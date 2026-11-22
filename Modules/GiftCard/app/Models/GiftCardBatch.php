<?php

namespace Modules\GiftCard\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\GiftCard\Database\Factories\GiftCardBatchFactory;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\Translation\Traits\Translatable;

/**
 * Represents a batch definition used to generate multiple gift cards with a shared prefix and value.
 *
 * @property int $id
 * @property array|string $name
 * @property string|null $prefix
 * @property int $quantity
 * @property Money $value
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $cards_generated
 * @property int|null $cards_used
 * @property-read int $cards_remaining
 * @property-read Collection<int, GiftCard> $cards
 */
class GiftCardBatch extends Model implements ModelExportDefinitionInterface
{
    use HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasSortBy,
        HasTagsCache,
        SoftDeletes,
        Translatable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'prefix',
        'quantity',
        'value',
        'currency',
        'branch_id',
    ];

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['name'];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'name', 'label' => 'giftcard::gift_card_batches.table.name'],
            ['path' => 'prefix', 'label' => 'giftcard::gift_card_batches.table.prefix'],
            ['path' => 'quantity', 'label' => 'giftcard::gift_card_batches.table.quantity'],
            ['path' => 'value', 'label' => 'giftcard::gift_card_batches.table.value', 'type' => 'money'],
            ['path' => 'currency', 'label' => 'giftcard::gift_card_batches.table.currency'],
            ['path' => 'branch.name', 'label' => 'giftcard::gift_card_batches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'cards_generated', 'label' => 'giftcard::gift_card_batches.table.cards_generated'],
            ['path' => 'cards_used', 'label' => 'giftcard::gift_card_batches.table.cards_used'],
            ['path' => 'cards_remaining', 'label' => 'giftcard::gift_card_batches.table.cards_remaining'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get a cached list of gift card batches for form options.
     */
    public static function list(): Collection
    {
        return Cache::tags('gift_card_batches')
            ->rememberForever(
                makeCacheKey(['gift_card_batches', 'list']),
                fn () => static::query()
                    ->select('id', 'name')
                    ->latest()
                    ->get()
                    ->map(fn (GiftCardBatch $batch) => [
                        'id' => $batch->id,
                        'name' => $batch->name,
                    ])
            );
    }

    /**
     * Create the model factory used by tests and database seeders.
     */
    protected static function newFactory(): GiftCardBatchFactory
    {
        return GiftCardBatchFactory::new();
    }

    /**
     * Get the supported filter keys for batch listing endpoints.
     *
     * @return array<int, string>
     */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            'currency',
            self::BRANCH_COLUMN_NAME,
        ];
    }

    /**
     * Apply a text search over the batch prefix and translated name.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function (Builder $builder) use ($value) {
            $builder->like('prefix', $value)
                ->orWhereTranslationLike('name', "%$value%");
        });
    }

    /**
     * Cast the batch face value into a Money value object.
     *
     * @return Attribute<Money, static>
     */
    public function value(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Get all gift cards generated from this batch.
     *
     * @return HasMany<GiftCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(GiftCard::class, 'gift_card_batch_id');
    }

    /**
     * Derive remaining gift cards from generated and used counts.
     *
     * @return Attribute<int, never>
     */
    public function cardsRemaining(): Attribute
    {
        return Attribute::get(function () {
            $generated = ($this->cards_generated ?? 0);
            $used = ($this->cards_used ?? 0);

            return max($generated - $used, 0);
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
            'quantity',
            'value',
            'currency',
            'branch_id',
            'created_at',
        ];
    }

    /**
     * Cast model attributes to their expected scalar types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'int',
        ];
    }
}
