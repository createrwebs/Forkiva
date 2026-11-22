<?php

namespace Modules\Pdf\Services\Driver;

use Closure;
use Modules\Pdf\Enums\PdfDriver;

class PdfDriverConfigurator
{
    /**
     * Run a callback with isolated Spatie configuration for one driver.
     *
     * @template T
     *
     * @param  array<string, mixed>  $configuration
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(PdfDriver $driver, array $configuration, Closure $callback): mixed
    {
        $key = 'laravel-pdf.'.$driver->value;
        $original = config($key, []);
        $binding = 'laravel-pdf.driver.'.$driver->value;

        config([$key => $configuration]);
        app()->forgetInstance($binding);

        try {
            return $callback();
        } finally {
            app()->forgetInstance($binding);
            config([$key => $original]);
        }
    }
}
