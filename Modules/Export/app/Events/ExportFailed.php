<?php

namespace Modules\Export\Events;

use Modules\Export\Models\Export;

class ExportFailed
{
    /**
     * Create a new event instance.
     */
    public function __construct(public Export $export) {}
}
