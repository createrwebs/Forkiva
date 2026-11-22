<?php

namespace Modules\Order\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Order\Pricing\Pricing;

class PricingServiceProvider extends ServiceProvider
{
    /**
     * Register one calculator per request or worker lifecycle to prevent cross-order state reuse.
     */
    public function register(): void
    {
        $this->app->scoped(Pricing::class, fn () => new Pricing);
    }
}
