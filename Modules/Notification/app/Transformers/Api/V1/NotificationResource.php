<?php

namespace Modules\Notification\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Modules\Notification\Support\NotificationPayload;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'id' => $this->id,
            ...NotificationPayload::forClient($data, locale()),
            'read_at' => dateTimeFormat($this->read_at),
            'created_at' => dateTimeFormat($this->created_at),
            'created_at_human' => $this->created_at?->locale(locale())->diffForHumans(),
            'updated_at' => dateTimeFormat($this->updated_at),
        ];
    }
}
