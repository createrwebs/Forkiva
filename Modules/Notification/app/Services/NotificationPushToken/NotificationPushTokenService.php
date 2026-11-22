<?php

namespace Modules\Notification\Services\NotificationPushToken;

use Modules\Notification\Enums\NotificationPushTokenClient;
use Modules\Notification\Models\NotificationPushToken;

class NotificationPushTokenService implements NotificationPushTokenServiceInterface
{
    /** {@inheritDoc} */
    public function store(array $data): NotificationPushToken
    {
        $token = NotificationPushToken::query()
            ->updateOrCreate(
                [
                    'provider' => $data['provider'],
                    'token' => $data['token'],
                ],
                [
                    'user_id' => auth()->id(),
                    'device_id' => $data['device_id'] ?? null,
                    'device_name' => $data['device_name'] ?? null,
                    'platform' => $data['platform'] ?? null,
                    'client' => $data['client'] ?? NotificationPushTokenClient::Web->value,
                    'last_used_at' => now(),
                ]
            );

        return $token->refresh();
    }

    /** {@inheritDoc} */
    public function destroy(int $id): bool
    {
        return NotificationPushToken::query()
            ->where('user_id', auth()->id())
            ->where('id', $id)
            ->delete() > 0;
    }
}
