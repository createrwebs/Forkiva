<?php

namespace Modules\Inventory\Services\StockSync;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Currency\Currency;
use Modules\Inventory\Data\StockMovementData;
use Modules\Inventory\Data\StockSyncResult;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\OrderProductIngredientSnapshot;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\StockMovement\StockMovementServiceInterface;
use Modules\Option\Models\OptionValue;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Order\Models\OrderProductOption;
use Modules\Product\Enums\IngredientOperation;
use Modules\Product\Enums\ProductInventoryConsumptionMode;
use Modules\Product\Models\Ingredientable;
use Modules\Product\Models\Product;

class StockSyncService implements StockSyncServiceInterface
{
    private const QUANTITY_SCALE = 6;

    private const LOSS_SCALE = 4;

    /**
     * Create a new StockSyncService instance.
     */
    public function __construct(protected StockMovementServiceInterface $movements) {}

    /** {@inheritDoc} */
    public function snapshotOrderStock(Order $order, bool $replaceExisting = false): void
    {
        DB::transaction(function () use ($order, $replaceExisting): void {
            $order = Order::query()
                ->withOutGlobalBranchPermission()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($order->is_stock_deducted
                || in_array($order->status, [
                    OrderStatus::Cancelled,
                    OrderStatus::Refunded,
                    OrderStatus::Merged,
                ], true)) {
                return;
            }

            if ($order->stock_bom_snapshotted_at && ! $replaceExisting) {
                return;
            }

            $this->replaceOrderStockSnapshot($order);
        });
    }

