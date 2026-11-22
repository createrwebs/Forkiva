<?php

namespace Modules\Order\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Order\Services\Order\OrderService;
use Modules\Order\Services\Order\OrderServiceInterface;
use Modules\Order\Services\OrderNotification\OrderNotificationService;
use Modules\Order\Services\OrderNotification\OrderNotificationServiceInterface;
use Modules\Order\Services\OrderPayment\OrderPaymentService;
use Modules\Order\Services\OrderPayment\OrderPaymentServiceInterface;
use Modules\Order\Services\Reason\ReasonService;
use Modules\Order\Services\Reason\ReasonServiceInterface;
use Modules\Order\Services\SaveOrder\SaveOrderService;
use Modules\Order\Services\SaveOrder\SaveOrderServiceInterface;

class DeferredOrderServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Boot the application events.
     */
    public function register(): void
    {
        $this->app->singleton(
            abstract: OrderServiceInterface::class,
            concrete: fn ($app) => $app->make(OrderService::class)
        );

        $this->app->scoped(
            abstract: SaveOrderServiceInterface::class,
            concrete: fn ($app) => $app->make(SaveOrderService::class)
        );

        $this->app->singleton(
            abstract: ReasonServiceInterface::class,
            concrete: fn ($app) => $app->make(ReasonService::class)
        );

        $this->app->singleton(
            abstract: OrderPaymentServiceInterface::class,
            concrete: fn ($app) => $app->make(OrderPaymentService::class)
        );

        $this->app->singleton(
            abstract: OrderNotificationServiceInterface::class,
            concrete: fn ($app) => $app->make(OrderNotificationService::class)
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            OrderServiceInterface::class,
            OrderNotificationServiceInterface::class,
            OrderPaymentServiceInterface::class,
            SaveOrderServiceInterface::class,
            ReasonServiceInterface::class,
        ];
    }
}
