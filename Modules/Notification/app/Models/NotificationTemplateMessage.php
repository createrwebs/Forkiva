<?php

namespace Modules\Notification\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Notification\Enums\NotificationTemplateContentType;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Translation\Traits\Translatable;

/**
 * @property int $id
 * @property string $template_key
 * @property string $channel_key
 * @property string $recipient_role_key
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $subject
 * @property array<string, string>|string $content
 * @property NotificationTemplateContentType $content_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read NotificationTemplate|null $template
 */
class NotificationTemplateMessage extends Model
{
    public const DEFAULT_RECIPIENT_ROLE = 'default';

    use HasActivityLog,
        HasFilters,
        HasSortBy,
        Translatable;

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = [
        'title',
        'subject',
        'content',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'template_key',
        'channel_key',
        'recipient_role_key',
        'title',
        'subject',
        'content',
        'content_type',
    ];

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'template_key',
            'channel_key',
            'recipient_role_key',
            'content_type',
            'from',
            'to',
        ];
    }

    /**
     * Apply a text search over message channel and translated content.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function (Builder $builder) use ($value) {
            $builder->like('template_key', $value)
                ->orLike('channel_key', $value)
                ->orLike('recipient_role_key', $value)
                ->orWhereTranslationLike('title', "%$value%")
                ->orWhereTranslationLike('subject', "%$value%")
                ->orWhereTranslationLike('content', "%$value%");
        });
    }

    /**
     * Get the template.
     *
     * @return BelongsTo<NotificationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_key', 'key');
    }

    /**
     * Get the sortable attributes supported by the listing layer.
     *
     * @return array<int, string>
     */
    protected function getSortableAttributes(): array
    {
        return [
            'template_key',
            'channel_key',
            'recipient_role_key',
            'content_type',
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
            'content_type' => NotificationTemplateContentType::class,
        ];
    }
}
