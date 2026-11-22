<?php

namespace Modules\SeatingPlan\Events;

use Modules\SeatingPlan\Models\Table;

class TableAssignWaiter
{
    /**
     * Create a new event instance.
     */
    public function __construct(public Table $table,
        public ?int $waiterId = null,
        public ?int $oldWaiterId = null) {}
}
