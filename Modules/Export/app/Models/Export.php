<?php

namespace Modules\Export\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Export\Enums\ExportFormat;
use Modules\Export\Enums\ExportStatus;
use Modules\Support\Eloquent\Model;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $user_id
 * @property string $resource_key
 * @property string $context_key
 * @property ExportFormat $format
 * @property ExportStatus $status
 * @property array|null $filters
 * @property array|null $sorts
 * @property array|null $context
 * @property string|null $file_name
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $downloaded_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read string $download_url
 * @property-read string $format_label
 * @property-read bool $is_downloaded
 */
class Export extends Model
{
    /**
     * @var array<int,string>
     */
    protected $guarded = [];

    /**
     * Get the user that owns the export.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Download URL for completed exports.
     */
    public function downloadUrl(): Attribute
    {
        return Attribute::get(fn () => route('api.v1.exports.download', $this->id));
    }

    /**
     * Export type label.
     */
    public function formatLabel(): Attribute
    {
        return Attribute::get(fn () => $this->format->trans());
    }

    /**
     * Whether this export has already been downloaded.
     */
    public function isDownloaded(): Attribute
    {
        return Attribute::get(fn () => ! is_null($this->downloaded_at));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExportStatus::class,
            'format' => ExportFormat::class,
            'filters' => 'array',
            'sorts' => 'array',
            'context' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
