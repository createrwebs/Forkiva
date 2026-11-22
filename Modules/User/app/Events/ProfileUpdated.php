<?php

namespace Modules\User\Events;

use Carbon\Carbon;
use Modules\User\Models\User;

class ProfileUpdated
{
    /**
     * Create a new ProfileUpdated event instance.
     *
     * @param  array<int, string>  $changedFields
     */
    public function __construct(
        public User $user,
        public array $changedFields,
        public ?string $ipAddress,
        public Carbon $changedAt,
        public string $locale,
    ) {}
}
