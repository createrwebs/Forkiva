<?php

namespace Modules\Inventory\Services\InventoryCount;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Inventory\Data\StockMovementData;
use Modules\Inventory\Enums\InventoryCountStatus;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\InventoryCount;
use Modules\Inventory\Models\InventoryCountItem;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\FrozenCurrencyRate\FrozenCurrencyRate;
use Modules\Inventory\Services\StockMovement\StockMovementServiceInterface;
use Modules\Support\GlobalStructureFilters;
use Throwable;

class InventoryCountService implements InventoryCountServiceInterface
{
    /**
     * Create a new inventory count service instance.
     */
    public function __construct(
        protected FinancialPostingBridgeInterface $postingBridge,
        protected StockMovementServiceInterface $stockMovements,
        protected BranchAccessServiceInterface $branchAccess,
    ) {}

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('inventory::inventory_counts.inventory_count');
    }

    /** {@inheritDoc} */
    public function store(array $data): InventoryCount
    {
        return DB::transaction(function () use ($data) {
            $branchId = $this->resolveBranchId((int) Arr::get($data, 'branch_id'), true);
            $count = InventoryCount::query()
                ->create([
                    'branch_id' => $branchId,
                    'count_date' => $data['count_date'],
                    'notes' => Arr::get($data, 'notes'),
                    'status' => InventoryCountStatus::Draft,
                ]);

            $count->update([
                'reference_number' => sprintf('IC-%03d-%06d', $branchId, $count->id),
            ]);

            $this->syncItems($count, $data['items']);

            return $count;
        });
    }

    /**
     * Resolve and authorize the branch for the current user.
     */
    private function resolveBranchId(?int $requestedBranchId, bool $required = false): ?int
    {
        return $this->branchAccess->resolve($requestedBranchId, $required);
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
        return InventoryCount::query()
            ->withOutGlobalBranchPermission()
            ->with(['branch:id,name', 'approvedBy:id,name', 'createdBy:id,name'])
            ->withCount('items')
            ->filters($filters)
            ->sortBy($sorts);
    }

    /** {@inheritDoc} */
    public function update(int $id, array $data): InventoryCount
    {
        return DB::transaction(function () use ($id, $data) {
            $count = InventoryCount::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $this->assertDraft($count);

            $count->update([
                'count_date' => $data['count_date'],
                'notes' => Arr::get($data, 'notes'),
            ]);

            $this->syncItems($count, $data['items']);

            return $count;
        });
    }

    /**
     * Ensure the count is still editable.
     */
    private function assertDraft(InventoryCount $count): void
    {
        if ($count->status !== InventoryCountStatus::Draft) {
            throw ValidationException::withMessages(['status' => __('inventory::messages.inventory_count_not_editable')]);
        }
    }

    /**
     * Synchronize draft items using one ingredient lookup and one bulk upsert.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(InventoryCount $count, array $items): void
    {
        $ingredientIds = collect($items)
            ->pluck('ingredient_id')->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $ingredients = Ingredient::query()
            ->whereKey($ingredientIds)
            ->get(['id', 'unit_id', 'current_stock'])
            ->keyBy('id');

        if ($ingredients->count() !== $ingredientIds->count()) {
            throw ValidationException::withMessages(['items' => __('inventory::messages.inventory_count_invalid_ingredient')]);
        }

        $latestMovementIds = $this->latestMovementIds($ingredientIds->all());
        $now = now();
        $rows = collect($items)
            ->map(function (array $item) use ($count, $ingredients, $latestMovementIds, $now) {
                /** @var Ingredient $ingredient */
                $ingredient = $ingredients->get((int) $item['ingredient_id']);
                $expected = $ingredient->current_stock;
                $counted = (float) $item['counted_quantity'];

                return [
                    'branch_id' => $count->branch_id,
                    'inventory_count_id' => $count->id,
                    'ingredient_id' => $ingredient->id,
                    'unit_id' => $ingredient->unit_id,
                    'expected_quantity' => $expected,
                    'counted_quantity' => $counted,
                    'variance_quantity' => round($counted - $expected, 4),
                    'snapshot_movement_id' => $latestMovementIds[$ingredient->id] ?? null,
                    'generated_movement_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

        InventoryCountItem::query()
            ->upsert(
                $rows,
                ['inventory_count_id', 'ingredient_id'],
                ['branch_id', 'unit_id', 'expected_quantity', 'counted_quantity', 'variance_quantity', 'snapshot_movement_id', 'updated_at']
            );

        InventoryCountItem::query()
            ->where('inventory_count_id', $count->id)
            ->whereNotIn('ingredient_id', $ingredientIds)
            ->delete();
    }

    /**
     * Resolve latest stock movement IDs for the selected ingredients.
     *
     * @param  array<int, int>  $ingredientIds
     * @return array<int, int>
     */
    private function latestMovementIds(array $ingredientIds): array
    {
        return StockMovement::query()
            ->whereIn('ingredient_id', $ingredientIds)
            ->selectRaw('ingredient_id, MAX(id) AS movement_id')
            ->groupBy('ingredient_id')
            ->pluck('movement_id', 'ingredient_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** {@inheritDoc} */
    public function show(int $id): InventoryCount
    {
        return InventoryCount::query()
            ->withOutGlobalBranchPermission()
            ->with([
                'branch:id,name,currency',
                'createdBy:id,name',
                'approvedBy:id,name',
                'cancelledBy:id,name',
                'items.ingredient:id,name,unit_id,branch_id',
                'items.unit:id,name,symbol',
                'items.snapshotMovement:id',
                'items.generatedMovement:id,type,quantity',
            ])
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function approve(int $id): InventoryCount
    {
        $count = DB::transaction(function () use ($id) {
            $count = InventoryCount::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $this->assertDraft($count);

            $items = InventoryCountItem::query()
                ->where('inventory_count_id', $count->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => __('inventory::messages.inventory_count_requires_items')]);
            }

            $ingredients = Ingredient::query()
                ->whereKey($items->pluck('ingredient_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $latestMovementIds = $this->latestMovementIds($items->pluck('ingredient_id')->all());
            $branchCurrency = (string) Branch::query()->findOrFail($count->branch_id)->currency;
            $currencyRate = FrozenCurrencyRate::for($branchCurrency);

            /** @var InventoryCountItem $item */
            foreach ($items as $item) {
                /** @var Ingredient $ingredient */
                $ingredient = $ingredients->get($item->ingredient_id);
                $latestMovementId = $latestMovementIds[$item->ingredient_id] ?? null;
                if (! $ingredient
                    || $latestMovementId !== $item->snapshot_movement_id
                    || abs($ingredient->current_stock - (float) $item->expected_quantity) > 0.00005) {
                    throw ValidationException::withMessages([
                        'items' => __('inventory::messages.inventory_count_snapshot_stale'),
                    ]);
                }

                if (abs((float) $item->variance_quantity) <= 0.00005) {
                    continue;
                }

                $movement = $this->stockMovements->record(new StockMovementData(
                    branchId: (int) $count->branch_id,
                    ingredientId: (int) $item->ingredient_id,
                    type: $item->variance_quantity > 0
                        ? StockMovementType::AdjustAdd
                        : StockMovementType::AdjustSubtract,
                    quantity: abs((float) $item->variance_quantity),
                    sourceType: InventoryCountItem::class,
                    sourceId: $item->id,
                    idempotencyKey: "inventory-count:$count->id:item:$item->id:adjustment",
                    occurredAt: now(),
                    currencyRate: $currencyRate,
                    currency: $branchCurrency,
                    note: __('inventory::messages.inventory_count_adjustment_note', [
                        'reference' => $count->reference_number,
                    ]),
                    emitAccounting: false,
                ));
                $item->update(['generated_movement_id' => $movement->id]);
            }

            $count->update([
                'status' => InventoryCountStatus::Approved,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            return $count;
        });

        $this->recordApprovedEvent($count);

        return $count;
    }

    /**
     * Record the count event without allowing Accounting failures to undo stock approval.
     */
    private function recordApprovedEvent(InventoryCount $count): void
    {
        $payload = ['branch_id' => $count->branch_id, 'inventory_count_id' => $count->id];

        try {
            $this->postingBridge->recordEvent('inventory_count', $count->id, 'approved', $payload);
        } catch (Throwable $exception) {
            try {
                $this->postingBridge->recordFailure(
                    'inventory_count',
                    $count->id,
                    'approved',
                    class_basename($exception),
                    $exception->getMessage(),
                    $payload,
                );
            } catch (Throwable $diagnosticException) {
                Log::error('Unable to record inventory count accounting failure.', [
                    'inventory_count_id' => $count->id,
                    'exception' => $exception,
                    'diagnostic_exception' => $diagnosticException,
                ]);
            }
        }
    }

    /** {@inheritDoc} */
    public function cancel(int $id, ?string $reason = null): InventoryCount
    {
        return DB::transaction(function () use ($id, $reason) {
            $count = InventoryCount::query()
                ->lockForUpdate()
                ->findOrFail($id);
            $this->assertDraft($count);
            $count->update([
                'status' => InventoryCountStatus::Cancelled,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $count;
        });
    }

    /** {@inheritDoc} */
    public function destroy(int|string|array $ids): bool
    {
        $models = InventoryCount::query()
            ->whereKey(parseIds($ids))
            ->get();

        if ($models->isEmpty()) {
            return false;
        }

        foreach ($models as $count) {
            $this->assertDraft($count);
            $count->delete();
        }

        return true;
    }

    /** {@inheritDoc} */
    public function getFormMeta(?int $branchId = null): array
    {
        $resolvedBranchId = $this->resolveBranchId($branchId);

        return [
            'branches' => Branch::list(true),
            'ingredients' => $resolvedBranchId ? Ingredient::list($resolvedBranchId) : [],
        ];
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            [
                'key' => 'status',
                'label' => __('inventory::inventory_counts.filters.status'),
                'type' => 'select',
                'options' => InventoryCountStatus::toArrayTrans(),
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'inventory_counts';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.inventory_counts.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return InventoryCount::class;
    }
}
