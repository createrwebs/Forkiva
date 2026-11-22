<?php

namespace Modules\Import\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportStatus;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int|null $created_by
 * @property string $resource_key
 * @property string $locale
 * @property ImportStatus $status
 * @property ImportMode $mode
 * @property array|null $context
 * @property array $headers
 * @property array|null $mapping
 * @property int $revision
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $valid_rows
 * @property int $invalid_rows
 * @property int $new_rows
 * @property int $update_rows
 * @property int $skipped_rows
 * @property int $imported_rows
 * @property int $failed_rows
 * @property string $error_path
 * @property string $path
 * @property string $disk
 * @property string $original_name
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property-read User|null $createdBy
 */
class DataImport extends Model
{
    use HasCreatedBy;

    protected $table = 'imports';

    protected $guarded = [];

    /**
     * Return the staged rows that belong to this import workflow.
     *
     * @return HasMany<ImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'import_id');
    }

    /**
     * Reject work queued for an older import revision or a different phase.
     */
    public function isAtRevision(ImportStatus $status, int $revision): bool
    {
        return $this->status === $status && $this->revision === $revision;
    }

    /**
     * Cast persisted workflow state into the types used by import jobs and services.
     *
     * @return array<string, class-string<ImportMode|ImportStatus>|string>
     */
    protected function casts(): array
    {
        return [
            'client_processing' => 'boolean',
            'continuation' => 'array',
            'status' => ImportStatus::class,
            'mode' => ImportMode::class,
            'context' => 'array',
            'headers' => 'array',
            'mapping' => 'array',
            'revision' => 'integer',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
            'new_rows' => 'integer',
            'update_rows' => 'integer',
            'skipped_rows' => 'integer',
            'imported_rows' => 'integer',
            'failed_rows' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