    /**
     * Persist the currently resolved order BOM as an immutable deduction input.
     */
    private function replaceOrderStockSnapshot(Order $order): void
    {
        $order->load($this->orderRelations());
        $rows = [];

        /** @var OrderProduct $orderProduct */
        foreach ($order->products as $orderProduct) {
            if ($orderProduct->status === OrderProductStatus::Cancelled) {
                continue;
            }

            $lines = $this->resolveProductBom(
                $orderProduct->product,
                $orderProduct->options->flatMap(fn (OrderProductOption $option) => $option->values),
                (int) $order->branch_id,
            );

            /** @var Ingredientable $line */
            foreach ($lines as $line) {
                if (! $line->relationLoaded('ingredient') || ! $line->ingredient) {
                    throw new InvalidStockSyncConfigurationException(
                        __('inventory::messages.invalid_stock_sync_snapshot'),
                    );
                }

                $netQuantity = $this->scaled($line->quantity, self::QUANTITY_SCALE);
                $grossQuantity = $this->grossQuantity($line);
                $productQuantity = $this->scaled(
                    $orderProduct->getRawOriginal('quantity'),
                    self::QUANTITY_SCALE,
                );
                $quantity = $this->scaled(
                    $this->decimal($grossQuantity)->multipliedBy($productQuantity),
                    self::QUANTITY_SCALE,
                );

                if ($this->decimal($quantity)->isLessThanOrEqualTo(BigDecimal::zero())) {
                    continue;
                }

                $rows[] = [
                    'order_id' => $order->id,
                    'order_product_id' => $orderProduct->id,
                    'ingredient_id' => (int) $line->ingredient_id,
                    'unit_id' => (int) $line->ingredient->unit_id,
                    'branch_id' => (int) $order->branch_id,
                    'net_quantity_per_product' => $netQuantity,
                    'gross_quantity_per_product' => $grossQuantity,
                    'order_product_quantity' => $productQuantity,
                    'quantity' => $quantity,
                    'loss_pct' => $this->scaled($line->loss_pct ?? 0, self::LOSS_SCALE),
                    'is_returnable' => (bool) $line->ingredient->is_returnable,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        OrderProductIngredientSnapshot::query()
            ->withOutGlobalBranchPermission()
            ->where('order_id', $order->id)
            ->delete();

        if ($rows !== []) {
            OrderProductIngredientSnapshot::query()
                ->withOutGlobalBranchPermission()
                ->insert($rows);
        }

        $order->forceFill(['stock_bom_snapshotted_at' => now()])->save();
    }

    /**
     * Get relations required to calculate the order ingredient snapshot.
     *
     * @return array<string, mixed>
     */
    private function orderRelations(): array
    {
        return [
            'branch:id,currency',
            'products' => fn ($query) => $query->with([
                'product' => fn ($query) => $query->with([
                    'menu:id,branch_id',
                    'producedIngredient:id,branch_id,is_returnable,unit_id',
                    'ingredients' => fn ($query) => $query
                        ->withOutGlobalBranchPermission()
                        ->select('id', 'branch_id', 'ingredientable_id', 'ingredientable_type', 'quantity', 'loss_pct', 'ingredient_id')
                        ->with('ingredient:id,branch_id,is_returnable,unit_id'),
                ]),
                'options' => fn ($query) => $query->with([
                    'values' => fn ($query) => $query->with([
                        'ingredients' => fn ($query) => $query
                            ->withOutGlobalBranchPermission()
                            ->select('id', 'branch_id', 'ingredientable_id', 'ingredientable_type', 'operation', 'quantity', 'loss_pct', 'ingredient_id')
                            ->with('ingredient:id,branch_id,is_returnable,unit_id'),
                    ]),
                ]),
            ]),
        ];
    }

    /** {@inheritDoc} */
    public function resolveProductBom(Product $product, Collection $values, int $branchId): Collection
    {
        $this->assertBomOwnersBelongToBranch($product, $values, $branchId);

        if ($product->inventory_consumption_mode === ProductInventoryConsumptionMode::ProductionOutput) {
            return $this->resolveProductionOutputBom($product, $branchId);
        }

        $this->assertBomLinesBelongToBranch($product->ingredients, $branchId);

        $lines = $product->ingredients
            ->groupBy('ingredient_id')
            ->map(fn (Collection $group): Ingredientable => $this->aggregateIngredientLines($group))
            ->keyBy('ingredient_id');

        foreach ($values as $value) {
            $this->assertBomLinesBelongToBranch($value->ingredients, $branchId);
            $this->assertModifierOperationsAreCompatible($value->ingredients);

            $modifiers = $value->ingredients
                ->groupBy('ingredient_id')
                ->map(fn (Collection $group): Ingredientable => $this->aggregateIngredientLines($group));

            /** @var Ingredientable $modifier */
            foreach ($modifiers as $modifier) {
                $ingredientId = (int) $modifier->ingredient_id;

                match ($modifier->operation) {
                    IngredientOperation::Add => $this->addModifier($lines, $ingredientId, $modifier),
                    IngredientOperation::Subtract => $this->subtractModifier($lines, $ingredientId, $modifier),
                    IngredientOperation::Replace => $lines->put($ingredientId, $modifier),
                    IngredientOperation::Multiply => $this->multiplyModifier($lines, $ingredientId, $modifier),
                };
            }
        }

        return $lines;
    }

    /**
     * Resolve a production-managed product to exactly one prepared inventory item.
     *
     * Raw recipe and option ingredient rows are intentionally ignored in this mode,
     * because their stock was already consumed when the production batch was posted.
     *
     * @return Collection<int, Ingredientable>
     */
    private function resolveProductionOutputBom(Product $product, int $branchId): Collection
    {
        $product->loadMissing('producedIngredient:id,branch_id,is_returnable,unit_id');
        $ingredient = $product->producedIngredient;
        $quantity = $this->decimal($product->produced_ingredient_quantity);

        if (! $ingredient
            || (int) $ingredient->branch_id !== $branchId
            || $quantity->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new InvalidStockSyncConfigurationException(
                __('inventory::messages.invalid_stock_sync_production_output'),
            );
        }

        $line = new Ingredientable([
            'branch_id' => $branchId,
            'ingredient_id' => $ingredient->id,
            'quantity' => $this->scaled($quantity, self::QUANTITY_SCALE),
            'loss_pct' => 0,
            'operation' => IngredientOperation::Add,
        ]);
        $line->setRelation('ingredient', $ingredient);

        return collect([$line]);
    }

    /**
     * Validate product and selected option-value ownership independently of recipe rows.
     */
    private function assertBomOwnersBelongToBranch(Product $product, Collection $values, int $branchId): void
    {
        if ($product->exists) {
            $product->loadMissing('menu:id,branch_id');
        }

        $productBranchId = $product->relationLoaded('menu')
            ? $product->menu?->branch_id
            : null;
        $invalidProduct = ($product->exists || $productBranchId !== null)
            && (int) $productBranchId !== $branchId;
        $invalidValue = $values->contains(function (OptionValue $value) use ($branchId): bool {
            $valueBranchId = $value->getRawOriginal('branch_id') ?? $value->branch_id;

            return ($value->exists || $valueBranchId !== null)
                && (int) $valueBranchId !== $branchId;
        });

        if ($invalidProduct || $invalidValue) {
            throw new InvalidStockSyncConfigurationException(
                __('inventory::messages.invalid_stock_sync_bom_owner_branch'),
            );
        }
    }

    /**
     * Ensure every bill-of-material line and ingredient belongs to the order branch.
     *
     * @param  Collection<int, Ingredientable>  $lines
     */
    private function assertBomLinesBelongToBranch(Collection $lines, int $branchId): void
    {
        $hasInvalidLine = $lines->contains(function (Ingredientable $line) use ($branchId): bool {
            if ((int) $line->branch_id !== $branchId) {
                return true;
            }

            if ($line->exists && ! $line->relationLoaded('ingredient')) {
                $line->loadMissing('ingredient:id,branch_id,is_returnable,unit_id');
            }

            return $line->exists
                && (! $line->ingredient || (int) $line->ingredient->branch_id !== $branchId);
        });

        if ($hasInvalidLine) {
            throw new InvalidStockSyncConfigurationException(
                __('inventory::messages.invalid_stock_sync_bom_branch'),
            );
        }
    }

    /**
     * Aggregate duplicate ingredient rows without inflating their loss percentage.
     *
     * @param  Collection<int, Ingredientable>  $lines
     */
    private function aggregateIngredientLines(Collection $lines): Ingredientable
    {
        /** @var Ingredientable $line */
        $line = $lines->first()->replicate();
        $netQuantity = $lines->reduce(
            fn (BigDecimal $total, Ingredientable $item): BigDecimal => $total->plus($item->quantity),
            BigDecimal::zero(),
        );
        $grossQuantity = $lines->reduce(
            fn (BigDecimal $total, Ingredientable $item): BigDecimal => $total->plus($this->grossQuantity($item)),
            BigDecimal::zero(),
        );

        $line->quantity = $this->scaled($netQuantity, self::QUANTITY_SCALE);
        $line->loss_pct = $this->effectiveLossPercentage($netQuantity, $grossQuantity);

        return $line;
    }

    /**
     * Calculate the usable quantity plus its configured processing loss.
     */
    private function grossQuantity(Ingredientable $line): string
    {
        $quantity = $this->decimal($line->quantity);
        $lossMultiplier = BigDecimal::one()->plus(
            $this->decimal($line->loss_pct ?? 0)
                ->dividedBy(100, self::QUANTITY_SCALE + self::LOSS_SCALE, RoundingMode::HalfUp),
        );

        return $this->scaled(
            $quantity->multipliedBy($lossMultiplier),
            self::QUANTITY_SCALE,
        );
    }

    /**
     * Convert a persisted decimal value without performing binary floating-point arithmetic.
     */
    private function decimal(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) ($value ?? 0));
    }

    /**
     * Round and serialize a decimal for a database or service boundary.
     */
    private function scaled(mixed $value, int $scale): string
    {
        return (string) $this->decimal($value)->toScale($scale, RoundingMode::HalfUp);
    }

    /**
     * Convert net and gross quantities into one weighted loss percentage.
     */
    private function effectiveLossPercentage(mixed $netQuantity, mixed $grossQuantity): string
    {
        $net = $this->decimal($netQuantity);
        $gross = $this->decimal($grossQuantity);

        if ($net->isLessThanOrEqualTo(BigDecimal::zero())) {
            return $this->scaled(0, self::LOSS_SCALE);
        }

        $loss = $gross
            ->dividedBy($net, self::QUANTITY_SCALE + self::LOSS_SCALE, RoundingMode::HalfUp)
            ->minus(1)
            ->multipliedBy(100);

        return $this->scaled(
            $loss->isLessThan(BigDecimal::zero()) ? 0 : $loss,
            self::LOSS_SCALE,
        );
    }

    /**
     * Prevent a grouped ingredient from silently inheriting the first modifier operation.
     */
    private function assertModifierOperationsAreCompatible(Collection $lines): void
    {
        $hasMixedOperations = $lines
            ->groupBy('ingredient_id')
            ->contains(
                fn (Collection $group): bool => $group
                    ->pluck('operation')
                    ->map(fn (IngredientOperation $operation): string => $operation->value)
                    ->unique()
                    ->count() > 1,
            );

        if ($hasMixedOperations) {
            throw new InvalidStockSyncConfigurationException(
                __('inventory::messages.mixed_stock_sync_modifier_operations'),
            );
        }
    }

    /**
     * Apply an additive option modifier.
     */
    private function addModifier(Collection $lines, int $ingredientId, Ingredientable $modifier): void
    {
        if (! $lines->has($ingredientId)) {
            $lines->put($ingredientId, $modifier);

            return;
        }

        /** @var Ingredientable $line */
        $line = $lines[$ingredientId];
        $netQuantity = $this->decimal($line->quantity)->plus($modifier->quantity);
        $grossQuantity = $this->decimal($this->grossQuantity($line))
            ->plus($this->grossQuantity($modifier));

        $line->quantity = $this->scaled($netQuantity, self::QUANTITY_SCALE);
        $line->loss_pct = $this->effectiveLossPercentage($netQuantity, $grossQuantity);
    }

    /**
     * Apply a subtractive option modifier.
     */
    private function subtractModifier(Collection $lines, int $ingredientId, Ingredientable $modifier): void
    {
        if (! $lines->has($ingredientId)) {
            return;
        }

        /** @var Ingredientable $line */
        $line = $lines[$ingredientId];
        $netQuantity = $this->decimal($line->quantity)->minus($modifier->quantity);

        if ($netQuantity->isLessThanOrEqualTo(BigDecimal::zero())) {
            $lines->forget($ingredientId);

            return;
        }

        $calculatedGross = $this->decimal($this->grossQuantity($line))
            ->minus($this->grossQuantity($modifier));
        $grossQuantity = $calculatedGross->isLessThan($netQuantity)
            ? $netQuantity
            : $calculatedGross;

        $line->quantity = $this->scaled($netQuantity, self::QUANTITY_SCALE);
        $line->loss_pct = $this->effectiveLossPercentage($netQuantity, $grossQuantity);
    }

    /**
     * Apply a multiplicative option modifier.
     */
    private function multiplyModifier(Collection $lines, int $ingredientId, Ingredientable $modifier): void
    {
        if (! $lines->has($ingredientId)) {
            return;
        }

        /** @var Ingredientable $line */
        $line = $lines[$ingredientId];
        $line->quantity = $this->scaled(
            $this->decimal($line->quantity)->multipliedBy($modifier->quantity),
            self::QUANTITY_SCALE,
        );
        $lineLossMultiplier = BigDecimal::one()->plus(
            $this->decimal($line->loss_pct ?? 0)
                ->dividedBy(100, self::QUANTITY_SCALE + self::LOSS_SCALE, RoundingMode::HalfUp),
        );
        $modifierLossMultiplier = BigDecimal::one()->plus(
            $this->decimal($modifier->loss_pct ?? 0)
                ->dividedBy(100, self::QUANTITY_SCALE + self::LOSS_SCALE, RoundingMode::HalfUp),
        );
        $line->loss_pct = $this->scaled(
            $lineLossMultiplier
                ->multipliedBy($modifierLossMultiplier)
                ->minus(1)
                ->multipliedBy(100),
            self::LOSS_SCALE,
        );
    }

    /** {@inheritDoc} */
    public function deductOrderStock(Order $order): StockSyncResult
    {
        return DB::transaction(function () use ($order): StockSyncResult {
            $order = Order::query()
                ->withOutGlobalBranchPermission()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($order->status !== OrderStatus::Completed) {
                throw new StockSyncNotRequiredException(trans('inventory::messages.order_stock_deduction_not_required', [
                    'reference' => $order->reference_no,
                    'status' => $order->status->value,
                ]));
            }

            if ($order->is_stock_deducted) {
                return $this->resultFromMovements(
                    $order,
                    $this->activeOrderDeductionMovements($order),
                );
            }

            if (! $order->stock_bom_snapshotted_at) {
                $this->replaceOrderStockSnapshot($order);
            } else {
                $order->load([
                    'branch:id,currency',
                    'products',
                ]);
            }

            $snapshots = OrderProductIngredientSnapshot::query()
                ->withOutGlobalBranchPermission()
                ->with('ingredient:id,branch_id,unit_id')
                ->where('branch_id', $order->branch_id)
                ->where('order_id', $order->id)
                ->orderBy('order_product_id')
                ->orderBy('ingredient_id')
                ->lockForUpdate()
                ->get()
                ->groupBy('order_product_id');
            $unitIds = $snapshots
                ->flatten()
                ->mapWithKeys(fn (OrderProductIngredientSnapshot $snapshot): array => [
                    $snapshot->ingredient_id => $snapshot->unit_id,
                ]);

            $quantities = collect();
            $productQuantities = collect();

            /** @var OrderProduct $orderProduct */
            foreach ($order->products as $orderProduct) {
                if ($orderProduct->status === OrderProductStatus::Cancelled) {
                    continue;
                }

                /** @var OrderProductIngredientSnapshot $line */
                foreach ($snapshots->get($orderProduct->id, collect()) as $line) {
                    if (! $line->ingredient
                        || (int) $line->branch_id !== (int) $order->branch_id
                        || (int) $line->ingredient->branch_id !== (int) $order->branch_id
                        || (int) $line->ingredient->unit_id !== (int) $line->unit_id) {
                        throw new InvalidStockSyncConfigurationException(
                            __('inventory::messages.invalid_stock_sync_snapshot'),
                        );
                    }

                    $quantity = $this->decimal($line->getRawOriginal('quantity') ?? $line->quantity);

                    if ($quantity->isLessThanOrEqualTo(BigDecimal::zero())) {
                        continue;
                    }

                    $ingredientId = (int) $line->ingredient_id;
                    $quantities->put(
                        $ingredientId,
                        $this->scaled(
                            $this->decimal($quantities->get($ingredientId, 0))->plus($quantity),
                            self::QUANTITY_SCALE,
                        ),
                    );

                    $currentProductQuantities = collect($productQuantities->get($orderProduct->id, []));
                    $productQuantities->put(
                        $orderProduct->id,
                        $currentProductQuantities->put(
                            $ingredientId,
                            $this->scaled(
                                $this->decimal($currentProductQuantities->get($ingredientId, 0))->plus($quantity),
                                self::QUANTITY_SCALE,
                            ),
                        ),
                    );
                }
            }

            if (! $order->stock_effective_at) {
                $order->forceFill(['stock_effective_at' => now()])->save();
            }

            $currency = $order->branch?->currency ?: $order->currency ?: setting('default_currency', 'JOD');
            $currencyRate = $this->decimal($order->currency_rate)->toScale(8, RoundingMode::HalfUp);
            abort_if(
                $currencyRate->isLessThanOrEqualTo(BigDecimal::zero()),
                422,
                __('inventory::messages.stock_movement_currency_rate_invalid'),
            );
            $currencyPrecision = Currency::subunit($currency);
            $sourceType = $order->getMorphClass();

            $created = $quantities
                ->sortKeys()
                ->map(function (string $quantity, int $ingredientId) use ($order, $currency, $currencyRate, $sourceType, $unitIds): StockMovement {
                    return $this->movements->record(new StockMovementData(
                        branchId: (int) $order->branch_id,
                        ingredientId: $ingredientId,
                        type: StockMovementType::Out,
                        quantity: $quantity,
                        sourceType: $sourceType,
                        sourceId: $order->id,
                        idempotencyKey: "order-stock:deduct:$order->id:$ingredientId",
                        occurredAt: $order->stock_effective_at ?? now(),
                        currencyRate: (string) $currencyRate,
                        unitId: (int) $unitIds->get($ingredientId),
                        currency: $currency,
                        note: "Deducted due to order #$order->reference_no",
                        emitAccounting: false,
                    ));
                })
                ->values();

            $productCosts = $this->allocateMovementCosts(
                $created,
                $productQuantities,
                $currencyPrecision,
            );
            $totalCostPrice = BigDecimal::zero();
            $totalGrossProfit = BigDecimal::zero();

            /** @var OrderProduct $orderProduct */
            foreach ($order->products as $orderProduct) {
                if ($orderProduct->status === OrderProductStatus::Cancelled) {
                    continue;
                }

                $productCost = $this->scaled(
                    $productCosts->get($orderProduct->id, 0),
                    $currencyPrecision,
                );
                $grossProfit = $this->scaled(
                    $this->decimal($orderProduct->getRawOriginal('subtotal'))
                        ->minus($productCost),
                    $currencyPrecision,
                );
                $totalCostPrice = $totalCostPrice->plus($productCost);
                $totalGrossProfit = $totalGrossProfit->plus($grossProfit);

                $orderProduct->forceFill([
                    'cost_price' => $productCost,
                    'gross_profit' => $grossProfit,
                ])->save();
            }

            $order->forceFill([
                'cost_price' => $this->scaled($totalCostPrice, $currencyPrecision),
                'gross_profit' => $this->scaled($totalGrossProfit, $currencyPrecision),
                'is_stock_deducted' => true,
            ])->save();

            return $this->resultFromMovements(
                $order,
                $created,
                $this->scaled($totalCostPrice, $currencyPrecision),
                $this->scaled($totalGrossProfit, $currencyPrecision),
            );
        });
    }

    /**
     * Build a structured result exclusively from committed movement snapshots.
     *
     * @param  Collection<int, StockMovement>  $movements
     */
    private function resultFromMovements(
        Order $order,
        Collection $movements,
        ?string $orderCost = null,
        ?string $grossProfit = null,
    ): StockSyncResult {
        $movements = $movements->sortBy('id')->values();

        return new StockSyncResult(
            movements: $movements,
            ingredientQuantities: $movements
                ->groupBy('ingredient_id')
                ->map(fn (Collection $items): string => $this->scaled(
                    $items->reduce(
                        fn (BigDecimal $total, StockMovement $movement): BigDecimal => $total->plus(
                            $movement->getRawOriginal('quantity') ?? $movement->quantity,
                        ),
                        BigDecimal::zero(),
                    ),
                    self::QUANTITY_SCALE,
                ))
                ->all(),
            unitCosts: $movements
                ->groupBy('ingredient_id')
                ->map(fn (Collection $items): array => $items
                    ->mapWithKeys(fn (StockMovement $movement): array => [
                        $movement->id => $this->scaled(
                            $movement->getRawOriginal('unit_cost')
                            ?? ($movement->getAttributes()['unit_cost'] ?? 0),
                            self::QUANTITY_SCALE,
                        ),
                    ])
                    ->all())
                ->all(),
            currencies: $movements
                ->groupBy('ingredient_id')
                ->map(fn (Collection $items): array => $items
                    ->mapWithKeys(fn (StockMovement $movement): array => [
                        $movement->id => (string) $movement->currency,
                    ])
                    ->all())
                ->all(),
            orderCost: $orderCost ?? $this->scaled($order->getRawOriginal('cost_price'), Currency::subunit($order->currency)),
            grossProfit: $grossProfit ?? $this->scaled($order->getRawOriginal('gross_profit'), Currency::subunit($order->currency)),
        );
    }

    /**
     * Resolve the currently active deduction in each order movement correction chain.
     *
     * @return Collection<int, StockMovement>
     */
    private function activeOrderDeductionMovements(Order $order): Collection
    {
        return $this->orderMovements($order, StockMovementType::Out)
            ->map(fn (StockMovement $movement): ?StockMovement => $this->activeMovementInChain($movement))
            ->filter()
            ->sortBy('id')
            ->values();
    }

    /**
     * Get persisted order stock movements of the requested direction.
     *
     * @return Collection<int, StockMovement>
     */
    private function orderMovements(Order $order, StockMovementType $type): Collection
    {
        return StockMovement::query()
            ->withOutGlobalBranchPermission()
            ->with('ingredient:id,branch_id,unit_id,is_returnable')
            ->where('branch_id', $order->branch_id)
            ->where('source_type', $order->getMorphClass())
            ->where('source_id', $order->id)
            ->where('type', $type)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Follow correction replacements until the active committed movement is found.
     */
    private function activeMovementInChain(StockMovement $movement): ?StockMovement
    {
        $current = $movement;
        $visited = [];

        while (true) {
            if (isset($visited[$current->id])) {
                throw new InvalidStockSyncConfigurationException(
                    __('inventory::messages.invalid_stock_sync_movement_chain'),
                );
            }
            $visited[$current->id] = true;

            $reversal = StockMovement::query()
                ->withOutGlobalBranchPermission()
                ->where('reversal_of_id', $current->id)
                ->lockForUpdate()
                ->first();

            if (! $reversal) {
                return $current;
            }

            $replacement = StockMovement::query()
                ->withOutGlobalBranchPermission()
                ->where('source_type', ltrim(StockMovement::class, '\\'))
                ->where('source_id', $current->id)
                ->where('type', $current->type)
                ->whereNull('reversal_of_id')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $replacement) {
                return null;
            }

            $current = $replacement;
        }
    }

    /**
     * Allocate each committed movement value exactly across contributing products.
     *
     * @param  Collection<int, StockMovement>  $movements
     * @param  Collection<int, Collection<int, string>>  $productQuantities
     * @return Collection<int, string>
     */
    private function allocateMovementCosts(
        Collection $movements,
        Collection $productQuantities,
        int $currencyPrecision,
    ): Collection {
        $productCosts = collect();

        /** @var StockMovement $movement */
        foreach ($movements->sortBy('id') as $movement) {
            $ingredientId = (int) $movement->ingredient_id;
            $contributors = $productQuantities
                ->mapWithKeys(function (mixed $quantities, int $orderProductId) use ($ingredientId): array {
                    $quantity = $this->scaled(
                        collect($quantities)->get($ingredientId, 0),
                        self::QUANTITY_SCALE,
                    );

                    return $this->decimal($quantity)->isGreaterThan(BigDecimal::zero())
                        ? [$orderProductId => $quantity]
                        : [];
                })
                ->sortKeys();

            $totalQuantity = $contributors->reduce(
                fn (BigDecimal $total, string $quantity): BigDecimal => $total->plus($quantity),
                BigDecimal::zero(),
            );
            if ($totalQuantity->isLessThanOrEqualTo(BigDecimal::zero())) {
                throw new InvalidStockSyncConfigurationException(
                    __('inventory::messages.stock_sync_movement_cost_missing'),
                );
            }

            $movementCost = $this->decimal($movement->getRawOriginal('quantity'))
                ->multipliedBy($movement->getRawOriginal('unit_cost'))
                ->toScale(
                    $currencyPrecision,
                    RoundingMode::HalfUp,
                );
            $allocated = BigDecimal::zero();
            $lastProductId = (int) $contributors->keys()->last();

            foreach ($contributors as $orderProductId => $quantity) {
                $allocation = (int) $orderProductId === $lastProductId
                    ? $movementCost->minus($allocated)->toScale($currencyPrecision, RoundingMode::HalfUp)
                    : $movementCost
                        ->multipliedBy($quantity)
                        ->dividedBy($totalQuantity, $currencyPrecision, RoundingMode::HalfUp);
                $allocated = $allocated->plus($allocation);
                $productCosts->put(
                    (int) $orderProductId,
                    $this->scaled(
                        $this->decimal($productCosts->get((int) $orderProductId, 0))
                            ->plus($allocation),
                        $currencyPrecision,
                    ),
                );
            }
        }

        return $productCosts;
    }

    /** {@inheritDoc} */
    public function restoreOrderStock(Order $order): StockSyncResult
    {
        return DB::transaction(function () use ($order): StockSyncResult {
            $order = Order::query()
                ->withOutGlobalBranchPermission()
                ->with('branch:id,currency')
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (! in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
                throw new StockSyncNotRequiredException(
                    __('inventory::messages.order_stock_restoration_not_required', [
                        'reference' => $order->reference_no,
                        'status' => $order->status->value,
                    ])
                );
            }

            $deductions = $this->activeOrderDeductionMovements($order);
            if ((bool) $order->is_stock_deducted !== $deductions->isNotEmpty()) {
                throw new InvalidStockSyncConfigurationException(
                    __('inventory::messages.stock_sync_state_mismatch'),
                );
            }

            if ($deductions->isEmpty()) {
                return $this->resultFromMovements($order, collect(), '0.000');
            }

            $returnableDeductions = $this->returnableDeductions($order, $deductions);
            if ($returnableDeductions->contains(
                fn (StockMovement $movement): bool => is_null($movement->getRawOriginal('unit_cost'))
                    || blank($movement->currency)
                    || $movement->legacy_cost_snapshot_missing
            )) {
                throw new InvalidStockSyncConfigurationException(
                    __('inventory::messages.stock_sync_movement_cost_missing'),
                );
            }
            $restorations = $returnableDeductions
                ->sortBy('id')
                ->map(function (StockMovement $deduction) use ($order): StockMovement {
                    return $this->movements->reverse(
                        $deduction,
                        "order-stock:restore:$order->id:movement:$deduction->id",
                        "Restored from deduction movement #$deduction->id for order #$order->reference_no",
                        false,
                    );
                })
                ->values();

            $hasActiveDeductions = $deductions->count() !== $returnableDeductions->count();
            $order->forceFill(['is_stock_deducted' => $hasActiveDeductions])->save();

            $currency = $order->branch->currency;
            $currencyPrecision = Currency::subunit($currency);

            $originalDeductionCost = $returnableDeductions->reduce(
                fn (BigDecimal $total, StockMovement $movement): BigDecimal => $total->plus(
                    $this->decimal($movement->getRawOriginal('quantity'))
                        ->multipliedBy($movement->getRawOriginal('unit_cost'))
                        ->toScale($currencyPrecision, RoundingMode::HalfUp),
                ),
                BigDecimal::zero(),
            );

            return $this->resultFromMovements(
                $order,
                $restorations,
                $this->scaled($originalDeductionCost, $currencyPrecision),
                $this->scaled($order->getRawOriginal('gross_profit'), $currencyPrecision),
            );
        });
    }

    /**
     * Select deductions whose ingredient was returnable when the order BOM was frozen.
     *
     * Legacy orders without snapshots fall back to the current ingredient flag.
     *
     * @param  Collection<int, StockMovement>  $deductions
     * @return Collection<int, StockMovement>
     */
    private function returnableDeductions(Order $order, Collection $deductions): Collection
    {
        $snapshots = OrderProductIngredientSnapshot::query()
            ->withOutGlobalBranchPermission()
            ->where('branch_id', $order->branch_id)
            ->where('order_id', $order->id)
            ->whereIn('ingredient_id', $deductions->pluck('ingredient_id')->all())
            ->get(['ingredient_id', 'is_returnable'])
            ->groupBy('ingredient_id');

        return $deductions
            ->filter(function (StockMovement $movement) use ($snapshots): bool {
                $ingredientSnapshots = $snapshots->get($movement->ingredient_id, collect());

                if ($ingredientSnapshots->isNotEmpty()) {
                    if ($ingredientSnapshots->pluck('is_returnable')->unique()->count() !== 1) {
                        throw new InvalidStockSyncConfigurationException(
                            __('inventory::messages.invalid_stock_sync_snapshot'),
                        );
                    }

                    return (bool) $ingredientSnapshots->first()->is_returnable;
                }

                return (bool) $movement->ingredient?->is_returnable;
            })
            ->values();
    }
}
