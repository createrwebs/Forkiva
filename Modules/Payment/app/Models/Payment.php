<?php

namespace Modules\Payment\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Pos\Enums\PosCashReason;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Models\PosSession;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $order_reference_no
 * @property int $order_id
 * @property-read  Order $order
 * @property int|null $pos_session_id
 * @property-read PosSession|null $posSession
 * @property int|null $cashier_id
 * @property-read User|null $cashier
 * @property string $transaction_id
 * @property PaymentMethod $method
 * @property Money $amount
 * @property string $currency
 * @property float $currency_rate
 * @property array|null $meta
 * @property PaymentType|null $type
 * @property Carbon|null $received_at
 * @property User|null $received_by
 * @property-read Collection<int, PaymentAllocation> $allocations
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Payment extends Model implements ModelExportDefinitionInterface
{
    use HasBranch,
        HasCreatedBy,
        HasFilters,
        HasSortBy,
        SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_reference_no',
        'order_id',
        'cashier_id',
        'pos_session_id',
        'transaction_id',
        'method',
        'amount',
        'currency',
        'currency_rate',
        'meta',
        'type',
        'received_at',
        'received_by',
        'voided_at',
        'voided_by',
        'void_reason',
        self::BRANCH_COLUMN_NAME,
    ];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'order_reference_no', 'label' => 'payment::payments.table.order_reference_no'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'cashier.name', 'label' => 'payment::payments.table.cashier', 'with' => ['cashier:id,name']],
            ['path' => 'transaction_id', 'label' => 'payment::payments.show.transaction_id'],
            ['path' => 'method', 'label' => 'payment::payments.table.method'],
            ['path' => 'type', 'label' => 'payment::payments.show.type'],
            ['path' => 'amount', 'label' => 'payment::payments.table.amount', 'type' => 'money'],
            ['path' => 'currency', 'label' => 'payment::payments.show.currency'],
            ['path' => 'currency_rate', 'label' => 'payment::payments.show.currency_rate'],
            ['path' => 'createdBy.name', 'label' => 'payment::payments.show.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
        ];
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            'method',
            'type',
            'status',
            'group_by_date',
            self::BRANCH_COLUMN_NAME,
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function (Builder $query) use ($value) {
            $query->like('order_reference_no', $value)->orLike('transaction_id', $value);
        });
    }

    /**
     * Get order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)
            ->withOutGlobalBranchPermission();
    }

    /**
     * Get Pos Session
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class)
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Cash Movements
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(PosCashMovement::class)
            ->withOutGlobalBranchPermission();
    }

    /**
     * Include legacy cash only when its physical movements establish one collecting session.
     */
    public function scopeCollectedInSession(Builder $query, PosSession $session): void
    {
        $query->where('payments.branch_id', $session->branch_id)
            ->where(function (Builder $query) use ($session) {
                $query->where('payments.pos_session_id', $session->id)
                    ->orWhere(function (Builder $query) use ($session) {
                        $query->whereNull('payments.pos_session_id')
                            ->where('method', PaymentMethod::Cash->value)
                            ->whereHas('cashMovements', fn (Builder $query) => $query
                                ->where('branch_id', $session->branch_id)
                                ->where('pos_session_id', $session->id)
                                ->whereIn('reason', [PosCashReason::Sale->value, PosCashReason::Refund->value]))
                            ->whereDoesntHave('cashMovements', fn (Builder $query) => $query
                                ->whereIn('reason', [PosCashReason::Sale->value, PosCashReason::Refund->value])
                                ->where(fn (Builder $query) => $query
                                    ->where('branch_id', '!=', $session->branch_id)
                                    ->orWhere('pos_session_id', '!=', $session->id)));
                    });
            });
    }

    /**
     * Get cashier
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get received by
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get invoice allocations created for this payment.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Get the voided-by relation.
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get amount
     */
    public function amount(): Attribute
    {
        return Attribute::get(fn ($amount) => ! is_null($amount) ? new Money($amount, $this->currency) : null);
    }

    /**
     * Scope a query to get by status.
     */
    public function scopeStatus(Builder $query, string $value): void
    {
        $query->whereHas('order', function (Builder $query) use ($value) {
            $query->where('status', $value);
        });
    }

    /**
     * Scope a query to get by order type.
     */
    public function scopeType(Builder $query, string $value): void
    {
        $query->whereHas('order', function (Builder $query) use ($value) {
            $query->where('type', $value);
        });
    }

    /**
     * Scope a query to non-voided payments.
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    /**
     * Determine whether the payment was voided.
     */
    public function isVoided(): bool
    {
        return ! is_null($this->voided_at);
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
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'method' => PaymentMethod::class,
            'type' => PaymentType::class,
            'received_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'order_reference_no',
            'method',
            'amount',
            'transaction_id',
            self::BRANCH_COLUMN_NAME,
        ];
    }
}
