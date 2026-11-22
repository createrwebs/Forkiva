<?php

namespace Modules\Report\Reports\Loyalty;

use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Loyalty\Enums\LoyaltyTransactionType;
use Modules\Loyalty\Models\LoyaltyCustomer;
use Modules\Loyalty\Models\LoyaltyTransaction;
use Modules\Support\Enums\DateTimeFormat;
use Modules\Support\GlobalStructureFilters;

class LoyaltyLastActivityReport extends LoyaltyReport
{
    /** {@inheritDoc} */
    public function key(): string
    {
        return 'loyalty_last_activity';
    }

    /** {@inheritDoc} */
    public function attributes(): Collection
    {
        return collect([
            'customer_name',
            'last_earned_date',
            'last_redeemed_date',
            'last_transaction_type',
        ]);
    }

    /** {@inheritDoc} */
    public function columns(): array
    {
        return [
            'loyalty_customers.customer_id',
            'customers.name as customer_name',
            "MAX(CASE WHEN loyalty_transactions.type in ('earn','bonus','adjust') THEN loyalty_transactions.created_at END) as last_earned_date",
            "MAX(CASE WHEN loyalty_transactions.type = 'redeem' THEN loyalty_transactions.created_at END) as last_redeemed_date",
            'MAX(loyalty_transactions.created_at) as last_activity_date',
        ];
    }

    /** {@inheritDoc} */
    public function resource(Model $model): array
    {
        return [
            'customer_name' => $model->customer_name,
            'last_earned_date' => $model->last_earned_date
                ? dateTimeFormat(Carbon::parse($model->last_earned_date), DateTimeFormat::Date)
                : null,
            'last_redeemed_date' => $model->last_redeemed_date
                ? dateTimeFormat(Carbon::parse($model->last_redeemed_date), DateTimeFormat::Date)
                : null,
            'last_transaction_type' => $model->last_transaction_type !== null
                ? LoyaltyTransactionType::from($model->last_transaction_type)->trans()
                : null,
        ];
    }

    /** {@inheritDoc} */
    public function through(Request $request): array
    {
        $filters = $request->input('filters', []);
        unset($filters['group_by_date']);

        // The last type must use the same membership scope as the dates, with an ID tie-breaker.
        $memberships = LoyaltyCustomer::query()->select('id')->filters($filters)
            ->whereColumn('customer_id', 'customers.id');
        $lastType = LoyaltyTransaction::query()->select('type')
            ->whereIn('loyalty_customer_id', $memberships)
            ->orderByDesc('created_at')->orderByDesc('id')->limit(1);

        return [
            fn (Builder $query, Closure $next) => $next($query)
                ->selectSub($lastType, 'last_transaction_type')
                ->join('users as customers', 'customers.id', '=', 'loyalty_customers.customer_id')
                ->leftJoin('loyalty_transactions', function ($join) {
                    $join->on('loyalty_transactions.loyalty_customer_id', '=', 'loyalty_customers.id')
                        ->whereNull('loyalty_transactions.deleted_at');
                })
                ->groupBy('loyalty_customers.customer_id', 'customers.id', 'customers.name'),
        ];
    }

    /**
     * Latest activity is one row per customer, irrespective of enrollment date buckets.
     */
    protected function supportsDateGrouping(): bool
    {
        return false;
    }

    /** {@inheritDoc} */
    public function globalFilters(): array
    {
        return [
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
            $this->programFilter(),
        ];
    }

    /** {@inheritDoc} */
    protected function resolveDefaultDateColumn(): void
    {
        $this->model()::$defaultDateColumn = 'loyalty_customers.created_at';
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return LoyaltyCustomer::class;
    }
}
