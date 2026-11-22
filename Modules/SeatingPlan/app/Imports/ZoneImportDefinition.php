<?php

namespace Modules\SeatingPlan\Imports;

class ZoneImportDefinition extends SeatingImportDefinition
{
    /** {@inheritDoc} */
    protected function resource(): string
    {
        return 'zones';
    }
}
