<?php

namespace Modules\Export\Support;

use Illuminate\Contracts\Container\BindingResolutionException;
use Modules\Export\Contracts\ExportSourceInterface;

readonly class ExportSourceResolver
{
    /**
     * Resolve a registered export source.
     *
     * @throws BindingResolutionException
     */
    public function resolve(string $resource): ExportSourceInterface
    {
        $resources = config('export.resources', []);
        abort_unless(isset($resources[$resource]), 404, __('export::exports.messages.not_found'));

        $source = app()->make($resources[$resource]);
        abort_unless($source instanceof ExportSourceInterface, 404, __('export::exports.messages.not_found'));

        if (method_exists($source, 'setExportResourceKey')) {
            $source->setExportResourceKey($resource);
        }

        return $source;
    }
}
