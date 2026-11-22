<?php

namespace Modules\Discount\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Discount\Services\DiscountApplyService\DiscountApplyService;
use Modules\Discount\Services\DiscountApplyService\DiscountApplyServiceInterface;

class DiscountServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DiscountApplyServiceInterface::class, DiscountApplyService::class);
    }
}
