<?php

namespace Modules\Core\Providers;

use App\Forkiva;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\User\Exceptions\TooManyLoginAttemptsException;
use Nwidart\Modules\Facades\Module;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->routes(fn () => $this->mapModulesRoutes());
        $this->configureRateLimiting();

        Route::pattern('id', '[0-9]+');
    }

    /**
     * Map routes from all enabled modules.
     */
    private function mapModulesRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        foreach (Module::getOrdered() as $module) {
            foreach (config('core.routes') as $config) {
                $this->mapRoutes(
                    "{$module->getPath()}/routes/{$config['file']}",
                    "Modules\\{$module->getName()}\\{$config['namespace']}",
                    $config
                );
            }
        }
    }

    /**
     * Map routes.
     */
    private function mapRoutes(string $path, string $namespace, array $config): void
    {
        if (! file_exists($path)) {
            return;
        }

        $route = Route::middleware($config['middleware'])
            ->namespace($namespace);

        if (isset($config['name'])) {
            $route->name($config['name']);
        }

        if (Forkiva::routeDomainEnabled()) {
            $route->domain($config['domain']);
            if (isset($config['version'])) {
                $route->prefix($config['version']);
            }
        } else {
            if (isset($config['prefix'])) {
                $route->prefix($config['prefix']);
            }

            if (isset($config['version'])) {
                $route->prefix("{$config['prefix']}/{$config['version']}");
            }
        }

        $route->group(fn () => require $path);
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $identifier = $request->input('identifier');
            // Match AuthService normalization so casing cannot create extra attempt budgets.
            $identifier = is_string($identifier) ? Str::lower(trim($identifier)) : '';

            return Limit::perMinute(3)->by($identifier.'|'.$request->ip())
                ->response(function ($request, $headers) {
                    throw new TooManyLoginAttemptsException(
                        retryAfter: $headers['Retry-After']
                    );
                });
        });

        RateLimiter::for('passkey_login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('two_factor', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('table_qr_context', function (Request $request) {
            return Limit::perMinute(60)->by(implode('|', [
                $request->ip(),
                $request->route('uuid'),
            ]));
        });

        RateLimiter::for('table_qr_quote', function (Request $request) {
            return Limit::perMinute(30)->by(implode('|', [
                $request->ip(),
                $request->route('uuid'),
            ]));
        });

        RateLimiter::for('table_qr_submit', function (Request $request) {
            return Limit::perMinute(5)->by(implode('|', [
                $request->ip(),
                $request->route('uuid'),
                hash('sha256', (string) $request->query('token')),
            ]));
        });
    }
}
