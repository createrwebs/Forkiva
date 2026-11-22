<?php

namespace Modules\Inventory\Services\StockMovement;

use App\Forkiva;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Currency\Currency;
use Modules\Inventory\Data\StockMovementCorrectionResult;
use Modules\Inventory\Data\StockMovementData;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\InventoryCostBalance;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\StockTransferItem;
use Modules\Inventory\Models\StockTransferReceiptItem;
use Modules\Inventory\Services\FrozenCurrencyRate\FrozenCurrencyRate;
use Modules\Inventory\Services\StockCostResolver\StockCostResolverServiceInterface;
use Modules\Support\GlobalStructureFilters;
use Throwable;

class StockMovementService implements StockMovementServiceInterface
{
    /**
     * Create the stock movement service.
     */
    public function __construct(
        protected StockCostResolverServiceInterface $costResolver,
        protected BranchAccessServiceInterface $branchAccess,
    ) {}

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('inventory::stock_movements.stock_movement');
    }

    /** {@inheritDoc} */
    public function store(array $data): StockMovement
    {
        $type = $data['type'] instanceof StockMovementType
            ? $data['type']
            : StockMovementType::from($data['type']);

        abort_unless(
            in_array($type, [
                StockMovementType::AdjustAdd,
                StockMovementType::AdjustSubtract,
                StockMovementType::Waste,
                StockMovementType::Spoil,
                StockMovementType::Sample,
                StockMovementType::StaffMeal,
            ], true),
            422,
            __('inventory::messages.manual_stock_movement_type_invalid'),
        );

        $branchId = (int) $this->branchAccess->resolve($data['branch_id'] ?? null);
        $branch = Branch::query()->findOrFail($branchId);

        return $this->record(new StockMovementData(
            branchId: $branchId,
            ingredientId: (int) $data['ingredient_id'],
            type: $type,
            quantity: (string) $data['quantity'],
            sourceType: in_array($type, [
                StockMovementType::Waste,
                StockMovementType::Spoil,
                StockMovementType::Sample,
                StockMovementType::StaffMeal,
            ], true) ? 'manual_stock_loss' : 'manual_adjustment',
            sourceId: (int) (auth()->id() ?: 0),
            idempotencyKey: 'manual-stock:'.$data['idempotency_key'],
            occurredAt: now(),
            currencyRate: FrozenCurrencyRate::for($branch->currency),
            note: $data['note'] ?? null,
        ));
    }

    /** {@inheritDoc} */
    public function record(StockMovementData $data): StockMovement
    {
        abort_if(
            BigDecimal::of($data->quantity)->isLessThanOrEqualTo(BigDecimal::zero()),
            422,
            __('inventory::messages.stock_movement_quantity_positive'),
        );
        abort_if(blank($data->idempotencyKey), 422, __('inventory::messages.stock_movement_idempotency_required'));
        abort_if(
            $this->decimal($data->currencyRate)->isLessThanOrEqualTo(BigDecimal::zero()),
            422,
            __('inventory::messages.stock_movement_currency_rate_invalid'),
        );

        try {
            $movement = DB::transaction(function () use ($data): StockMovement {
                $existing = StockMovement::query()
                    ->withOutGlobalBranchPermission()
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $this->assertIdempotentPayloadMatches($existing, $this->payload($data, $existing));

                    return $existing;
                }

                $ingredient = Ingredient::query()
                    ->withOutGlobalBranchPermission()
                    ->whereKey($data->ingredientId)
                    ->whereBranch($data->branchId)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_if(
                    ! is_null($data->unitId) && (int) $data->unitId !== (int) $ingredient->unit_id,
                    422,
                    __('inventory::messages.stock_movement_unit_snapshot_mismatch'),
                );

                // The ingredient lock serializes all movements for the same stock item.
                // Recheck the key after acquiring it so concurrent requests cannot both
                // pass the optimistic lookup and create duplicate committed movements.
                $existing = StockMovement::query()
                    ->withOutGlobalBranchPermission()
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->first();

                if ($existing) {
                    $this->assertIdempotentPayloadMatches($existing, $this->payload($data, $existing));

                    return $existing;
                }

                $branch = Branch::query()->findOrFail($data->branchId);
                $balance = InventoryCostBalance::query()
                    ->withOutGlobalBranchPermission()
                    ->firstOrCreate(
                        [
                            'branch_id' => $data->branchId,
                            'ingredient_id' => $data->ingredientId,
                        ],
                        [
                            'quantity_on_hand' => 0,
                            'inventory_value' => 0,
                            'average_unit_cost' => null,
                            'currency' => $branch->currency,
                            'requires_remediation' => ! $this->decimal($ingredient->getRawOriginal('current_stock'))->isZero(),
                            'remediation_reason' => ! $this->decimal($ingredient->getRawOriginal('current_stock'))->isZero()
                                ? 'stock_exists_without_cost_balance'
                                : null,
                        ],
                    );
                $balance = InventoryCostBalance::query()
                    ->withOutGlobalBranchPermission()
                    ->whereKey($balance->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $cost = $this->costResolver->resolve($data, $ingredient, $balance);
                abort_if(
                    $cost['currency'] !== strtoupper($branch->currency),
                    422,
                    __('inventory::messages.stock_movement_currency_mismatch'),
                );

                $updated = $this->costResolver->apply($balance, $data, $cost['unit_cost']);
                abort_if(
                    ! $this->allowsNegativeStock() && $this->decimal($updated['quantity'])->isLessThan(BigDecimal::zero()),
                    422,
                    __('inventory::messages.negative_stock_not_allowed'),
                );
                abort_if(
                    $data->type === StockMovementType::ReturnToSupplier
                    && $this->decimal($updated['quantity'])->isLessThan(BigDecimal::zero()),
                    422,
                    __('inventory::messages.supplier_return_insufficient_stock'),
                );

                $movement = StockMovement::query()
                    ->withOutGlobalBranchPermission()
                    ->create($this->payload(
                        data: $data,
                        cost: $cost,
                        resolvedUnitId: (int) $ingredient->unit_id,
                    ));

                $balance->forceFill([
                    'quantity_on_hand' => $updated['quantity'],
                    'inventory_value' => $updated['value'],
                    'average_unit_cost' => $updated['average_cost'],
                    'currency' => $cost['currency'],
                ])->save();

                $ingredient->forceFill([
                    'current_stock' => $updated['quantity'],
                    'cost_per_unit' => $updated['average_cost'],
                ])->save();

                return $movement;
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->recordSourceFailure($data, $exception);

            throw $exception;
        }

        if ($movement->wasRecentlyCreated && $data->emitAccounting) {
            DB::afterCommit(fn () => $this->recordAccountingEvent($movement));
        }

        return $movement->fresh(['ingredient:id,name,unit_id', 'unit:id,symbol']);
    }

    /**
     * Ensure an existing idempotent movement represents the same operation.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertIdempotentPayloadMatches(StockMovement $movement, array $data): void
    {
        $expected = [
            'branch_id' => (int) $data['branch_id'],
            'ingredient_id' => (int) $data['ingredient_id'],
            'unit_id' => isset($data['unit_id']) ? (int) $data['unit_id'] : null,
            'type' => $data['type'] instanceof StockMovementType
                ? $data['type']->value
                : (string) $data['type'],
            'quantity' => $this->scaled($data['quantity'], 6),
            'currency_rate' => $this->scaled($data['currency_rate'], 8),
            'source_id' => isset($data['source_id']) ? (int) $data['source_id'] : null,
            'source_type' => isset($data['source_type'])
                ? ltrim((string) $data['source_type'], '\\')
                : null,
            'reversal_of_id' => isset($data['reversal_of_id'])
                ? (int) $data['reversal_of_id']
                : null,
        ];

        $actual = [
            'branch_id' => (int) $movement->getRawOriginal('branch_id'),
            'ingredient_id' => (int) $movement->getRawOriginal('ingredient_id'),
            'unit_id' => $movement->getRawOriginal('unit_id') === null
                ? null
                : (int) $movement->getRawOriginal('unit_id'),
            'type' => (string) $movement->getRawOriginal('type'),
            'quantity' => $this->scaled($movement->getRawOriginal('quantity'), 6),
            'currency_rate' => $this->scaled($movement->getRawOriginal('currency_rate'), 8),
            'source_id' => $movement->getRawOriginal('source_id') === null
                ? null
                : (int) $movement->getRawOriginal('source_id'),
            'source_type' => $movement->getRawOriginal('source_type') === null
                ? null
                : ltrim((string) $movement->getRawOriginal('source_type'), '\\'),
            'reversal_of_id' => $movement->getRawOriginal('reversal_of_id') === null
                ? null
                : (int) $movement->getRawOriginal('reversal_of_id'),
        ];

        if ($expected !== $actual) {
            throw new StockMovementIdempotencyConflictException;
        }

        if (isset($data['currency'])
            && strtoupper((string) $data['currency']) !== strtoupper((string) $movement->getRawOriginal('currency'))) {
            throw new StockMovementIdempotencyConflictException;
        }

        if (isset($data['unit_cost'])
            && $this->scaled($data['unit_cost'], 6)
            !== $this->scaled($movement->getRawOriginal('unit_cost'), 6)) {
            throw new StockMovementIdempotencyConflictException;
        }
    }

    private function scaled(mixed $value, int $scale): string
    {
        return (string) $this->decimal($value)->toScale($scale, RoundingMode::HalfUp);
    }

    private function decimal(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) ($value ?? 0));
    }

    /**
     * Build the immutable movement payload persisted by the gateway.
     *
     * @param  array{unit_cost: string, currency: string}|null  $cost
     * @return array<string, mixed>
     */
    private function payload(
        StockMovementData $data,
        ?StockMovement $existing = null,
        ?array $cost = null,
        ?int $resolvedUnitId = null,
    ): array {
        $unitCost = $cost['unit_cost'] ?? $existing?->getRawOriginal('unit_cost') ?? $data->unitCost;
        $currency = $cost['currency'] ?? $existing?->getRawOriginal('currency') ?? $data->currency;
        $unitId = $resolvedUnitId ?? $existing?->getRawOriginal('unit_id') ?? $data->unitId;

        if (is_null($unitId) && ! is_null($existing)) {
            $unitId = $existing->ingredient?->unit_id;
        }

        return [
            'branch_id' => $data->branchId,
            'ingredient_id' => $data->ingredientId,
            'unit_id' => $unitId,
            'type' => $data->type,
            'quantity' => $data->quantity,
            'unit_cost' => $unitCost,
            'currency' => $currency ? strtoupper((string) $currency) : null,
            'currency_rate' => $data->currencyRate,
            'currency_rate_source' => $data->reversalOfId ? 'original_movement' : 'frozen_at_commit',
            'currency_rate_reviewed_at' => $existing?->currency_rate_reviewed_at ?? now(),
            'source_id' => (int) $data->sourceId,
            'source_type' => ltrim($data->sourceType, '\\'),
            'idempotency_key' => $data->idempotencyKey,
            'reversal_of_id' => $data->reversalOfId,
            'note' => $data->note,
            'occurred_at' => $data->occurredAt,
            'committed_at' => $existing?->committed_at ?? now(),
        ];
    }

    /** {@inheritDoc} */
    public function findOrFail(int $id): Builder|array|EloquentCollection|StockMovement
    {
        return $this->getModel()->query()->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function getModel(): StockMovement
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return StockMovement::class;
    }

    /**
     * Determine whether the current Inventory policy permits negative stock.
     */
    private function allowsNegativeStock(): bool
    {
        return (bool) config('inventory.stock.allow_negative', true);
    }

    /**
     * Preserve a failed trusted source operation in Accounting diagnostics.
     */
    private function recordSourceFailure(StockMovementData $data, Throwable $exception): void
    {
        if ($data->sourceType === 'manual_adjustment') {
            return;
        }

        $payload = [
            'branch_id' => $data->branchId,
            'ingredient_id' => $data->ingredientId,
            'unit_id' => $data->unitId,
            'movement_type' => $data->type->value,
            'quantity' => $data->quantity,
            'source_type' => $data->sourceType,
            'source_id' => $data->sourceId,
            'idempotency_key' => $data->idempotencyKey,
            'occurred_at' => $data->occurredAt->toIso8601String(),
        ];

        try {
            app(FinancialPostingBridgeInterface::class)->recordFailure(
                'stock_movement_source',
                (int) $data->sourceId,
                'record',
                class_basename($exception),
                $exception->getMessage(),
                $payload,
            );
        } catch (Throwable $diagnosticException) {
            Log::error('Unable to record failed stock movement source operation.', [
                ...$payload,
                'movement_error' => $exception->getMessage(),
                'diagnostic_error' => $diagnosticException->getMessage(),
            ]);
        }
    }

    /**
     * Record Accounting only after the operational stock transaction commits.
     */
    private function recordAccountingEvent(StockMovement $movement): void
    {
        $currencyPrecision = Currency::subunit($movement->currency);
        $payload = [
            'branch_id' => (int) $movement->branch_id,
            'ingredient_id' => $movement->ingredient_id,
            'unit_id' => (int) $movement->unit_id,
            'unit' => $movement->unit?->symbol,
            'movement_type' => $movement->type->value,
            'quantity' => $this->scaled($movement->getRawOriginal('quantity'), 6),
            'unit_cost' => $this->scaled($movement->getRawOriginal('unit_cost'), 6),
            'total_cost' => $this->scaled(
                $this->decimal($movement->getRawOriginal('quantity'))
                    ->multipliedBy($movement->getRawOriginal('unit_cost')),
                $currencyPrecision,
            ),
            'currency' => $movement->currency,
            'currency_rate' => $this->scaled($movement->getRawOriginal('currency_rate'), 8),
            'occurred_at' => $movement->occurred_at?->toIso8601String(),
            'idempotency_key' => $movement->idempotency_key,
            'reversal_of_id' => $movement->reversal_of_id,
        ];

        try {
            app(FinancialPostingBridgeInterface::class)->recordEvent(
                'stock_movement',
                $movement->id,
                'created',
                $payload,
            );
        } catch (Throwable $exception) {
            try {
                app(FinancialPostingBridgeInterface::class)->recordFailure(
                    'stock_movement',
                    $movement->id,
                    'created',
                    class_basename($exception),
                    $exception->getMessage(),
                    $payload,
                );
            } catch (Throwable $diagnosticException) {
                Log::error('Unable to record stock movement Accounting failure.', [
                    'movement_id' => $movement->id,
                    'idempotency_key' => $movement->idempotency_key,
                    'posting_error' => $exception->getMessage(),
                    'diagnostic_error' => $diagnosticException->getMessage(),
                ]);
            }
        }
    }

    /** {@inheritDoc} */
    public function correct(int $id, int|float|string $quantity, string $reason): StockMovementCorrectionResult
    {
        $quantity = $this->scaled($quantity, 6);
        abort_if(
            $this->decimal($quantity)->isLessThanOrEqualTo(BigDecimal::zero()),
            422,
            __('inventory::messages.stock_movement_quantity_positive'),
        );
        abort_if(blank($reason), 422, __('inventory::messages.stock_movement_reversal_reason_required'));

        return DB::transaction(function () use ($id, $quantity, $reason): StockMovementCorrectionResult {
            $original = StockMovement::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(! $original->committed_at, 422, __('inventory::messages.stock_movement_not_committed'));
            $transferSourceTypes = [
                StockTransferItem::class,
                StockTransferReceiptItem::class,
            ];
            $originalSourceType = $original->reversal_of_id
                ? StockMovement::query()
                    ->withOutGlobalBranchPermission()
                    ->whereKey($original->reversal_of_id)
                    ->value('source_type')
                : null;
            abort_if(
                in_array(ltrim((string) $original->source_type, '\\'), $transferSourceTypes, true)
                || in_array(ltrim((string) $originalSourceType, '\\'), $transferSourceTypes, true),
                422,
                __('inventory::messages.stock_transfer_movement_correction_forbidden'),
            );

            $correlation = hash('sha256', implode('|', [
                $original->id,
                $quantity,
                trim($reason),
            ]));
            $reversalKey = "stock-correction:$original->id:$correlation:reverse";
            $replacementKey = "stock-correction:$original->id:$correlation:replacement";

            $existingReplacement = StockMovement::query()
                ->where('idempotency_key', $replacementKey)
                ->first();

            if ($existingReplacement) {
                $existingReversal = StockMovement::query()
                    ->where('idempotency_key', $reversalKey)
                    ->firstOrFail();

                return new StockMovementCorrectionResult(
                    original: $original,
                    reversal: $existingReversal,
                    replacement: $existingReplacement,
                );
            }

            abort_if(
                $original->reversals()->exists(),
                422,
                __('inventory::messages.stock_movement_already_corrected'),
            );

            $reversal = $this->reverse($original, $reversalKey, trim($reason));
            $replacement = $this->record(new StockMovementData(
                branchId: (int) $original->branch_id,
                ingredientId: (int) $original->ingredient_id,
                type: $original->type,
                quantity: $quantity,
                sourceType: StockMovement::class,
                sourceId: $original->id,
                idempotencyKey: $replacementKey,
                occurredAt: now(),
                currencyRate: (string) $original->getRawOriginal('currency_rate'),
                unitId: (int) $original->unit_id,
                unitCost: (string) $original->getRawOriginal('unit_cost'),
                currency: $original->currency,
                note: trim($reason),
            ));

            return new StockMovementCorrectionResult(
                original: $original,
                reversal: $reversal,
                replacement: $replacement,
            );
        }, attempts: 3);
    }

    /** {@inheritDoc} */
    public function reverse(
        StockMovement $movement,
        string $idempotencyKey,
        string $reason,
        bool $emitAccounting = true,
    ): StockMovement {
        abort_if(! $movement->committed_at, 422, __('inventory::messages.stock_movement_not_committed'));
        abort_if(blank($reason), 422, __('inventory::messages.stock_movement_reversal_reason_required'));
        abort_if(
            $movement->legacy_cost_snapshot_missing
            || is_null($movement->getRawOriginal('unit_cost'))
            || blank($movement->currency)
            || $this->decimal($movement->getRawOriginal('currency_rate'))->isLessThanOrEqualTo(BigDecimal::zero()),
            422,
            __('inventory::messages.stock_sync_movement_cost_missing'),
        );

        return $this->record(new StockMovementData(
            branchId: (int) $movement->branch_id,
            ingredientId: $movement->ingredient_id,
            type: $this->inverseType($movement->type),
            quantity: (string) $movement->getRawOriginal('quantity'),
            sourceType: StockMovement::class,
            sourceId: $movement->id,
            idempotencyKey: $idempotencyKey,
            occurredAt: now(),
            currencyRate: (string) $movement->getRawOriginal('currency_rate'),
            unitId: (int) $movement->unit_id,
            unitCost: (string) $movement->getRawOriginal('unit_cost'),
            currency: $movement->currency,
            reversalOfId: $movement->id,
            note: $reason,
            emitAccounting: $emitAccounting,
        ));
    }

    /**
     * Resolve the exact inverse movement type used by a correction or restoration.
     */
    private function inverseType(StockMovementType $type): StockMovementType
    {
        return match ($type) {
            StockMovementType::In => StockMovementType::Out,
            StockMovementType::Out => StockMovementType::In,
            StockMovementType::AdjustAdd => StockMovementType::AdjustSubtract,
            StockMovementType::AdjustSubtract,
            StockMovementType::Spoil,
            StockMovementType::ReturnToSupplier,
            StockMovementType::Sample,
            StockMovementType::Waste,
            StockMovementType::StaffMeal => StockMovementType::AdjustAdd,
            StockMovementType::TransferIn => StockMovementType::TransferOut,
            StockMovementType::TransferOut => StockMovementType::TransferIn,
        };
    }

    /** {@inheritDoc} */
    public function show(int $id): StockMovement
    {
        return $this->getModel()
            ->query()
            ->with([
                'branch:id,name,currency',
                'ingredient:id,name,unit_id',
                'unit:id,symbol',
                'originalMovement:id,type',
                'reversals:id,reversal_of_id,type,quantity',
                'createdBy:id,name',
            ])
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator
    {
        return $this->exportQuery($filters, $sorts)
            ->paginate(Forkiva::paginate())
            ->withQueryString();
    }

    /** {@inheritDoc} */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder
    {
        return $this->getModel()
            ->query()
            ->select('stock_movements.*')
            ->with([
                'ingredient:id,name,unit_id',
                'unit:id,symbol',
                'branch:id,name,currency',
                'originalMovement:id',
            ])
            ->filters($filters)
            ->sortBy($sorts);
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'stock_movements';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.stock_movements.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return StockMovement::class;
    }

    /** {@inheritDoc} */
    public function getStructureFilters(?int $branchId = null): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            [
                'key' => 'type',
                'label' => __('inventory::stock_movements.filters.type'),
                'type' => 'select',
                'options' => StockMovementType::toArrayTrans(),
            ],
            [
                'key' => 'ingredient_id',
                'label' => __('inventory::stock_movements.filters.ingredient'),
                'type' => 'select',
                'options' => ! is_null($branchId) ? Ingredient::list($branchId) : [],
                'depends' => 'branch_id',
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function getFormMeta(?int $branchId = null): array
    {
        $resolvedBranchId = $this->branchAccess->resolve($branchId);

        if (is_null($branchId)) {
            return [
                'branches' => Branch::list(true),
                'types' => $this->manualMovementTypes(),
            ];
        }

        return [
            'ingredients' => Ingredient::list($resolvedBranchId),
        ];
    }

    /**
     * Return movement types that an administrator may record manually.
     *
     * Operational types are deliberately excluded because their source, cost,
     * currency, and idempotency values must come from trusted module services.
     *
     * @return array<int, array<string, mixed>>
     */
    private function manualMovementTypes(): array
    {
        return array_values(array_filter(
            StockMovementType::toArrayTrans(),
            static fn (array $option): bool => in_array($option['id'], [
                StockMovementType::AdjustAdd->value,
                StockMovementType::AdjustSubtract->value,
                StockMovementType::Waste->value,
                StockMovementType::Spoil->value,
                StockMovementType::Sample->value,
                StockMovementType::StaffMeal->value,
            ], true),
        ));
    }
}
