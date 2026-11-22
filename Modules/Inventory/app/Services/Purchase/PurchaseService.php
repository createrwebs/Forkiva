<?php

namespace Modules\Inventory\Services\Purchase;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Inventory\Data\StockMovementData;
use Modules\Inventory\Enums\PurchaseStatus;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Events\PurchaseMarkedAsReceived;
use Modules\Inventory\Events\PurchaseUpdated;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\PurchaseReceipt;
use Modules\Inventory\Models\PurchaseReceiptItem;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\FrozenCurrencyRate\FrozenCurrencyRate;
use Modules\Inventory\Services\StockMovement\StockMovementServiceInterface;
use Modules\Support\GlobalStructureFilters;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class PurchaseService implements PurchaseServiceInterface
{
    /**
     * Create a purchase service instance.
     */
    public function __construct(
        protected StockMovementServiceInterface $stockMovements,
        protected BranchAccessServiceInterface $branchAccess,
    ) {}

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('inventory::purchases.purchase');
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'purchases';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.purchases.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return Purchase::class;
    }

    /** {@inheritDoc} */
    public function show(int $id, bool $withReceipt = false): Purchase
    {
        $with = ['branch', 'supplier', 'items'];

        if ($withReceipt) {
            $with = [...$with, ...['purchaseReceipts' => fn ($query) => $query->with('items', 'receivedBy')]];
        }

        return $this->getModel()
            ->query()
            ->with($with)
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function findOrFail(int $id): Builder|array|EloquentCollection|Purchase
    {
        return $this->getModel()->query()->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function getModel(): Purchase
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Purchase::class;
    }

    /** {@inheritDoc} */
    public function store(array $data): Purchase
    {
        /** @var Branch $branch */
        $branch = Branch::find($data['branch_id']);

        $purchase = $this->getModel()->query()->create([
            ...Arr::except($data, ['items']),
            'discount' => $data['discount'] ?? 0,
            'status' => PurchaseStatus::Pending,
            'currency' => $branch->currency,
            'currency_rate' => FrozenCurrencyRate::for($branch->currency),
        ]);
        $purchase->syncItems($data['items']);
        $purchase->calculateAndSyncTotals();

        return $purchase;
    }

    /** {@inheritDoc} */
    public function update(int $id, array $data): Purchase
    {
        $purchase = $this->findOrFail($id);

        abort_if(
            in_array($purchase->status, [PurchaseStatus::PartiallyReceived, PurchaseStatus::Received, PurchaseStatus::Cancelled]),
            400, __('inventory::messages.purchase_cannot_be_updated')
        );

        $items = $data['items'];
        $data = Arr::except($data, ['items']);

        $purchase->update($data);
        $purchase->syncItems($items);
        $purchase->calculateAndSyncTotals();

        event(new PurchaseUpdated(
            $purchase->fresh(['branch', 'supplier', 'items.receiptItems']) ?? $purchase
        ));

        return $purchase;
    }

    /** {@inheritDoc} */
    public function destroy(int|array|string $ids): bool
    {
        return $this->getModel()
            ->query()
            ->whereIn('status', [PurchaseStatus::Draft, PurchaseStatus::Pending])
            ->whereIn('id', parseIds($ids))->delete() ?: false;
    }

    /** {@inheritDoc} */
    public function getStructureFilters(?int $branchId = null): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            [
                'key' => 'supplier_id',
                'label' => __('inventory::purchases.filters.supplier'),
                'type' => 'select',
                'options' => ! is_null($branchId) ? Supplier::list($branchId) : [],
                'depends' => 'branch_id',
            ],
            [
                'key' => 'status',
                'label' => __('inventory::purchases.filters.status'),
                'type' => 'select',
                'options' => PurchaseStatus::toArrayTrans(),
            ],
            [
                'key' => 'expectedAt',
                'label' => __('inventory::purchases.filters.expected_at'),
                'type' => 'date',
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
            ];
        }

        return [
            'ingredients' => Ingredient::list($resolvedBranchId),
            'suppliers' => Supplier::list($resolvedBranchId),
        ];
    }

    /** {@inheritDoc}
     *
     */
    public function markAsReceived(int $id, array $data): void
    {
        DB::beginTransaction();

        try {
            /** @var Purchase $purchase */
            $purchase = $this->getModel()
                ->query()
                ->lockForUpdate()
                ->findOrFail($id);
            $purchase->setRelation('items', $purchase->items()->lockForUpdate()->get());

            abort_unless(
                in_array($purchase->status, [PurchaseStatus::Pending, PurchaseStatus::PartiallyReceived], true),
                422,
                __('inventory::messages.purchase_cannot_be_received')
            );

            /** @var PurchaseReceipt $receipt */
            $receipt = $purchase->purchaseReceipts()
                ->create([
                    'received_by' => auth()->id(),
                    'notes' => $data['notes'],
                    'reference' => $data['reference'],
                    'received_at' => now(),
                ]);

            foreach ($data['items'] as $item) {
                $purchaseItem = $purchase->items->firstWhere('id', $item['id']);
                if (! $purchaseItem || ! $purchaseItem->ingredient_id) {
                    continue;
                }

                $remainingQuantity = round((float) $purchaseItem->quantity - (float) $purchaseItem->received_quantity, 4);
                abort_if(
                    bccomp((string) $item['received_quantity'], (string) $remainingQuantity, 4) === 1,
                    422,
                    __('inventory::messages.received_quantity_exceeds', ['max' => number_format($remainingQuantity, 4)])
                );

                /** @var PurchaseReceiptItem $receiptItem */
                $receiptItem = $receipt->items()->create([
                    'purchase_item_id' => $purchaseItem->id,
                    'received_quantity' => $item['received_quantity'],
                ]);

                $this->stockMovements->record(new StockMovementData(
                    branchId: (int) $purchase->branch_id,
                    ingredientId: (int) $purchaseItem->ingredient_id,
                    type: StockMovementType::In,
                    quantity: (string) $item['received_quantity'],
                    sourceType: $receiptItem->getMorphClass(),
                    sourceId: $receiptItem->id,
                    idempotencyKey: "purchase-receipt-item:$receiptItem->id",
                    occurredAt: $receipt->received_at ?? now(),
                    currencyRate: (string) $purchaseItem->currency_rate,
                    unitCost: (string) $purchaseItem->getRawOriginal('unit_cost'),
                    currency: (string) $purchaseItem->currency,
                    note: $data['notes'] ?? null,
                    emitAccounting: false,
                ));
            }

            $purchase->recalculateReceivedQuantities();

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }

            report($exception);
            abort(400, __('inventory::messages.purchase_received_failed'));
        }

        event(new PurchaseMarkedAsReceived(
            $purchase->fresh(['branch', 'supplier', 'items.receiptItems']) ?? $purchase,
            $receipt->fresh(['items.item.ingredient']) ?? $receipt,
        ));
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
            ->with(['branch:id,name', 'supplier:id,name'])
            ->filters($filters)
            ->sortBy($sorts);
    }
}
