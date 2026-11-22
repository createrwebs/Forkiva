<?php

namespace Modules\Pos\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Pos\Database\Factories\PosCashMovementFactory;
use Modules\Pos\Enums\PosCashDirection;
use Modules\Pos\Enums\PosCashReason;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;

/**
 * @property int $id
 * @property int $pos_register_id
 * @property-read  PosRegister $posRegister
 * @property int $pos_session_id
 * @property-read  PosSession $posSession
 * @property int|null $order_id
 * @property-read Order|null $order
 * @property int|null $payment_id
 * @property-read Payment|null $payment
 * @property PosCashDirection $direction
 * @property PosCashReason $reason
 * @property Money $amount
 * @property string $currency
 * @property float $currency_rate
 * @property Money $balance_before
 * @property Money $balance_after
 * @property string|null $reference
 * @property string|null $notes
 * @property string|null $idempotency_key
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class PosCashMovement extends Model implements ModelExportDefinitionInterface
{
    use HasBranch,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasSortBy,
        SoftDeletes;

    /**
     * Default date column
     */
    public static string $defaultDateColumn = 'occurred_at';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'pos_register_id',
        'pos_session_id',
        'order_id',
        'payment_id',
        'direction',
        'reason',
        'amount',
        'currency',
        'currency_rate',
        'balance_before',
        'balance_after',
        'reference',
        'notes',
        'idempotency_key',
        'occurred_at',
        self::BRANCH_COLUMN_NAME,
    ];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'posRegister.name', 'label' => 'pos::pos_cash_movements.table.pos_register', 'with' => ['posRegister:id,name']],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'direction', 'label' => 'pos::pos_cash_movements.table.direction'],
            ['path' => 'reason', 'label' => 'pos::pos_cash_movements.table.reason'],
            ['path' => 'reference', 'label' => 'pos::pos_cash_movements.table.reference'],
            ['path' => 'notes', 'label' => 'pos::pos_cash_movements.table.notes'],
            ['path' => 'amount', 'label' => 'pos::pos_cash_movements.table.amount', 'type' => 'money'],
            ['path' => 'currency', 'label' => 'pos::pos_cash_movements.show.currency'],
            ['path' => 'currency_rate', 'label' => 'pos::pos_cash_movements.show.currency_rate'],
            ['path' => 'balance_before', 'label' => 'pos::pos_cash_movements.table.balance_before', 'type' => 'money'],
            ['path' => 'balance_after', 'label' => 'pos::pos_cash_movements.table.balance_after', 'type' => 'money'],
            ['path' => 'occurred_at', 'label' => 'pos::pos_cash_movements.table.occurred_at', 'type' => 'datetime'],
        ];
    }

    protected static function newFactory(): PosCashMovementFactory
    {
        return PosCashMovementFactory::new();
    }

    /**
     * Get pos register
     */
    public function posRegister(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class)
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get pos session
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class)
            ->withOutGlobalBranchPermission()
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

    /**
     * Get payment
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class)
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get amount
     */
    public function amount(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Get balance before
     */
    public function balanceBefore(): Attribute
    {
        return Attribute::get(fn ($balance) => new Money($balance, $this->currency));
    }

    /**
     * Get balance after
     */
    public function balanceAfter(): Attribute
    {
        return Attribute::get(fn ($balance) => new Money($balance, $this->currency));
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'from',
            'to',
            'status',
            'pos_register_id',
            'pos_session_id',
            'direction',
            'reason',
            'group_by_date',
            self::BRANCH_COLUMN_NAME,
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
            'direction' => PosCashDirection::class,
            'reason' => PosCashReason::class,
            'occurred_at' => 'datetime',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'pos_register_id',
            'pos_session_id',
            'direction',
            'reason',
            'amount',
            'balance_before',
            'balance_after',
            'occurred_at',
            self::BRANCH_COLUMN_NAME,
        ];
    }
}
