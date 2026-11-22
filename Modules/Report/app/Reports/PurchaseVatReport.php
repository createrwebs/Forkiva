<?php

namespace Modules\Report\Reports;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Inventory\Enums\PurchaseStatus;
use Modules\Inventory\Models\Purchase;
use Modules\Report\Report;
use Modules\Support\Enums\DateTimeFormat;

/**
 * Thai purchase VAT report (รายงานภาษีซื้อ): one row per purchase with the
 * supplier tax identity and input VAT, ready for monthly Revenue Department filing.
 */
class PurchaseVatReport extends Report
{
    /** {@inheritDoc} */
    public function key(): string
    {
        return 'purchase_vat';
    }

    /** {@inheritDoc} */
    public function attributes(): Collection
    {
        return collect([
            'purchase_date',
            'reference_number',
            'supplier_name',
            'supplier_vat_tin',
            'supplier_branch_code',
            'sub_total',
            'tax',
            'total',
        ]);
    }

    /** {@inheritDoc} */
    public function columns(): array
    {
        return [
            'purchases.id',
            'purchases.supplier_id',
            'purchases.created_at',
            'purchases.reference_number',
            'purchases.currency',
            'purchases.sub_total',
            'purchases.tax',
            'purchases.total',
        ];
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Purchase::class;
    }

    /** {@inheritDoc} */
    public function resource(Model $model): array
    {
        return [
            'purchase_date' => dateTimeFormat($model->created_at, DateTimeFormat::Date),
            'reference_number' => $model->reference_number,
            'supplier_name' => $model->supplier?->name,
            'supplier_vat_tin' => $model->supplier?->vat_tin,
            'supplier_branch_code' => $model->supplier?->tax_branch_code,
            'sub_total' => $model->sub_total,
            'tax' => $model->tax,
            'total' => $model->total,
        ];
    }

    /** {@inheritDoc} */
    public function through(Request $request): array
    {
        return [
            fn (Builder $query, Closure $next) => $next($query)
                ->when($this->user->assignedToBranch(), fn (Builder $query) => $query->whereBranch($this->user->branch_id))
                ->whereNotIn('status', [PurchaseStatus::Draft, PurchaseStatus::Cancelled]),
        ];
    }

    /** {@inheritDoc} */
    public function filters(Request $request): array
    {
        return [
            [
                'key' => 'status',
                'label' => __('report::reports.filters.purchase_status'),
                'type' => 'select',
                'options' => PurchaseStatus::toArrayTrans(),
            ],
        ];
    }

    /** {@inheritDoc} */
    public function with(): array
    {
        return ['supplier' => fn ($query) => $query->select('id', 'name', 'vat_tin', 'tax_branch_code')];
    }

    /** {@inheritDoc} */
    public function hasSearch(): bool
    {
        return true;
    }
}
