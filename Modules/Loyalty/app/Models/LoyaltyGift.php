<?php

namespace Modules\Loyalty\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Discount\Models\Discount;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Loyalty\Enums\LoyaltyGiftStatus;
use Modules\Loyalty\Enums\LoyaltyRewardType;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Translation\Traits\Translatable;
use Modules\Voucher\Models\Voucher;

/**
 * @property int $id
 * @property int $loyalty_customer_id
 * @property-read LoyaltyCustomer $customer
 * @property int $loyalty_reward_id
 * @property-read LoyaltyReward $reward
 * @property int|null $artifact_id
 * @property string|null $artifact_type
 * @property-read Voucher|Discount|null $artifact
 * @property int $loyalty_program_id
 * @property-read LoyaltyProgram $program
 * @property LoyaltyRewardType $type
 * @property LoyaltyGiftStatus $status
 * @property int $qty
 * @property int $points_spent
 * @property array $conditions
 * @property array $meta
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property Carbon|null $used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class LoyaltyGift extends Model implements ModelExportDefinitionInterface
{
    use HasFilters, HasSortBy, SoftDeletes, Translatable;

    /**
     * Default date column
     */
    public static string $defaultDateColumn = 'loyalty_gifts.created_at';

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['reward_name', 'program_name'];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'loyalty_customer_id',
        'loyalty_program_id',
        'loyalty_reward_id',
        'artifact_id',
        'artifact_type',
        'type',
        'status',
        'qty',
        'points_spent',
        'valid_from',
        'valid_until',
        'used_at',
        'conditions',
        'meta',
    ];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'customer.customer.name', 'label' => 'loyalty::loyalty_gifts.table.customer', 'with' => ['customer.customer:id,name']],
            ['path' => 'program.name', 'label' => 'loyalty::loyalty_gifts.table.program', 'with' => ['program:id,name']],
            ['path' => 'reward.name', 'label' => 'loyalty::loyalty_gifts.table.reward', 'with' => ['reward:id,name']],
            ['path' => 'type', 'label' => 'loyalty::loyalty_gifts.table.type'],
            ['path' => 'status', 'label' => 'loyalty::loyalty_gifts.table.status'],
            ['path' => 'qty', 'label' => 'loyalty::loyalty_gifts.table.qty'],
            ['path' => 'points_spent', 'label' => 'loyalty::loyalty_gifts.table.points_spent'],
            ['path' => 'valid_from', 'label' => 'loyalty::loyalty_gifts.table.valid_from', 'type' => 'datetime'],
            ['path' => 'valid_until', 'label' => 'loyalty::loyalty_gifts.table.valid_until', 'type' => 'datetime'],
            ['path' => 'used_at', 'label' => 'loyalty::loyalty_gifts.table.used_at', 'type' => 'datetime'],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get artifact
     */
    public function artifact(): MorphTo
    {
        return $this->morphTo()
            ->withTrashed()
            ->withoutGlobalActive();
    }

    /**
     * Get loyalty customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(LoyaltyCustomer::class, 'loyalty_customer_id')
            ->withTrashed();
    }

    /**
     * Get loyalty reward
     */
    public function reward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id')
            ->withTrashed()
            ->withoutGlobalActive();
    }

    /**
     * Get loyalty program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id')
            ->withTrashed()
            ->withoutGlobalActive();
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'status',
            'customer_id',
            'loyalty_program_id',
            'loyalty_reward_id',
            'group_by_date',
            'from',
            'to',
        ];
    }

    /**
     * Scope a query to get all gift by customer id.
     */
    public function scopeCustomerId(Builder $query, int $customerId): void
    {
        $query->whereHas('customer', fn ($query) => $query->where('customer_id', $customerId));
    }

    /**
     * Scope by loyalty program id
     */
    public function scopeLoyaltyProgramId(Builder $query, string $value): void
    {
        $query->where('loyalty_gifts.loyalty_program_id', $value);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'conditions' => 'array',
            'type' => LoyaltyRewardType::class,
            'status' => LoyaltyGiftStatus::class,
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'used_at' => 'datetime',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'loyalty_program_id',
            'loyalty_reward_id',
            'status',
            'type',
            'points_spent',
            'valid_until',
            'used_at',
        ];
    }
}
