<?php

namespace Modules\UserPreference\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\UserPreference\Services\UserPreference\UserPreferenceService;
use Modules\UserPreference\Services\UserPreference\UserPreferenceServiceInterface;

class DeferredUserPreferenceServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(UserPreferenceServiceInterface::class, UserPreferenceService::class);
    }

    /**
     * List bindings that trigger registration of this provider.
     */
    public function provides(): array
    {
        return [
            UserPreferenceServiceInterface::class,
        ];
    }
}
