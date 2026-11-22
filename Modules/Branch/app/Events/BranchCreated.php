<?php

namespace Modules\Branch\Events;

use Modules\Branch\Models\Branch;

class BranchCreated
{
    /**
     * Create a branch-created domain event.
     */
    public function __construct(public Branch $branch) {}
}
