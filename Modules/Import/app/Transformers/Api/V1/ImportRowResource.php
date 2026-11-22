<?php

namespace Modules\Import\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Import\Models\ImportRow;

/** @mixin ImportRow */
class ImportRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'row_number' => $this->row_number,
            'values' => $this->values,
            'data' => $this->data,
            'errors' => $this->errors,
            'operation' => $this->operation?->toTrans(),
            'status' => $this->status->toTrans(),
        ];
    }
}
