<?php

namespace App;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Forkiva
{
    /**
     * Forkiva application version
     */
    public static string $version = '2.2.0';

    /**
     * Determine if forkiva is cache enabled
     */
    public static function cacheEnabled(): bool
    {
        return (bool) config('cache.enabled');
    }

    /**
     * Determine if forkiva is enable seed demo data
     */
    public static function seedDemoData(): bool
    {
        return (bool) config('app.seed_demo_data');
    }

    /**
     * Determine if forkiva app is installed
     */
    public static function installed(): bool
    {
        return is_file(storage_path('framework/installed.json'));
    }

    /**
     * Get paginating per page
     */
    public static function paginate(): int
    {
        try {
            $requestPerPage = max(
                request('per_page', 10),
                1
            );

            return (int) min($requestPerPage, 100);
        } catch (Exception) {
            return 10;
        }
    }

    /**
     * Check if route prefix is enabled
     */
    public static function routeDomainEnabled(): bool
    {
        return config('core.enable_route_domain', false);
    }

    /**
     * Determine if API requests all translations
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function returnAllTranslations(): bool
    {
        return in_array(request()->input('return_all_translations'), ['true', true], true);
    }
}
