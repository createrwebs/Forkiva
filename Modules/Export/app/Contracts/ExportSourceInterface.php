<?php

namespace Modules\Export\Contracts;

interface ExportSourceInterface
{
    /**
     * Export resource key used in routes and storage.
     */
    public function exportResourceKey(): string;

    /**
     * Permission required to create and access exports.
     */
    public function exportPermission(): string;
}
