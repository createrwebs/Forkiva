<?php

namespace Modules\Notification\Services\NotificationPushToken;

use Modules\Notification\Models\NotificationPushToken;

interface NotificationPushTokenServiceInterface
{
    /**
     * Store or update one push token for the authenticated user.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): NotificationPushToken;

    /**
     * Remove one push token for the authenticated user.
     */
    public function destroy(int $id): bool;
}
