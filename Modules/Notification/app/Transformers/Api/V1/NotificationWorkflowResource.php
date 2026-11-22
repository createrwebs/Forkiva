<?php

namespace Modules\Notification\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notification\Models\NotificationWorkflow;

/** @mixin NotificationWorkflow */
class NotificationWorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'updated_at' => dateTimeFormat($this->updated_at),
        ];
    }
}
