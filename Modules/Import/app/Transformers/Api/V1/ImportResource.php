<?php

namespace Modules\Import\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Import\Enums\ImportStatus;
use Modules\Import\Models\DataImport;

/** @mixin DataImport */
class ImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requires_processing' => (bool) $this->client_processing && (bool) $this->continuation
                && in_array($this->status, [ImportStatus::Validating, ImportStatus::Queued, ImportStatus::Processing], true),
            'resource' => $this->resource_key,
            'status' => $this->status->toTrans(),
            'mode' => $this->mode->toTrans(),
            'original_name' => $this->original_name,
            'headers' => $this->headers,
            'mapping' => $this->mapping,
            'context' => $this->context,
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'valid_rows' => $this->valid_rows,
            'invalid_rows' => $this->invalid_rows,
            'new_rows' => $this->new_rows,
            'update_rows' => $this->update_rows,
            'skipped_rows' => $this->skipped_rows,
            'imported_rows' => $this->imported_rows,
            'failed_rows' => $this->failed_rows,
            'failure_reason' => $this->failure_reason,
            'has_error_file' => (bool) $this->error_path,
            'has_original_file' => (bool) $this->path,
            'created_at' => dateTimeFormat($this->created_at),
            'started_at' => dateTimeFormat($this->started_at),
            'finished_at' => dateTimeFormat($this->finished_at),
        ];
    }
}
