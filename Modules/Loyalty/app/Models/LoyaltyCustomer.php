<?php

namespace Modules\Loyalty\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Loyalty\Database\Factories\LoyaltyCustomerFactory;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Translation\Traits\Translatable;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $customer_id
 * @property User $customer
 * @property int $loyalty_program_id
 * @property LoyaltyProgram $loyaltyProgram
 * @property int|null $loyalty_tier_id
 * @property LoyaltyTier|null $loyaltyTier
 * @property int $points_balance
 * @property string $points_balance_format
 * @property int $lifetime_points
 * @property Carbon|null $last_earned_at
 * @property Carbon|null $last_redeemed_at
 * @property bool $force
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class LoyaltyCustomer extends Model implements ModelExportDefinitionInterface
{
    use HasFactory,
        HasFilters,
        HasSortBy,
        SoftDeletes,
        Translatable;

    /**
     * Default date column
     */
    public static string $defaultDateColumn = 'created_at';

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['program_name', 'tier_name'];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'loyalty_program_id',
        'loyalty_tier_id',
        'points_balance',
        'lifetime_points',
        'last_earned_at',
        'last_redeemed_at',
        'force',
    ];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'customer.name', 'label' => 'loyalty::loyalty_customers.table.customer', 'with' => ['customer:id,name']],
            ['path' => 'loyaltyProgram.name', 'label' => 'loyalty::loyalty_customers.table.loyalty_program', 'with' => ['loyaltyProgram:id,name']],
            ['path' => 'loyaltyTier.name', 'label' => 'loyalty::loyalty_customers.table.loyalty_tier', 'with' => ['loyaltyTier:id,name']],
            ['path' => 'points_balance', 'label' => 'loyalty::loyalty_customers.table.points_balance'],
            ['path' => 'lifetime_points', 'label' => 'loyalty::loyalty_customers.table.lifetime_points'],
            ['path' => 'last_earned_at', 'label' => 'loyalty::loyalty_customers.table.last_earned_at', 'type' => 'datetime'],
            ['path' => 'last_redeemed_at', 'label' => 'loyalty::loyalty_customers.table.last_redeemed_at', 'type' => 'datetime'],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    protected static function newFactory(): LoyaltyCustomerFactory
    {
        return LoyaltyCustomerFactory::new();
    }

    /**
     * Get customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')
            ->withTrashed()
            ->withoutGlobalActive();
    }

    /**
     * Get loyalty program
     */
    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class)
            ->withTrashed()
            ->withoutGlobalActive();
    }

    /**
     * Get loyalty tier
     */
    public function loyaltyTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class)
            ->with('files')
            ->withTrashed()
            ->withoutGlobalActive();
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'customer_id',
            'loyalty_program_id',
            'loyalty_tier_id',
            'from',
            'to',
            'group_by_date',
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->whereHas('customer', function (Builder $query) use ($value) {
            $query->like('name', $value)
                ->orLike('email', $value)
                ->orLike('username', $value);
        });
    }

    /**
     * Get points balance format
     */
    public function pointsBalanceFormat(): Attribute
    {
        return Attribute::get(fn () => "$this->points_balance Pts");
    }

    /**
     * Scope by loyalty program id
     */
    public function scopeLoyaltyProgramId(Builder $query, string $value): void
    {
        $query->where('loyalty_customers.loyalty_program_id', $value);
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'customer_id',
            'loyalty_program_id',
            'loyalty_tier_id',
            'points_balance',
            'lifetime_points',
            'last_earned_at',
            'last_redeemed_at',
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points_balance' => 'int',
            'lifetime_points' => 'int',
            'last_earned_at' => 'datetime',
            'last_redeemed_at' => 'datetime',
            'force' => 'boolean',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }
}
