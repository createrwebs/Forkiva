<?php

namespace Modules\GiftCard\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\GiftCard\Enums\GiftCardBreakageBatchStatus;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $reference_no
 * @property GiftCardBreakageBatchStatus $status
 * @property string $currency
 * @property string $currency_rate Frozen gift-card-currency to base-currency rate.
 * @property Money $total_amount Frozen total of the selected card balances.
 * @property Carbon $economic_date
 * @property string $idempotency_key
 * @property string $review_note
 * @property array<string, mixed>|null $source_snapshot
 * @property int|null $created_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int|null $posted_by
 * @property Carbon|null $posted_at
 * @property int|null $reversal_of_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, GiftCardBreakageBatchLine> $lines
 * @property-read User|null $createdBy
 * @property-read User|null $reviewedBy
 * @property-read User|null $postedBy
 * @property-read GiftCardBreakageBatch|null $reversalOf
 * @property-read GiftCardBreakageBatch|null $reversal
 */
class GiftCardBreakageBatch extends Model implements ModelExportDefinitionInterface
{
    use HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFilters,
        HasSortBy;

    /** Use the operational breakage date for generic date-range filters and sorting. */
    public static string $defaultDateColumn = 'economic_date';

    protected $guarded = ['id'];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'reference_no', 'label' => 'giftcard::gift_card_breakage_batches.table.reference_no'],
            ['path' => 'status', 'label' => 'giftcard::gift_card_breakage_batches.table.status'],
            ['path' => 'branch.name', 'label' => 'giftcard::gift_card_breakage_batches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'total_amount', 'label' => 'giftcard::gift_card_breakage_batches.table.total_amount', 'type' => 'money'],
            ['path' => 'currency', 'label' => 'giftcard::gift_card_breakage_batches.table.currency'],
            ['path' => 'currency_rate', 'label' => 'giftcard::gift_card_breakage_batches.table.currency_rate'],
            ['path' => 'economic_date', 'label' => 'giftcard::gift_card_breakage_batches.table.economic_date', 'type' => 'date'],
            ['path' => 'review_note', 'label' => 'giftcard::gift_card_breakage_batches.review_note'],
            ['path' => 'reviewed_at', 'label' => 'giftcard::gift_card_breakage_batches.reviewed_at', 'type' => 'datetime'],
            ['path' => 'posted_at', 'label' => 'giftcard::gift_card_breakage_batches.posted_at', 'type' => 'datetime'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get the supported filter keys for the breakage-batch listing and export.
     *
     * @return array<int, string>
     */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            self::BRANCH_COLUMN_NAME,
            'status',
            'currency',
            'created_by',
            'reviewed_by',
            'posted_by',
            'reversal_of_id',
        ];
    }

    /**
     * Apply a text search over the immutable batch reference number.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->like('reference_no', $value);
    }

    /**
     * Get the frozen card-balance lines selected for this breakage batch.
     *
     * @return HasMany<GiftCardBreakageBatchLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(GiftCardBreakageBatchLine::class);
    }

    /**
     * Get the user who reviewed this batch.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get the user who posted this batch.
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get the posted batch this row reverses, when this is a reversal row.
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /**
     * Get the append-only reversal row created for this batch, when present.
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /**
     * Cast the frozen batch total into its source currency.
     *
     * @return Attribute<Money, static>
     */
    public function totalAmount(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Get the sortable attributes supported by the listing and export layers.
     *
     * @return array<int, string>
     */
    protected function getSortableAttributes(): array
    {
        return [
            'reference_no',
            'status',
            'currency',
            'currency_rate',
            'total_amount',
            'economic_date',
            'branch_id',
            'created_by',
            'reviewed_at',
            'posted_at',
        ];
    }

    /**
     * Cast monetary, lifecycle, and frozen-source attributes to their
     * application representations.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GiftCardBreakageBatchStatus::class,
            'currency_rate' => 'decimal:8',
            'economic_date' => 'date',
            'source_snapshot' => 'array',
            'reviewed_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }
}
