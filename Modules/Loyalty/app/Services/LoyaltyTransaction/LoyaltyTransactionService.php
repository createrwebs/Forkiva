<?php

namespace Modules\Loyalty\Services\LoyaltyTransaction;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\Loyalty\Enums\LoyaltyTransactionType;
use Modules\Loyalty\Models\LoyaltyTransaction;
use Modules\Support\GlobalStructureFilters;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class LoyaltyTransactionService implements LoyaltyTransactionServiceInterface
{
    /** {@inheritDoc} */
    public function label(): string
    {
        return __('loyalty::loyalty_transactions.loyalty_transaction');
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
            ->with([
                'customer.customer:id,name',
                'order:id,reference_no',
            ])
            ->filters($filters)
            ->sortBy($sorts);
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'loyalty_transactions';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.loyalty_transactions.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return LoyaltyTransaction::class;
    }

    /** {@inheritDoc} */
    public function getModel(): LoyaltyTransaction
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return LoyaltyTransaction::class;
    }

    /** {@inheritDoc} */
    public function show(int $id): LoyaltyTransaction
    {
        return $this->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function findOrFail(int $id): Builder|array|EloquentCollection|LoyaltyTransaction
    {
        return $this->getModel()
            ->query()
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        return [
            [
                'key' => 'customer_id',
                'label' => __('loyalty::loyalty_transactions.filters.customer'),
                'type' => 'select',
                'options' => User::list(defaultRole: DefaultRole::Customer),
            ],
            [
                'key' => 'type',
                'label' => __('loyalty::loyalty_transactions.filters.type'),
                'type' => 'select',
                'options' => LoyaltyTransactionType::toArrayTrans(),
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }
}
