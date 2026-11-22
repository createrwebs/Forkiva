<?php

namespace Modules\Notification\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Translation\Traits\Translatable;

/**
 * @property int $id
 * @property string $key
 * @property array<string, string>|string $name
 * @property array<string, string>|string|null $description
 * @property array<int, string>|null $supported_variables
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, NotificationTemplateMessage> $messages
 */
class NotificationTemplate extends Model
{
    use HasActivityLog,
        HasFilters,
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
        'supported_variables',
    ];

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'channel_key',
            'content_type',
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
            $builder->like('key', "%$value%")
                ->orWhereLikeTranslation('name', "%$value%")
                ->orWhereLikeTranslation('description', "%$value%");
        });
    }

    /**
     * Filter templates by a related channel key.
     */
    public function scopeChannelKey(Builder $query, string $value): void
    {
        $query->whereHas('messages', function (Builder $builder) use ($value) {
            $builder->where('channel_key', $value);
        });
    }

    /**
     * Filter templates by a related content type.
     */
    public function scopeContentType(Builder $query, string $value): void
    {
        $query->whereHas('messages', function (Builder $builder) use ($value) {
            $builder->where('content_type', $value);
        });
    }

    /**
     * Get the template messages.
     *
     * @return HasMany<NotificationTemplateMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(NotificationTemplateMessage::class, 'template_key', 'key');
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'key',
            'name',
        ];
    }

    /**
     * Get the model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supported_variables' => 'array',
        ];
    }
}
