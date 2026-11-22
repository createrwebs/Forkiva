<?php

namespace Modules\Loyalty\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Loyalty\Database\Factories\LoyaltyTransactionFactory;
use Modules\Loyalty\Enums\LoyaltyTransactionType;
use Modules\Order\Models\Order;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Translation\Traits\Translatable;

/**
 * @property int $id
 * @property int $loyalty_customer_id
 * @property LoyaltyCustomer $customer
 * @property int|null $order_id
 * @property Order $order
 * @property LoyaltyTransactionType $type
 * @property int $points
 * @property Money|null $amount
 * @property-read string|null $description
 * @property array|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class LoyaltyTransaction extends Model implements ModelExportDefinitionInterface
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
    protected array $translatable = ['program_name', 'branch_name'];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'loyalty_customer_id',
        'order_id',
        'type',
        'points',
        'amount',
        'meta',
    ];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'customer.customer.name', 'label' => 'loyalty::loyalty_transactions.table.customer', 'with' => ['customer.customer:id,name']],
            ['path' => 'order.reference_no', 'label' => 'order::orders.table.reference_no', 'with' => ['order:id,reference_no']],
            ['path' => 'description', 'label' => 'loyalty::loyalty_transactions.table.description'],
            ['path' => 'type', 'label' => 'loyalty::loyalty_transactions.table.type'],
            ['path' => 'points', 'label' => 'loyalty::loyalty_transactions.table.points'],
            ['path' => 'amount', 'label' => 'loyalty::loyalty_transactions.table.amount', 'type' => 'money'],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    protected static function newFactory(): LoyaltyTransactionFactory
    {
        return LoyaltyTransactionFactory::new();
    }

    /**
     * Get amount
     */
    public function amount(): Attribute
    {
        return Attribute::get(fn ($amount) => is_null($amount) ? null : Money::inDefaultCurrency($amount));
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
     * Get order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)
            ->withOutGlobalBranchPermission();
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'type',
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
            $query->whereHas('customer', function (Builder $query) use ($value) {
                $query->like('name', $value)
                    ->orLike('email', $value)
                    ->orLike('username', $value);
            });
        });
    }

    /**
     * Get transaction description
     *
     * @return Attribute<string|null>
     */
    public function description(): Attribute
    {
        return Attribute::get(
            fn () => isset($this->meta['description'])
                ? is_string($this->meta['description']) ? $this->meta['description'] : (__($this->meta['description']['text'], $this->meta['description']['replace'] ?? []))
                : null
        );
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'customer_id',
            'points',
            'type',
            'amount',
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
            'type' => LoyaltyTransactionType::class,
            'meta' => 'array',
            'points' => 'int',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'activity_date' => 'datetime',
        ];
    }
}
