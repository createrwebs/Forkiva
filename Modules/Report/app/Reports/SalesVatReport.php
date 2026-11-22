<?php

namespace Modules\Report\Reports;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Invoice\Enums\InvoiceKind;
use Modules\Invoice\Models\Invoice;
use Modules\Report\Report;
use Modules\Support\Enums\DateTimeFormat;

/**
 * Thai sales VAT report (รายงานภาษีขาย): one row per issued tax invoice with
 * the buyer identity and VAT split required for monthly Revenue Department filing.
 */
class SalesVatReport extends Report
{
    /** {@inheritDoc} */
    public function key(): string
    {
        return 'sales_vat';
    }

    /** {@inheritDoc} */
    public function attributes(): Collection
    {
        return collect([
            'issued_at',
            'invoice_number',
            'invoice_kind',
            'buyer_name',
            'buyer_vat_tin',
            'buyer_branch_code',
            'subtotal',
            'tax_total',
            'total',
        ]);
    }

    /** {@inheritDoc} */
    public function columns(): array
    {
        return [
            'invoices.id',
            'invoices.buyer_party_id',
            'invoices.issued_at',
            'invoices.invoice_number',
            'invoices.invoice_kind',
            'invoices.currency',
            'invoices.subtotal',
            'invoices.tax_total',
            'invoices.total',
        ];
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Invoice::class;
    }

    /** {@inheritDoc} */
    public function resource(Model $model): array
    {
        return [
            'issued_at' => dateTimeFormat($model->issued_at, DateTimeFormat::Date),
            'invoice_number' => $model->invoice_number,
            'invoice_kind' => $model->invoice_kind->trans(),
            'buyer_name' => $model->buyer?->legal_name,
            'buyer_vat_tin' => $model->buyer?->vat_tin,
            'buyer_branch_code' => $model->buyer?->tax_branch_code,
            'subtotal' => $model->subtotal,
            'tax_total' => $model->tax_total,
            'total' => $model->total,
        ];
    }

    /** {@inheritDoc} */
    public function through(Request $request): array
    {
        return [
            fn (Builder $query, Closure $next) => $next($query)
                ->when($this->user->assignedToBranch(), fn (Builder $query) => $query->whereBranch($this->user->branch_id))
                ->issued(),
        ];
    }

    /** {@inheritDoc} */
    public function filters(Request $request): array
    {
        return [
            [
                'key' => 'invoice_kind',
                'label' => __('report::reports.filters.invoice_kind'),
                'type' => 'select',
                'options' => InvoiceKind::toArrayTrans(),
            ],
        ];
    }

    /** {@inheritDoc} */
    public function with(): array
    {
        return ['buyer' => fn ($query) => $query->select('id', 'legal_name', 'vat_tin', 'tax_branch_code')];
    }

    /** {@inheritDoc} */
    public function hasSearch(): bool
    {
        return true;
    }
}
