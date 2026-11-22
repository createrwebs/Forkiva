<?php

namespace Modules\Core\Support;

use Nwidart\Modules\Facades\Module;

class ModuleFeature
{
    /**
     * Determine whether the given module exists and is enabled.
     */
    public static function enabled(string $name): bool
    {
        $module = Module::find($name) ?? Module::find(ucfirst($name)) ?? Module::find(strtolower($name));

        return ! is_null($module) && $module->isEnabled();
    }
}
