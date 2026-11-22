<?php

namespace Modules\DailyFinance\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\DailyFinance\Services\CashHandover\CashHandoverService;
use Modules\DailyFinance\Services\CashHandover\CashHandoverServiceInterface;
use Modules\DailyFinance\Services\DailyFinance\DailyFinanceService;
use Modules\DailyFinance\Services\DailyFinance\DailyFinanceServiceInterface;
use Modules\DailyFinance\Services\DayClosing\DayClosingService;
use Modules\DailyFinance\Services\DayClosing\DayClosingServiceInterface;
use Modules\DailyFinance\Services\PaymentSettlement\PaymentSettlementService;
use Modules\DailyFinance\Services\PaymentSettlement\PaymentSettlementServiceInterface;

class DeferredDailyFinanceServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the stateless daily-finance workflows within their owning module.
     */
    public function register(): void
    {
        $this->app->singleton(
            abstract: PaymentSettlementServiceInterface::class,
            concrete: fn ($app) => $app->make(PaymentSettlementService::class)
        );
        $this->app->singleton(
            abstract: DayClosingServiceInterface::class,
            concrete: fn ($app) => $app->make(DayClosingService::class)
        );
        $this->app->singleton(
            abstract: CashHandoverServiceInterface::class,
            concrete: fn ($app) => $app->make(CashHandoverService::class)
        );
        $this->app->singleton(
            abstract: DailyFinanceServiceInterface::class,
            concrete: fn ($app) => $app->make(DailyFinanceService::class)
        );
    }

    /**
     * Declare the contract that activates this deferred provider.
     */
    public function provides(): array
    {
        return [
            DailyFinanceServiceInterface::class,
            CashHandoverServiceInterface::class,
            DayClosingServiceInterface::class,
            PaymentSettlementServiceInterface::class,
        ];
    }
}
