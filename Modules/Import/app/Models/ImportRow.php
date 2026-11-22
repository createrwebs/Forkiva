<?php

namespace Modules\Import\Models;

use Modules\Import\Enums\ImportOperation;
use Modules\Import\Enums\ImportRowStatus;
use Modules\Support\Eloquent\Model;

/**
 * @property int $id
 * @property int $import_id
 * @property int $row_number
 * @property array $values
 * @property array $data
 * @property array|null $errors
 * @property ImportOperation|null $operation
 * @property int|null $target_id
 * @property ImportRowStatus $status
 */
class ImportRow extends Model
{
    protected $guarded = [];

    /**
     * Cast persisted row state into the types used by import validation and processing.
     *
     * @return array<string, class-string<ImportOperation|ImportRowStatus>|string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportRowStatus::class,
            'operation' => ImportOperation::class,
            'values' => 'array',
            'data' => 'array',
            'errors' => 'array',
            'row_number' => 'integer',
            'target_id' => 'integer',
        ];
    }
}
