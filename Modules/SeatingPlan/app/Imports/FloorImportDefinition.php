<?php

namespace Modules\SeatingPlan\Imports;

class FloorImportDefinition extends SeatingImportDefinition
{
    /** {@inheritDoc} */
    protected function resource(): string
    {
        return 'floors';
    }
}
