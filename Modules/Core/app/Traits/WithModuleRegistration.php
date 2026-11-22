<?php

namespace Modules\Core\Traits;

use Nwidart\Modules\Laravel\Module as LaravelModule;

trait WithModuleRegistration
{
    /**
     * Register config.
     */
    protected function registerConfig(LaravelModule $module): void
    {
        $file = $module->getPath().DIRECTORY_SEPARATOR.'config/config.php';
        if (file_exists($file)) {
            $this->mergeConfigFrom($file, $module->getLowerName());
        }
    }

    /**
     * Register services.
     */
    protected function registerServices(LaravelModule $module): void
    {
        $file = $module->getPath().DIRECTORY_SEPARATOR.'config/services.php';
        if (file_exists($file)) {
            $this->mergeConfigFrom($file, $module->getLowerName());
        }
    }

    /**
     * Register translations.
     */
    protected function registerTranslations(LaravelModule $module): void
    {
        $path = $module->getPath().DIRECTORY_SEPARATOR.'lang';
        $this->loadTranslationsFrom($path, $module->getLowerName());
        $this->loadJsonTranslationsFrom($path);
    }

    /**
     * Append Accounting export resources while the module is enabled.
     */
    private function registerExportResources(LaravelModule $module): void
    {
        $resources = config($module->getLowerName().'.export_resources', []);

        if (empty($resources)) {
            return;
        }

        config()->set('export.resources', array_merge(config('export.resources', []), $resources));
    }

    /**
     * Register domain-owned import definitions with the shared import engine.
     */
    private function registerImportResources(LaravelModule $module): void
    {
        $resources = config($module->getLowerName().'.import_resources', []);

        if ($resources !== []) {
            config()->set('import.resources', array_merge(config('import.resources', []), $resources));
        }
    }
}
