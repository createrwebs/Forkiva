<?php

namespace Modules\Export\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Export\Enums\ExportStatus;
use Modules\Export\Models\Export;

/** @mixin Export */
class ExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'resource_key' => $this->resource_key,
            'context_key' => $this->context_key,
            'status' => $this->status->toTrans(),
            'format' => $this->format->toTrans(),
            'file_name' => $this->file_name,
            'download_url' => $this->status === ExportStatus::Completed ? $this->download_url : null,
            'error_message' => $this->error_message,
            'created_at' => dateTimeFormat($this->created_at),
            'started_at' => dateTimeFormat($this->started_at),
            'finished_at' => dateTimeFormat($this->finished_at),
            'downloaded_at' => dateTimeFormat($this->downloaded_at),
            'is_downloaded' => $this->is_downloaded,
            'expires_at' => dateTimeFormat($this->expires_at),
        ];
    }
}
