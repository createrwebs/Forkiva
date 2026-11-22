<?php

namespace Modules\User\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Passkeys\Passkey;

/** @mixin Passkey */
class PasskeyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'authenticator' => $this->authenticator,
            'last_used_at' => dateTimeFormat($this->last_used_at),
            'created_at' => dateTimeFormat($this->created_at),
        ];
    }
}
