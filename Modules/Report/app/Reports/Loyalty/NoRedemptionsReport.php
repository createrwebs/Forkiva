<?php

namespace Modules\Report\Reports\Loyalty;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Loyalty\Models\LoyaltyCustomer;
use Modules\Loyalty\Models\LoyaltyTransaction;

class NoRedemptionsReport extends LoyaltyReport
{
    /** {@inheritDoc} */
    public function model(): string
    {
        return LoyaltyCustomer::class;
    }

    /** {@inheritDoc} */
    public function key(): string
    {
        return 'loyalty_no_redemptions';
    }

    /** {@inheritDoc} */
    public function attributes(): Collection
    {
        return collect([
            'customer_name',
            'lifetime_points',
        ]);
    }

    /** {@inheritDoc} */
    public function columns(): array
    {
        return [
            'loyalty_customers.customer_id',
            'customers.name as customer_name',
            'SUM(loyalty_customers.lifetime_points) as lifetime_points',
        ];
    }

    /** {@inheritDoc} */
    public function resource(Model $model): array
    {
        return [
            'customer_name' => $model->customer_name,
            'lifetime_points' => (int) $model->lifetime_points,
        ];
    }

    /** {@inheritDoc} */
    public function through(Request $request): array
    {
        // One redemption marker per membership prevents transactions multiplying point totals.
        $redemptions = LoyaltyTransaction::query()
            ->select('loyalty_customer_id')->where('type', 'redeem')->distinct();

        return [
            fn (Builder $query, Closure $next) => $next($query)
                ->join('users as customers', 'customers.id', '=', 'loyalty_customers.customer_id')
                ->leftJoinSub($redemptions, 'redemptions', 'redemptions.loyalty_customer_id', '=', 'loyalty_customers.id')
                ->groupBy('loyalty_customers.customer_id', 'customers.name')
                ->havingRaw('COUNT(redemptions.loyalty_customer_id) = 0'),
        ];
    }

    /**
     * Never-redeemed status applies to the customer across all selected memberships.
     */
    protected function supportsDateGrouping(): bool
    {
        return false;
    }

    /** {@inheritDoc} */
    public function globalFilters(): array
    {
        return [
            $this->programFilter(),
        ];
    }
}
