<?php

namespace Modules\SeatingPlan\Imports;

class TableImportDefinition extends SeatingImportDefinition
{
    /** {@inheritDoc} */
    protected function resource(): string
    {
        return 'tables';
    }
}
