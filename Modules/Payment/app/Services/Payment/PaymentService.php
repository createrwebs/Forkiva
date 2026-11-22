<?php

namespace Modules\Payment\Services\Payment;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Models\Payment;
use Modules\Support\GlobalStructureFilters;

class PaymentService implements PaymentServiceInterface
{
    /** {@inheritDoc} */
    public function label(): string
    {
        return __('payment::payments.payment');
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
            ->with(['branch:id,name', 'cashier:id,name'])
            ->filters($filters)
            ->sortBy($sorts);
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'payments';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.payments.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return Payment::class;
    }

    /** {@inheritDoc} */
    public function getModel(): Payment
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Payment::class;
    }

    /** {@inheritDoc} */
    public function show(int $id): Payment
    {
        return $this->getModel()
            ->query()
            ->with(['branch:id,name', 'cashier:id,name'])
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            [
                'key' => 'method',
                'label' => __('payment::payments.filters.method'),
                'type' => 'select',
                'options' => PaymentMethod::toArrayTrans(),
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }
}
