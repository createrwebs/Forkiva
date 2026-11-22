<?php

namespace Modules\Pos\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Branch\Traits\HasBranchCurrency;
use Modules\Currency\Currency;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Payment\Models\Payment;
use Modules\Pos\Database\Factories\PosSessionFactory;
use Modules\Pos\Enums\PosCashDirection;
use Modules\Pos\Enums\PosCashReason;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Services\PosCashMovement\PosCashMovementServiceInterface;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\User\Models\User;
use Throwable;

/**
 * @property int $id
 * @property int $pos_register_id
 * @property-read PosRegister $posRegister
 * @property int $opened_by
 * @property-read User $openedBy
 * @property int|null $closed_by
 * @property-read User $closedBy
 * @property PosSessionStatus $status
 * @property Money $opening_float
 * @property Money|null $declared_cash
 * @property Money|null $system_cash_sales
 * @property Money|null $cash_over_short
 * @property Money|null $system_card_sales
 * @property Money|null $system_other_sales
 * @property Money|null $total_sales
 * @property Money|null $total_refunds
 * @property int $orders_count
 * @property array|null $meta
 * @property string $notes
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class PosSession extends Model implements ModelExportDefinitionInterface
{
    use HasActivityLog,
        HasBranch,
        HasBranchCurrency,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasSortBy,
        SoftDeletes;

    /**
     * The opener retains drawer custody; closing for someone else requires explicit authority.
     * API entry points additionally require the existing session-close permission.
     */
    public function canBeClosedBy(?User $actor): bool
    {
        return $actor !== null && (
            (int) $this->opened_by === (int) $actor->id
            || $actor->can('admin.pos_sessions.close_others')
        );
    }

    /**
     * Default date column
     */
    public static string $defaultDateColumn = 'created_at';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'pos_register_id',
        'opened_by',
        'closed_by',
        'status',
        'opening_float',
        'declared_cash',
        'system_cash_sales',
        'cash_over_short',
        'system_card_sales',
        'system_other_sales',
        'total_sales',
        'total_refunds',
        'orders_count',
        'meta',
        'notes',
        'opened_at',
        'closed_at',
        self::BRANCH_COLUMN_NAME,
    ];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'posRegister.name', 'label' => 'pos::pos_sessions.table.pos_register', 'with' => ['posRegister:id,name']],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name,currency']],
            ['path' => 'openedBy.name', 'label' => 'pos::pos_sessions.table.opened_by', 'with' => ['openedBy:id,name']],
            ['path' => 'closedBy.name', 'label' => 'pos::pos_sessions.table.closed_by', 'with' => ['closedBy:id,name']],
            ['path' => 'status', 'label' => 'pos::pos_sessions.table.status'],
            ['path' => 'opening_float', 'label' => 'pos::pos_sessions.table.opening_float', 'type' => 'money'],
            ['path' => 'declared_cash', 'label' => 'pos::pos_sessions.table.declared_cash', 'type' => 'money'],
            ['path' => 'system_cash_sales', 'label' => 'pos::pos_sessions.show.system_cash_sales', 'type' => 'money'],
            ['path' => 'cash_over_short', 'label' => 'pos::pos_sessions.show.cash_over_short', 'type' => 'money'],
            ['path' => 'system_card_sales', 'label' => 'pos::pos_sessions.show.system_card_sales', 'type' => 'money'],
            ['path' => 'system_other_sales', 'label' => 'pos::pos_sessions.show.system_other_sales', 'type' => 'money'],
            ['path' => 'total_sales', 'label' => 'pos::pos_sessions.show.total_sales', 'type' => 'money'],
            ['path' => 'total_refunds', 'label' => 'pos::pos_sessions.show.total_refunds', 'type' => 'money'],
            ['path' => 'orders_count', 'label' => 'pos::pos_sessions.show.orders_count'],
            ['path' => 'notes', 'label' => 'pos::pos_sessions.show.notes'],
            ['path' => 'opened_at', 'label' => 'pos::pos_sessions.table.opened_at', 'type' => 'datetime'],
            ['path' => 'closed_at', 'label' => 'pos::pos_sessions.table.closed_at', 'type' => 'datetime'],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
        ];
    }

    protected static function newFactory(): PosSessionFactory
    {
        return PosSessionFactory::new();
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            'status',
            'pos_register_id',
            'group_by_date',
            self::BRANCH_COLUMN_NAME,
        ];
    }

    /**
     * Get system card sales
     */
    public function systemCardSales(): Attribute
    {
        return Attribute::get(fn ($amount) => is_null($amount) ? null : new Money($amount, $this->currency));
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->whereHas('posRegister', function (Builder $query) use ($value) {
            $query->whereLikeTranslation('name', $value);
        });
    }

    /**
     * Get system wallet sales
     */
    public function systemWalletSales(): Attribute
    {
        return Attribute::get(fn ($amount) => is_null($amount) ? null : new Money($amount, $this->currency));
    }

    /**
     * Get system voucher sales
     */
    public function systemVoucherSales(): Attribute
    {
        return Attribute::get(fn ($amount) => is_null($amount) ? null : new Money($amount, $this->currency));
    }

    /**
     * Get system other sales
     */
    public function systemOtherSales(): Attribute
    {
        return Attribute::get(fn ($amount) => is_null($amount) ? null : new Money($amount, $this->currency));
    }

    /**
     * Get total sales
     */
    public function totalSales(): Attribute
    {
        return Attribute::get(fn ($amount) => is_null($amount) ? null : new Money($amount, $this->currency));
    }

    /**
     * Get opening float
     */
    public function openingFloat(): Attribute
    {
        // Display the closing snapshot's rounded component without rewriting the original opening amount.
        return Attribute::get(fn ($amount) => new Money($this->meta['cash_reconciliation']['opening_float'] ?? $amount, $this->currency));
    }

    /**
     * Get declared cash
     */
    public function declaredCash(): Attribute
    {
        return Attribute::get(fn ($amount) => is_null($amount) ? null : new Money($amount, $this->currency));
    }

    /**
     * Get cash over short
     */
    public function cashOverShort(): Attribute
    {
        return Attribute::get(fn ($amount) => is_null($amount) ? null : new Money($amount, $this->currency));
    }

    /**
     * Get total refunds
     */
    public function totalRefunds(): Attribute
    {
        return Attribute::get(fn ($amount) => is_null($amount) ? null : new Money($amount, $this->currency));
    }

    /**
     * Get system cash sales
     */
    public function systemCashSales(): Attribute
    {
        return Attribute::get(fn ($amount) => is_null($amount) ? null : new Money($amount, $this->currency));
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
     * Get opened by
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get closed by
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Determine if a session is closed
     */
    public function isClosed(): bool
    {
        return $this->status === PosSessionStatus::Closed;
    }

    /**
     * Validate counted cash and record any permitted closing adjustment.
     * The optional reconciliation is calculated by the closing service, never supplied by a client.
     *
     * @throws Throwable
     */
    public function calculateClosingSummary(?array $reconciliation = null): array
    {
        $this->loadMissing('branch');
        $reconciliation ??= $this->calculateReconciliation();
        $scale = Currency::subunit($this->currency);
        $expectedCash = BigDecimal::of($reconciliation['meta']['cash_reconciliation']['expected_cash']);
        $declaredCash = BigDecimal::of((string) ($this->getAttributes()['declared_cash'] ?? 0))
            ->toScale($scale, RoundingMode::HALF_UP);
        $cashOverShort = $declaredCash->minus($expectedCash);
        $threshold = BigDecimal::of((string) $this->branch->getRawOriginal('cash_difference_threshold'));

        if ($cashOverShort->abs()->isGreaterThan($threshold)) {
            throw new Exception(
                __('pos::messages.cannot_close_session_cash_difference', [
                    'declared' => number_format($declaredCash->toFloat(), $scale),
                    'expected' => number_format($expectedCash->toFloat(), $scale),
                    'difference' => number_format($cashOverShort->toFloat(), $scale),
                    'threshold' => number_format($threshold->toFloat(), $scale),
                ])
            );
        }

        if (! $cashOverShort->isZero()) {
            app(PosCashMovementServiceInterface::class)
                ->closingAdjust(
                    session: $this,
                    amount: (string) $cashOverShort->abs(),
                    notes: $cashOverShort->isPositive()
                        ? __('pos::messages.cash_over_short_note_over')
                        : __('pos::messages.cash_over_short_note_short')
                );
        }

        return [
            ...$reconciliation,
            'declared_cash' => (string) $declaredCash,
            'cash_over_short' => (string) $cashOverShort,
        ];
    }

    /**
     * Calculate collections and physical drawer cash without writing closing records.
     */
    public function calculateReconciliation(): array
    {
        $this->loadMissing('branch');
        $scale = Currency::subunit($this->currency);
        $attributedPayments = Payment::query()
            ->withOutGlobalBranchPermission()
            ->active()
            ->collectedInSession($this);

        $unattributedCount = Payment::query()
            ->withOutGlobalBranchPermission()
            ->active()
            ->where('branch_id', $this->branch_id)
            ->whereNull('pos_session_id')
            ->whereHas('order', fn (Builder $query) => $query
                ->where('branch_id', $this->branch_id)->where('pos_session_id', $this->id))
            ->whereNotIn('payments.id', (clone $attributedPayments)->select('payments.id'))
            ->count();

        $currencyMismatchCount = (clone $attributedPayments)->where('currency', '!=', $this->currency)->count();
        $payments = $attributedPayments->where('currency', $this->currency)->get();
        $methodTotals = [];
        foreach (PaymentMethod::cases() as $method) {
            $methodTotals[$method->value] = ['collections' => BigDecimal::zero(), 'refunds' => BigDecimal::zero()];
        }

        foreach ($payments as $payment) {
            $amount = BigDecimal::of((string) $payment->getRawOriginal('amount'));
            $kind = $payment->type === PaymentType::Refund ? 'refunds' : 'collections';
            $methodTotals[$payment->method->value][$kind] = $methodTotals[$payment->method->value][$kind]->plus($amount);
        }

        $systemOtherSales = BigDecimal::zero()->toScale($scale);
        $totalRefunds = BigDecimal::zero()->toScale($scale);
        $paymentMethods = [];

        // Round each method aggregate once, then derive net and summary totals from those same amounts.
        foreach ($methodTotals as $method => $totals) {
            $totals['collections'] = $totals['collections']->toScale($scale, RoundingMode::HALF_UP);
            $totals['refunds'] = $totals['refunds']->toScale($scale, RoundingMode::HALF_UP);
            $methodTotals[$method] = $totals;
            $totalRefunds = $totalRefunds->plus($totals['refunds']);
            if (! in_array($method, [PaymentMethod::Cash->value, PaymentMethod::Card->value], true)) {
                $systemOtherSales = $systemOtherSales->plus($totals['collections']);
            }
            $paymentMethods[] = [
                'method' => $method,
                'collections' => (string) $totals['collections'],
                'refunds' => (string) $totals['refunds'],
                'net' => (string) $totals['collections']->minus($totals['refunds']),
            ];
        }

        $systemCashSales = $methodTotals[PaymentMethod::Cash->value]['collections'];
        $systemCardSales = $methodTotals[PaymentMethod::Card->value]['collections'];
        $cashIn = BigDecimal::zero();
        $cashOut = BigDecimal::zero();
        $movements = PosCashMovement::query()
            ->withOutGlobalBranchPermission()
            ->where('branch_id', $this->branch_id)
            ->where('pos_session_id', $this->id)
            ->where('reason', '!=', PosCashReason::ClosingAdjust->value)
            ->get(['direction', 'amount']);

        // Payments and their drawer movements represent the same cash; count physical movements once.
        foreach ($movements as $movement) {
            $amount = BigDecimal::of((string) $movement->getRawOriginal('amount'));
            if ($movement->direction === PosCashDirection::In) {
                $cashIn = $cashIn->plus($amount);
            } elseif ($movement->direction === PosCashDirection::Out) {
                $cashOut = $cashOut->plus($amount);
            }
        }

        $openingFloat = BigDecimal::of((string) $this->getRawOriginal('opening_float'))
            ->toScale($scale, RoundingMode::HALF_UP);
        $cashIn = $cashIn->toScale($scale, RoundingMode::HALF_UP);
        $cashOut = $cashOut->toScale($scale, RoundingMode::HALF_UP);
        $expectedCash = $openingFloat
            ->plus($cashIn)
            ->minus($cashOut);

        return [
            'system_cash_sales' => (string) $systemCashSales,
            'system_card_sales' => (string) $systemCardSales,
            'system_other_sales' => (string) $systemOtherSales,
            'total_sales' => (string) $systemCashSales->plus($systemCardSales)->plus($systemOtherSales),
            'total_refunds' => (string) $totalRefunds,
            'orders_count' => $this->orders()->count(),
            'meta' => [
                ...($this->meta ?? []),
                'payment_reconciliation' => [
                    'version' => 2,
                    'currency' => $this->currency,
                    'methods' => $paymentMethods,
                    'legacy_cash_payments_count' => $payments->whereNull('pos_session_id')->count(),
                    'unattributed_order_payments_count' => $unattributedCount,
                    'currency_mismatch_payments_count' => $currencyMismatchCount,
                ],
                'cash_reconciliation' => [
                    'version' => 2,
                    'opening_float' => (string) $openingFloat,
                    'cash_in' => (string) $cashIn,
                    'cash_out' => (string) $cashOut,
                    'expected_cash' => (string) $expectedCash,
                ],
            ],
        ];
    }

    /**
     * Get orders
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)
            ->withOutGlobalBranchPermission();
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'status',
            'pos_register_id',
            'opening_float',
            'total_sales',
            'closed_by',
            'opened_by',
            'opened_at',
            'closed_at',
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
            'status' => PosSessionStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'meta' => 'array',
        ];
    }
}
