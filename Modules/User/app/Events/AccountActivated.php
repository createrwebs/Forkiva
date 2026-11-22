<?php

namespace Modules\User\Events;

use Carbon\Carbon;
use Modules\User\Models\User;

class AccountActivated
{
    /**
     * Create a new AccountActivated event instance.
     */
    public function __construct(
        public User $user,
        public Carbon $activatedAt,
        public string $locale,
    ) {}
}
