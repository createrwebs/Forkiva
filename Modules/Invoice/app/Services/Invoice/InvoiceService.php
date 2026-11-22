<?php

namespace Modules\Invoice\Services\Invoice;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Invoice\Enums\InvoiceKind;
use Modules\Invoice\Enums\InvoicePurpose;
use Modules\Invoice\Enums\InvoiceStatus;
use Modules\Invoice\Enums\InvoiceType;
use Modules\Invoice\Models\Invoice;
use Modules\Support\GlobalStructureFilters;

class InvoiceService implements InvoiceServiceInterface
{
    /** {@inheritDoc} */
    public function label(): string
    {
        return __('invoice::invoices.invoice');
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
            ->filters($filters)
            ->sortBy($sorts)
            ->with([
                'seller:id,legal_name',
                'buyer:id,legal_name',
                'branch:id,name',
                'referenceInvoice:id,invoice_number,uuid',
            ]);
    }

    /** {@inheritDoc} */
    public function getModel(): Invoice
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Invoice::class;
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'invoices';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.invoices.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return Invoice::class;
    }

    /** {@inheritDoc} */
    public function show(int|string $id): Invoice
    {
        $isUuid = str($id)->isUuid();
        $numericId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return Invoice::query()
            ->where(
                fn ($query) => $query
                    ->when($numericId !== false && ! $isUuid, fn ($query) => $query->where('id', $numericId))
                    ->when($isUuid, fn ($query) => $query->where('uuid', $id))
                    ->when(! $isUuid && $numericId === false, fn ($query) => $query->where('invoice_number', (string) $id))
            )
            ->with([
                'seller',
                'buyer',
                'discounts',
                'taxes',
                'allTaxes',
                'allocations' => fn ($query) => $query->with(['payment']),
                'lines.taxes',
                'branch:id,name',
                'referenceInvoice:id,invoice_number,uuid',
            ])
            ->firstOrFail();
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            [
                'key' => 'status',
                'label' => __('invoice::invoices.filters.status'),
                'type' => 'select',
                'options' => InvoiceStatus::toArrayTrans(),
            ],
            [
                'key' => 'type',
                'label' => __('invoice::invoices.filters.type'),
                'type' => 'select',
                'options' => InvoiceType::toArrayTrans(),
            ],
            [
                'key' => 'purpose',
                'label' => __('invoice::invoices.filters.purpose'),
                'type' => 'select',
                'options' => InvoicePurpose::toArrayTrans(),
            ],
            [
                'key' => 'invoice_kind',
                'label' => __('invoice::invoices.filters.invoice_kind'),
                'type' => 'select',
                'options' => InvoiceKind::toArrayTrans(),
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }
}
