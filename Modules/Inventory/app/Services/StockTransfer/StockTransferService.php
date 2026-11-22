<?php

namespace Modules\Inventory\Services\StockTransfer;

use App\Forkiva;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Inventory\Data\StockMovementData;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Enums\StockTransferStatus;
use Modules\Inventory\Events\StockTransferStatusChanged;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\StockTransferItem;
use Modules\Inventory\Models\StockTransferReceipt;
use Modules\Inventory\Models\StockTransferReceiptItem;
use Modules\Inventory\Services\FrozenCurrencyRate\FrozenCurrencyRate;
use Modules\Inventory\Services\StockMovement\StockMovementServiceInterface;
use Modules\Support\GlobalStructureFilters;
use Modules\User\Models\User;
use Throwable;

class StockTransferService implements StockTransferServiceInterface
{
    private const QUANTITY_SCALE = 6;

    private const MAX_DECIMAL_VALUE = '999999999999.999999';

    /**
     * Create the stock-transfer workflow service.
     */
    public function __construct(
        protected StockMovementServiceInterface $stockMovements,
        protected FinancialPostingBridgeInterface $postingBridge,
        protected BranchAccessServiceInterface $branchAccess,
    ) {}

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('inventory::stock_transfers.stock_transfer');
    }

    /** {@inheritDoc} */
    public function store(array $data): StockTransfer
    {
        $transfer = DB::transaction(function () use ($data): StockTransfer {
            [$source, $destination] = $this->branches($data);

            $transfer = StockTransfer::query()->create([
                'source_branch_id' => $source->id,
                'destination_branch_id' => $destination->id,
                'status' => StockTransferStatus::Draft,
                'expected_at' => Arr::get($data, 'expected_at'),
                'notes' => Arr::get($data, 'notes'),
            ]);
            $transfer->update([
                'reference_number' => sprintf('ST-%03d-%06d', $source->id, $transfer->id),
            ]);

            $this->syncItems($transfer, $data['items']);

            return $transfer;
        }, attempts: 3);

        return $this->show($transfer->id);
    }

    /**
     * Resolve source and destination branches and enforce branch ownership.
     *
     * @param  array<string, mixed>  $data
     * @return array{Branch, Branch}
     */
    private function branches(array $data, ?StockTransfer $existing = null): array
    {
        $sourceId = (int) ($data['source_branch_id'] ?? $existing?->source_branch_id);
        $destinationId = (int) ($data['destination_branch_id'] ?? $existing?->destination_branch_id);
        $user = auth()->user();

        if ($user instanceof User && $user->assignedToBranch()) {
            abort_unless($sourceId === (int) $user->branch_id, 404);
        }

        abort_if($sourceId <= 0 || $destinationId <= 0 || $sourceId === $destinationId, 422, __('inventory::messages.stock_transfer_branches_invalid'));
        $branches = Branch::query()
            ->whereIn('id', [$sourceId, $destinationId])
            ->get()
            ->keyBy('id');
        abort_unless($branches->count() === 2, 422, __('inventory::messages.stock_transfer_branches_invalid'));
        $source = $branches->get($sourceId);
        /** @var Branch $destination */
        $destination = $branches->get($destinationId);
        abort_unless($source instanceof Branch && $destination instanceof Branch, 422);
        abort_unless($source->currency === $destination->currency, 422, __('inventory::messages.stock_transfer_currency_mismatch'));

        return [$source, $destination];
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
        return StockTransfer::query()
            ->with(['sourceBranch:id,name,currency', 'destinationBranch:id,name,currency', 'createdBy:id,name'])
            ->withCount(['items', 'receipts'])
            ->filters($filters)
            ->sortBy($sorts);
    }

    /** {@inheritDoc} */
    public function update(int|string $id, array $data): StockTransfer
    {
        $transfer = DB::transaction(function () use ($id, $data): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($id);
            $this->assertSourceAccess($transfer);
            $this->assertStatus($transfer, [StockTransferStatus::Draft]);
            [$source, $destination] = $this->branches($data, $transfer);

            $transfer->update([
                'source_branch_id' => $source->id,
                'destination_branch_id' => $destination->id,
                'expected_at' => Arr::get($data, 'expected_at'),
                'notes' => Arr::get($data, 'notes'),
            ]);
            $this->syncItems($transfer, $data['items']);

            return $transfer;
        }, attempts: 3);

        return $this->show($transfer->id);
    }

    /**
     * Require an assigned user to belong to the source branch.
     */
    private function assertSourceAccess(StockTransfer $transfer): void
    {
        $this->assertAssignedBranch($transfer->source_branch_id);
    }

    /**
     * Enforce a branch-specific action while allowing authorized unassigned users.
     */
    private function assertAssignedBranch(int $branchId): void
    {
        $user = auth()->user();

        if ($user instanceof User && $user->assignedToBranch()) {
            abort_unless((int) $user->branch_id === $branchId, 404);
        }
    }

    /**
     * Ensure the transfer is in one of the supplied lifecycle states.
     *
     * @param  array<int, StockTransferStatus>  $allowed
     */
    private function assertStatus(StockTransfer $transfer, array $allowed): void
    {
        abort_unless(in_array($transfer->status, $allowed, true), 422, __('inventory::messages.stock_transfer_status_invalid'));
    }

    /**
     * Synchronize editable draft lines after validating branch ownership and stock units.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(StockTransfer $transfer, array $items): void
    {
        abort_if($items === [], 422, __('inventory::messages.stock_transfer_requires_items'));
        $pairs = collect($items)->map(
            fn (array $item) => (int) $item['source_ingredient_id'].':'.(int) $item['destination_ingredient_id'],
        );
        abort_if($pairs->unique()->count() !== $pairs->count(), 422, __('inventory::messages.stock_transfer_duplicate_item'));
        abort_if(
            collect($items)->pluck('source_ingredient_id')->map(fn ($id) => (int) $id)->duplicates()->isNotEmpty()
            || collect($items)->pluck('destination_ingredient_id')->map(fn ($id) => (int) $id)->duplicates()->isNotEmpty(),
            422,
            __('inventory::messages.stock_transfer_duplicate_item'),
        );

        $ingredientIds = collect($items)
            ->flatMap(fn (array $item) => [
                (int) $item['source_ingredient_id'],
                (int) $item['destination_ingredient_id'],
            ])
            ->unique()
            ->values();
        $ingredients = Ingredient::query()
            ->withOutGlobalBranchPermission()
            ->whereIn('id', $ingredientIds)
            ->get()
            ->keyBy('id');
        abort_unless($ingredients->count() === $ingredientIds->count(), 422, __('inventory::messages.stock_transfer_invalid_item'));

        $rows = [];
        foreach ($items as $item) {
            $source = $ingredients->get((int) $item['source_ingredient_id']);
            $destination = $ingredients->get((int) $item['destination_ingredient_id']);
            $quantity = $this->quantity($item['quantity']);

            abort_unless(
                $source
                && $destination
                && (int) $source->branch_id === $transfer->source_branch_id
                && (int) $destination->branch_id === $transfer->destination_branch_id,
                422,
                __('inventory::messages.stock_transfer_ingredient_branch_mismatch'),
            );
            abort_unless(
                (int) $source->unit_id === (int) $destination->unit_id,
                422,
                __('inventory::messages.stock_transfer_unit_mismatch'),
            );
            abort_if($quantity->isLessThanOrEqualTo(BigDecimal::zero()), 422);
            $this->assertAvailableStock($source, $quantity);

            $rows[(int) $source->id.':'.(int) $destination->id] = [
                'source_ingredient_id' => $source->id,
                'destination_ingredient_id' => $destination->id,
                'unit_id' => $source->unit_id,
                'requested_quantity' => $this->scaled($quantity),
            ];
        }

        $existingItems = $transfer->items()
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (StockTransferItem $item) => $item->source_ingredient_id.':'.$item->destination_ingredient_id);

        foreach ($rows as $key => $row) {
            /** @var StockTransferItem|null $existing */
            $existing = $existingItems->get($key);

            if ($existing) {
                $existing->fill($row);

                if ($existing->isDirty()) {
                    $existing->save();
                }

                continue;
            }

            $transfer->items()->create($row);
        }

        $removedKeys = $existingItems->keys()->diff(array_keys($rows));
        foreach ($removedKeys as $key) {
            $existingItems->get($key)?->delete();
        }
    }

    /**
     * Validate a submitted quantity against the persisted DECIMAL(18,6) contract.
     */
    private function quantity(mixed $value): BigDecimal
    {
        try {
            $quantity = $this->decimal($value);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'items' => __('inventory::messages.stock_transfer_quantity_invalid'),
            ]);
        }

        if (
            $quantity->isLessThanOrEqualTo(BigDecimal::zero())
            || $quantity->getScale() > self::QUANTITY_SCALE
            || $quantity->isGreaterThan(BigDecimal::of(self::MAX_DECIMAL_VALUE))
        ) {
            throw ValidationException::withMessages([
                'items' => __('inventory::messages.stock_transfer_quantity_invalid'),
            ]);
        }

        return $quantity;
    }

    /**
     * Normalize a persisted or submitted decimal value.
     */
    private function decimal(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) ($value ?? 0));
    }

    /**
     * Ensure the source ingredient can cover the requested transfer quantity.
     */
    private function assertAvailableStock(Ingredient $ingredient, BigDecimal $requested): void
    {
        if ($this->decimal($ingredient->getRawOriginal('current_stock'))->isLessThan($requested)) {
            throw ValidationException::withMessages([
                'items' => __('inventory::messages.stock_transfer_insufficient_stock'),
            ]);
        }
    }

    /**
     * Format a decimal using the transfer quantity and cost scale.
     */
    private function scaled(BigDecimal $value): string
    {
        if ($value->abs()->isGreaterThan(BigDecimal::of(self::MAX_DECIMAL_VALUE))) {
            throw ValidationException::withMessages([
                'items' => __('inventory::messages.stock_transfer_value_out_of_range'),
            ]);
        }

        return (string) $value->toScale(self::QUANTITY_SCALE, RoundingMode::HalfUp);
    }

    /** {@inheritDoc} */
    public function show(int|string $id): StockTransfer
    {
        return StockTransfer::query()
            ->with([
                'sourceBranch:id,name,currency',
                'destinationBranch:id,name,currency',
                'createdBy:id,name',
                'approvedBy:id,name',
                'dispatchedBy:id,name',
                'completedBy:id,name',
                'cancelledBy:id,name',
                'voidedBy:id,name',
                'items.sourceIngredient:id,branch_id,name,unit_id',
                'items.destinationIngredient:id,branch_id,name,unit_id',
                'items.unit:id,name,symbol',
                'items.outboundMovement:id,type,quantity,unit_cost,currency,currency_rate,reversal_of_id,committed_at',
                'items.outboundReversalMovement:id,type,quantity,unit_cost,currency,reversal_of_id,committed_at',
                'receipts.transfer.sourceBranch:id,currency',
                'receipts.receivedBy:id,name',
                'receipts.items.inboundMovement:id,type,quantity,unit_cost,currency,currency_rate,reversal_of_id,committed_at',
            ])
            ->withCount('receipts')
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function approve(int|string $id): StockTransfer
    {
        $changed = false;
        $transfer = DB::transaction(function () use ($id, &$changed): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($id);
            $this->assertSourceAccess($transfer);

            if ($transfer->status === StockTransferStatus::Approved) {
                return $transfer;
            }

            $this->assertStatus($transfer, [StockTransferStatus::Draft]);
            $this->validateCurrentDefinition($transfer);

            $transfer->update([
                'status' => StockTransferStatus::Approved,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            $changed = true;

            return $transfer;
        }, attempts: 3);

        if ($changed) {
            event(new StockTransferStatusChanged($transfer, StockTransferStatus::Approved));
        }

        return $this->show($transfer->id);
    }

    /**
     * Revalidate branches, currency, items and units immediately before approval/dispatch.
     *
     * @return array{Branch, Branch}
     */
    private function validateCurrentDefinition(StockTransfer $transfer): array
    {
        [$source, $destination] = $this->branches([
            'source_branch_id' => $transfer->source_branch_id,
            'destination_branch_id' => $transfer->destination_branch_id,
        ], $transfer);
        $items = StockTransferItem::query()->where('stock_transfer_id', $transfer->id)->get();
        abort_if($items->isEmpty(), 422, __('inventory::messages.stock_transfer_requires_items'));
        $ingredientIds = $items
            ->flatMap(fn (StockTransferItem $item) => [$item->source_ingredient_id, $item->destination_ingredient_id])
            ->unique();
        $ingredients = Ingredient::query()
            ->withOutGlobalBranchPermission()
            ->whereIn('id', $ingredientIds)
            ->get()
            ->keyBy('id');

        /** @var StockTransferItem $item */
        foreach ($items as $item) {
            $sourceIngredient = $ingredients->get($item->source_ingredient_id);
            $destinationIngredient = $ingredients->get($item->destination_ingredient_id);
            abort_unless(
                $sourceIngredient
                && $destinationIngredient
                && (int) $sourceIngredient->branch_id === (int) $source->id
                && (int) $destinationIngredient->branch_id === (int) $destination->id,
                422,
                __('inventory::messages.stock_transfer_ingredient_branch_mismatch'),
            );
            abort_unless(
                (int) $sourceIngredient->unit_id === (int) $item->unit_id
                && (int) $destinationIngredient->unit_id === (int) $item->unit_id,
                422,
                __('inventory::messages.stock_transfer_unit_mismatch'),
            );
            $this->assertAvailableStock($sourceIngredient, $this->decimal($item->requested_quantity));
        }

        return [$source, $destination];
    }

    /** {@inheritDoc} */
    public function dispatch(int|string $id): StockTransfer
    {
        $created = false;
        $transfer = DB::transaction(function () use ($id, &$created): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($id);
            $this->assertSourceAccess($transfer);

            if (in_array($transfer->status, [
                StockTransferStatus::Dispatched,
                StockTransferStatus::PartiallyReceived,
                StockTransferStatus::Received,
                StockTransferStatus::ClosedWithDiscrepancy,
            ], true)) {
                return $transfer;
            }

            $this->assertStatus($transfer, [StockTransferStatus::Approved]);
            [$source] = $this->validateCurrentDefinition($transfer);
            $currencyRate = FrozenCurrencyRate::for($source->currency);
            $items = StockTransferItem::query()
                ->where('stock_transfer_id', $transfer->id)
                ->orderBy('source_ingredient_id')
                ->lockForUpdate()
                ->get();
            $ingredients = Ingredient::query()
                ->withOutGlobalBranchPermission()
                ->where('branch_id', $source->id)
                ->whereIn('id', $items->pluck('source_ingredient_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var StockTransferItem $item */
            foreach ($items as $item) {
                $ingredient = $ingredients->get($item->source_ingredient_id);
                $requested = $this->decimal($item->requested_quantity);

                if (! $ingredient || $this->decimal($ingredient->getRawOriginal('current_stock'))->isLessThan($requested)) {
                    throw ValidationException::withMessages([
                        'items' => __('inventory::messages.stock_transfer_insufficient_stock'),
                    ]);
                }

                $movement = $this->stockMovements->record(new StockMovementData(
                    branchId: $source->id,
                    ingredientId: (int) $item->source_ingredient_id,
                    type: StockMovementType::TransferOut,
                    quantity: (string) $requested,
                    sourceType: StockTransferItem::class,
                    sourceId: $item->id,
                    idempotencyKey: "stock-transfer:$transfer->id:item:$item->id:out",
                    occurredAt: now(),
                    currencyRate: $currencyRate,
                    unitId: (int) $item->unit_id,
                    currency: $source->currency,
                    note: __('inventory::messages.stock_transfer_dispatch_movement_note', [
                        'reference' => $transfer->reference_number,
                    ]),
                    emitAccounting: false,
                ));
                $unitCost = $this->decimal($movement->getRawOriginal('unit_cost'));

                $item->update([
                    'dispatched_quantity' => $this->scaled($requested),
                    'unit_cost' => $this->scaled($unitCost),
                    'total_cost' => $this->scaled($requested->multipliedBy($unitCost)),
                    'outbound_movement_id' => $movement->id,
                ]);
            }

            $transfer->update([
                'status' => StockTransferStatus::Dispatched,
                'currency' => $source->currency,
                'currency_rate' => $currencyRate,
                'dispatched_by' => auth()->id(),
                'dispatched_at' => now(),
            ]);
            $created = true;

            return $transfer;
        }, attempts: 3);

        if ($created) {
            $this->recordEvent(
                sourceType: 'stock_transfer',
                sourceId: $transfer->id,
                event: 'dispatched',
                branchId: (int) $transfer->source_branch_id,
                payload: ['destination_branch_id' => $transfer->destination_branch_id],
            );
            event(new StockTransferStatusChanged($transfer, StockTransferStatus::Dispatched));
        }

        return $this->show($transfer->id);
    }

    /**
     * Record an aggregate Accounting event without rolling back committed stock.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(
        string $sourceType,
        int $sourceId,
        string $event,
        int $branchId,
        array $payload = [],
    ): void {
        $payload = [
            ...$payload,
            'branch_id' => $branchId,
            'idempotency_key' => str_replace('_', '-', "$sourceType:$sourceId:$event"),
        ];

        try {
            $this->postingBridge->recordEvent($sourceType, $sourceId, $event, $payload);
        } catch (Throwable $exception) {
            try {
                $this->postingBridge->recordFailure(
                    $sourceType,
                    $sourceId,
                    $event,
                    class_basename($exception),
                    $exception->getMessage(),
                    $payload,
                );
            } catch (Throwable $diagnosticException) {
                Log::error('Unable to record stock transfer accounting failure.', [
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'event' => $event,
                    'exception' => $exception,
                    'diagnostic_exception' => $diagnosticException,
                ]);
            }
        }
    }

    /** {@inheritDoc} */
    public function receive(int|string $id, array $data): StockTransferReceipt
    {
        $created = false;
        $submitted = collect($data['items'])
            ->mapWithKeys(fn (array $item) => [(int) $item['stock_transfer_item_id'] => $item]);
        abort_if($submitted->count() !== count($data['items']), 422, __('inventory::messages.stock_transfer_duplicate_item'));

        try {
            $receipt = DB::transaction(function () use ($id, $data, $submitted, &$created): StockTransferReceipt {
                $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($id);
                $this->assertDestinationAccess($transfer);

                $existing = StockTransferReceipt::withTrashed()
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    abort_unless((int) $existing->stock_transfer_id === (int) $transfer->id, 409);
                    $this->assertReceiptIdempotentPayloadMatches($existing, $submitted);

                    return $existing;
                }

                $this->assertStatus($transfer, [
                    StockTransferStatus::Dispatched,
                    StockTransferStatus::PartiallyReceived,
                ]);
                $this->assertTransferCurrency($transfer);

                $items = StockTransferItem::query()
                    ->where('stock_transfer_id', $transfer->id)
                    ->whereIn('id', $submitted->keys())
                    ->orderBy('destination_ingredient_id')
                    ->lockForUpdate()
                    ->get();
                abort_unless($items->count() === $submitted->count(), 422, __('inventory::messages.stock_transfer_invalid_item'));

                $receipt = StockTransferReceipt::query()->create([
                    'stock_transfer_id' => $transfer->id,
                    'idempotency_key' => $data['idempotency_key'],
                    'notes' => Arr::get($data, 'notes'),
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                ]);
                $receipt->update([
                    'reference_number' => sprintf('STR-%06d-%04d', $transfer->id, $receipt->id),
                ]);

                /** @var StockTransferItem $item */
                foreach ($items as $item) {
                    $quantity = $this->quantity($submitted->get($item->id)['quantity']);
                    $remaining = $this->decimal($item->dispatched_quantity)
                        ->minus($this->decimal($item->received_quantity));

                    if ($quantity->isLessThanOrEqualTo(BigDecimal::zero()) || $quantity->isGreaterThan($remaining)) {
                        throw ValidationException::withMessages([
                            'items' => __('inventory::messages.stock_transfer_receive_quantity_invalid'),
                        ]);
                    }

                    $destinationIngredient = Ingredient::query()
                        ->withOutGlobalBranchPermission()
                        ->where('branch_id', $transfer->destination_branch_id)
                        ->whereKey($item->destination_ingredient_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    abort_unless((int) $destinationIngredient->unit_id === (int) $item->unit_id, 422);

                    $unitCost = $this->decimal($item->unit_cost);
                    $receiptItem = StockTransferReceiptItem::query()->create([
                        'stock_transfer_receipt_id' => $receipt->id,
                        'stock_transfer_item_id' => $item->id,
                        'quantity' => $this->scaled($quantity),
                        'unit_cost' => $this->scaled($unitCost),
                        'total_cost' => $this->scaled($quantity->multipliedBy($unitCost)),
                    ]);
                    $movement = $this->stockMovements->record(new StockMovementData(
                        branchId: (int) $transfer->destination_branch_id,
                        ingredientId: (int) $item->destination_ingredient_id,
                        type: StockMovementType::TransferIn,
                        quantity: (string) $quantity,
                        sourceType: StockTransferReceiptItem::class,
                        sourceId: $receiptItem->id,
                        idempotencyKey: "stock-transfer:$transfer->id:receipt:$receipt->id:item:$item->id:in",
                        occurredAt: now(),
                        currencyRate: (string) $transfer->currency_rate,
                        unitId: (int) $item->unit_id,
                        unitCost: (string) $unitCost,
                        currency: (string) $transfer->currency,
                        note: __('inventory::messages.stock_transfer_receipt_movement_note', [
                            'reference' => $transfer->reference_number,
                        ]),
                        emitAccounting: false,
                    ));
                    $receiptItem->update(['inbound_movement_id' => $movement->id]);
                    $item->update([
                        'received_quantity' => $this->scaled(
                            $this->decimal($item->received_quantity)->plus($quantity),
                        ),
                    ]);
                }

                $hasRemaining = StockTransferItem::query()
                    ->where('stock_transfer_id', $transfer->id)
                    ->whereColumn('received_quantity', '<', 'dispatched_quantity')
                    ->exists();
                $transfer->update([
                    'status' => $hasRemaining
                        ? StockTransferStatus::PartiallyReceived
                        : StockTransferStatus::Received,
                    'completed_by' => $hasRemaining ? null : auth()->id(),
                    'completed_at' => $hasRemaining ? null : now(),
                ]);
                $created = true;

                return $receipt;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = StockTransferReceipt::withTrashed()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if (! $existing) {
                throw $exception;
            }

            $transfer = StockTransfer::query()->findOrFail($id);
            $this->assertDestinationAccess($transfer);
            abort_unless((int) $existing->stock_transfer_id === (int) $transfer->id, 409);
            $this->assertReceiptIdempotentPayloadMatches($existing, $submitted);
            $receipt = $existing;
        }

        if ($created) {
            $transfer = StockTransfer::query()
                ->withoutGlobalScope('stock_transfer_branch_permission')
                ->findOrFail($receipt->stock_transfer_id);
            $this->recordEvent(
                sourceType: 'stock_transfer_receipt',
                sourceId: $receipt->id,
                event: 'received',
                branchId: (int) $transfer->destination_branch_id,
                payload: ['stock_transfer_id' => $transfer->id],
            );
            event(new StockTransferStatusChanged($transfer, $transfer->status));
        }

        return $this->loadReceipt($receipt);
    }

    /**
     * Require an assigned user to belong to the destination branch.
     */
    private function assertDestinationAccess(StockTransfer $transfer): void
    {
        $this->assertAssignedBranch($transfer->destination_branch_id);
    }

    /**
     * Ensure an idempotent receipt retry is exactly the same logical request.
     *
     * @param  Collection<int, array<string, mixed>>  $submitted
     */
    private function assertReceiptIdempotentPayloadMatches(StockTransferReceipt $receipt, Collection $submitted): void
    {
        $receiptItems = StockTransferReceiptItem::query()
            ->where('stock_transfer_receipt_id', $receipt->id)
            ->get();

        abort_unless($receiptItems->count() === $submitted->count(), 409);

        /** @var StockTransferReceiptItem $receiptItem */
        foreach ($receiptItems as $receiptItem) {
            $submittedItem = $submitted->get($receiptItem->stock_transfer_item_id);

            abort_unless($submittedItem !== null, 409);
            abort_unless(
                $this->decimal($receiptItem->getRawOriginal('quantity'))->isEqualTo($this->decimal($submittedItem['quantity'])),
                409,
            );
        }
    }

    /**
     * Ensure both current branch currencies still match the frozen transfer currency.
     */
    private function assertTransferCurrency(StockTransfer $transfer): void
    {
        $branches = Branch::query()
            ->whereIn('id', [$transfer->source_branch_id, $transfer->destination_branch_id])
            ->get()
            ->keyBy('id');
        abort_unless(
            $branches->count() === 2
            && $branches->get($transfer->source_branch_id)?->currency === $transfer->currency
            && $branches->get($transfer->destination_branch_id)?->currency === $transfer->currency
            && $this->decimal($transfer->getRawOriginal('currency_rate'))->isGreaterThan(BigDecimal::zero()),
            422,
            __('inventory::messages.stock_transfer_currency_mismatch'),
        );
    }

    /**
     * Load the complete immutable receipt response graph.
     */
    private function loadReceipt(StockTransferReceipt $receipt): StockTransferReceipt
    {
        return $receipt->load([
            'transfer.sourceBranch:id,currency',
            'receivedBy:id,name',
            'items.inboundMovement:id,type,quantity,unit_cost,currency,currency_rate,reversal_of_id,committed_at',
        ]);
    }

    /** {@inheritDoc} */
    public function close(int|string $id, string $reason): StockTransfer
    {
        $changed = false;
        $transfer = DB::transaction(function () use ($id, $reason, &$changed): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($id);
            $this->assertDestinationAccess($transfer);

            if ($transfer->status === StockTransferStatus::ClosedWithDiscrepancy) {
                return $transfer;
            }

            $this->assertStatus($transfer, [
                StockTransferStatus::Dispatched,
                StockTransferStatus::PartiallyReceived,
            ]);
            abort_if(blank($reason), 422, __('inventory::messages.stock_transfer_reason_required'));

            $items = StockTransferItem::query()
                ->where('stock_transfer_id', $transfer->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $hasRemaining = false;

            /** @var StockTransferItem $item */
            foreach ($items as $item) {
                $remaining = $this->decimal($item->dispatched_quantity)
                    ->minus($this->decimal($item->received_quantity));

                if ($remaining->isGreaterThan(BigDecimal::zero())) {
                    $hasRemaining = true;
                    $item->update([
                        'discrepancy_quantity' => $this->scaled($remaining),
                        'discrepancy_reason' => $reason,
                    ]);
                }
            }

            abort_unless($hasRemaining, 422, __('inventory::messages.stock_transfer_no_remaining_quantity'));
            $transfer->update([
                'status' => StockTransferStatus::ClosedWithDiscrepancy,
                'completed_by' => auth()->id(),
                'completed_at' => now(),
            ]);
            $changed = true;

            return $transfer;
        }, attempts: 3);

        if ($changed) {
            $this->recordEvent(
                sourceType: 'stock_transfer',
                sourceId: $transfer->id,
                event: 'closed',
                branchId: (int) $transfer->source_branch_id,
                payload: ['reason' => $reason],
            );
            event(new StockTransferStatusChanged(
                $transfer,
                StockTransferStatus::ClosedWithDiscrepancy,
                $reason,
            ));
        }

        return $this->show($transfer->id);
    }

    /** {@inheritDoc} */
    public function cancel(int|string $id, string $reason): StockTransfer
    {
        $changed = false;
        $transfer = DB::transaction(function () use ($id, $reason, &$changed): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($id);
            $this->assertSourceAccess($transfer);

            if ($transfer->status === StockTransferStatus::Cancelled) {
                return $transfer;
            }

            $this->assertStatus($transfer, [StockTransferStatus::Draft, StockTransferStatus::Approved]);
            abort_if(blank($reason), 422, __('inventory::messages.stock_transfer_reason_required'));

            $transfer->update([
                'status' => StockTransferStatus::Cancelled,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
            $changed = true;

            return $transfer;
        }, attempts: 3);

        if ($changed) {
            event(new StockTransferStatusChanged(
                $transfer,
                StockTransferStatus::Cancelled,
                $reason,
            ));
        }

        return $this->show($transfer->id);
    }

    /** {@inheritDoc} */
    public function void(int|string $id, string $reason): StockTransfer
    {
        $changed = false;
        $transfer = DB::transaction(function () use ($id, $reason, &$changed): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($id);
            $this->assertSourceAccess($transfer);

            if ($transfer->status === StockTransferStatus::Voided) {
                return $transfer;
            }

            $this->assertStatus($transfer, [StockTransferStatus::Dispatched]);
            abort_if(blank($reason), 422, __('inventory::messages.stock_transfer_reason_required'));
            abort_if(
                StockTransferReceipt::withTrashed()
                    ->where('stock_transfer_id', $transfer->id)
                    ->exists(),
                422,
                __('inventory::messages.stock_transfer_void_after_receipt_forbidden'),
            );

            $items = StockTransferItem::query()
                ->where('stock_transfer_id', $transfer->id)
                ->with('outboundMovement')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            /** @var StockTransferItem $item */
            foreach ($items as $item) {
                abort_unless($item->outboundMovement, 422);
                $reversal = $this->stockMovements->reverse(
                    movement: $item->outboundMovement,
                    idempotencyKey: "stock-transfer:$transfer->id:item:$item->id:void",
                    reason: $reason,
                    emitAccounting: false,
                );
                $item->update(['outbound_reversal_movement_id' => $reversal->id]);
            }

            $transfer->update([
                'status' => StockTransferStatus::Voided,
                'voided_by' => auth()->id(),
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);
            $changed = true;

            return $transfer;
        }, attempts: 3);

        if ($changed) {
            $this->recordEvent(
                sourceType: 'stock_transfer',
                sourceId: $transfer->id,
                event: 'voided',
                branchId: (int) $transfer->source_branch_id,
                payload: ['reason' => $reason],
            );
            event(new StockTransferStatusChanged(
                $transfer,
                StockTransferStatus::Voided,
                $reason,
            ));
        }

        return $this->show($transfer->id);
    }

    /** {@inheritDoc} */
    public function getFormMeta(
        ?int $sourceBranchId = null,
        ?int $destinationBranchId = null,
        array $sourceIngredientIds = [],
        array $destinationIngredientIds = [],
    ): array {
        $sourceBranchId = $this->branchAccess->resolve($sourceBranchId);
        if ($destinationBranchId) {
            abort_unless($sourceBranchId, 422, __('inventory::messages.stock_transfer_branches_invalid'));
            $this->branches([
                'source_branch_id' => $sourceBranchId,
                'destination_branch_id' => $destinationBranchId,
            ]);
        }

        return [
            'branches' => Branch::list(true),
            'source_branch_id' => $sourceBranchId,
            'source_ingredients' => $this->ingredientOptions(
                $sourceBranchId,
                ids: $sourceIngredientIds,
            ),
            'destination_ingredients' => $this->ingredientOptions(
                $destinationBranchId,
                ids: $destinationIngredientIds,
            ),
        ];
    }

    /**
     * Get active ingredient options for one branch.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    private function ingredientOptions(
        ?int $branchId,
        ?string $search = null,
        array $ids = [],
        ?int $unitId = null,
    ): Collection {
        if (! $branchId) {
            return collect();
        }

        return Ingredient::list(
            branchId: $branchId,
            withoutGlobalBranchPermission: true,
            search: $search,
            limit: $ids === [] ? 25 : count($ids),
            ids: $ids,
            unitId: $unitId,
        );
    }

    /** {@inheritDoc} */
    public function getIngredientOptions(
        int $branchId,
        string $role,
        ?string $search = null,
        array $ids = [],
        ?int $unitId = null,
        ?int $sourceBranchId = null,
    ): Collection {
        abort_unless(in_array($role, ['source', 'destination'], true), 422);

        $branch = Branch::query()->findOrFail($branchId);
        $user = auth()->user();
        if ($user instanceof User && $user->assignedToBranch()) {
            $assignedBranch = Branch::query()->findOrFail((int) $user->branch_id);

            if ($role === 'source') {
                abort_unless((int) $branch->id === (int) $assignedBranch->id, 404);
            } else {
                abort_if(
                    (int) $branch->id === (int) $assignedBranch->id
                    || $branch->currency !== $assignedBranch->currency,
                    404,
                );
            }
        } elseif ($role === 'destination') {
            abort_unless($sourceBranchId, 422, __('inventory::messages.stock_transfer_branches_invalid'));
            $sourceBranch = Branch::query()->findOrFail($sourceBranchId);
            abort_if(
                (int) $branch->id === (int) $sourceBranch->id
                || $branch->currency !== $sourceBranch->currency,
                404,
            );
        }

        return $this->ingredientOptions(
            $branchId,
            search: $search,
            ids: $ids,
            unitId: $unitId,
        );
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        return [
            [
                'key' => 'source_branch_id',
                'label' => __('inventory::stock_transfers.filters.source_branch'),
                'type' => 'select',
                'options' => Branch::list(),
            ],
            [
                'key' => 'destination_branch_id',
                'label' => __('inventory::stock_transfers.filters.destination_branch'),
                'type' => 'select',
                'options' => Branch::list(),
            ],
            [
                'key' => 'status',
                'label' => __('inventory::stock_transfers.filters.status'),
                'type' => 'select',
                'options' => StockTransferStatus::toArrayTrans(),
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'stock_transfers';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.stock_transfers.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return StockTransfer::class;
    }
}
