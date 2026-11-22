<?php

namespace Modules\Notification\Http\Requests\Api\V1;

use Illuminate\Validation\Rules\Enum;
use Modules\Core\Http\Requests\Request;
use Modules\Notification\Enums\NotificationPushTokenClient;
use Modules\Service\Enums\PushNotificationProvider;

class StoreNotificationPushTokenRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', new Enum(PushNotificationProvider::class)],
            'token' => ['required', 'string', 'max:512'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'client' => ['nullable', new Enum(NotificationPushTokenClient::class)],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'notification::attributes.notification_push_tokens';
    }
}
