<?php

namespace Modules\Report\Reports\Loyalty;

use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Loyalty\Models\LoyaltyCustomer;
use Modules\Support\Enums\DateTimeFormat;
use Modules\Support\GlobalStructureFilters;

class InactiveLoyaltyCustomersReport extends LoyaltyReport
{
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

    /** {@inheritDoc} */
    public function key(): string
    {
        return 'loyalty_inactive_customers';
    }

    /** {@inheritDoc} */
    public function attributes(): Collection
    {
        return collect([
            'customer_name',
            'last_activity_date',
            'days_inactive',
        ]);
    }

    /** {@inheritDoc} */
    public function columns(): array
    {
        return [
            'loyalty_customers.customer_id',
            'customers.name as customer_name',
            'MAX(activity.activity_at) as last_activity_date',
        ];
    }

    /** {@inheritDoc} */
    public function resource(Model $model): array
    {
        $lastActivity = $model->last_activity_date ? Carbon::parse($model->last_activity_date) : null;
        $daysInactive = $lastActivity?->diffInDays(now());

        return [
            'customer_name' => $model->customer_name,
            'last_activity_date' => $lastActivity ? dateTimeFormat($lastActivity, DateTimeFormat::Date) : null,
            'days_inactive' => $daysInactive !== null ? round($daysInactive, 2) : null,
        ];
    }

    /** {@inheritDoc} */
    public function through(Request $request): array
    {
        // Include imported activity summaries; COALESCE would prefer an older earn over a later redemption.
        $activity = DB::table('loyalty_transactions')
            ->select('loyalty_customer_id', 'created_at as activity_at')
            ->whereNull('deleted_at');
        foreach (['created_at', 'last_earned_at', 'last_redeemed_at'] as $column) {
            $activity->unionAll(DB::table('loyalty_customers')
                ->select('id as loyalty_customer_id', $column.' as activity_at')
                ->whereNull('deleted_at'));
        }

        return [
            fn (Builder $query, Closure $next) => $next($query)
                ->join('users as customers', 'customers.id', '=', 'loyalty_customers.customer_id')
                ->leftJoinSub($activity, 'activity', 'activity.loyalty_customer_id', '=', 'loyalty_customers.id')
                ->groupBy('loyalty_customers.customer_id', 'customers.name')
                ->havingRaw('MAX(activity.activity_at) < ?', [now()->subDays(30)]),
        ];
    }

    /**
     * Inactivity is measured per customer, not per enrollment date bucket.
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
}
