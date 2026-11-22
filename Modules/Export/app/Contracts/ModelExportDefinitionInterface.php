<?php

namespace Modules\Export\Contracts;

interface ModelExportDefinitionInterface
{
    /**
     * Ordered export column definitions.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function exportColumns(): array;
}
