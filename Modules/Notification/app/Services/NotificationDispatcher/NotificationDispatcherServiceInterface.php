<?php

namespace Modules\Notification\Services\NotificationDispatcher;

use Illuminate\Support\Collection;
use Modules\Notification\Support\GuestNotifiable;
use Modules\User\Models\User;

interface NotificationDispatcherServiceInterface
{
    /**
     * Send a rendered template notification to one or more users.
     *
     * @param  User|GuestNotifiable|array<int, User|GuestNotifiable>|Collection<int, User|GuestNotifiable>  $notifiables
     * @param  array<string, mixed>  $variables
     */
    public function send(string $templateKey, User|GuestNotifiable|array|Collection $notifiables, array $variables = [], ?string $locale = null): void;
}
