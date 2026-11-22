<?php

namespace Modules\Notification\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notification\Models\NotificationPushToken;

/** @mixin NotificationPushToken */
class NotificationPushTokenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider?->value,
            'token' => $this->token,
            'device_id' => $this->device_id,
            'device_name' => $this->device_name,
            'platform' => $this->platform,
            'client' => $this->client?->value,
            'last_used_at' => dateTimeFormat($this->last_used_at),
            'created_at' => dateTimeFormat($this->created_at),
            'updated_at' => dateTimeFormat($this->updated_at),
        ];
    }
}
