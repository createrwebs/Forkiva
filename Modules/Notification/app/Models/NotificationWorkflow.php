<?php

namespace Modules\Notification\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasOrder;
use Modules\Support\Traits\HasSortBy;
use Modules\Translation\Traits\Translatable;
use Spatie\Activitylog\LogOptions;

/**
 * @property int $id
 * @property string $key
 * @property array<string, string>|string $name
 * @property array<string, string>|string|null $description
 * @property array<string, array<string, bool>>|array<string, bool>|null $channels
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NotificationWorkflow extends Model
{
    use HasActivityLog,
        HasFilters,
        HasOrder,
        HasSortBy,
        Translatable;

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = [
        'name',
        'description',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'name',
        'description',
        'channels',
        'order',
    ];

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
        ];
    }

    /**
     * Apply a text search over key and translated metadata.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function (Builder $builder) use ($value) {
            $builder->like('key', $value)
                ->orWhereLikeTranslation('name', "%$value%")
                ->orWhereLikeTranslation('description', "%$value%");
        });
    }

    /**
     * Get the sortable attributes supported by the listing layer.
     *
     * @return array<int, string>
     */
    protected function getSortableAttributes(): array
    {
        return [
            'key',
            'name',
            'order',
        ];
    }

    /**
     * Get the options for the model's activity log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    /**
     * Get the model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'order' => 'integer',
        ];
    }
}
