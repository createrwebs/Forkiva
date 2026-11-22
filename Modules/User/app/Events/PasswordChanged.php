<?php

namespace Modules\User\Events;

use Carbon\Carbon;
use Modules\User\Models\User;

class PasswordChanged
{
    /**
     * Create a new PasswordChanged event instance.
     */
    public function __construct(
        public User $user,
        public ?string $ipAddress,
        public Carbon $changedAt,
        public string $locale,
    ) {}
}
