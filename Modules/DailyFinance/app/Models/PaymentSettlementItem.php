<?php

namespace Modules\DailyFinance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Modules\Payment\Models\Payment;
use Modules\Support\Eloquent\Model;

/**
 * @property int $id
 * @property int $payment_settlement_id
 * @property int $payment_id
 * @property int|null $active_payment_id
 * @property string $amount
 * @property string $order_reference_no
 * @property Carbon $collected_at
 * @property-read PaymentSettlement $settlement
 * @property-read Payment $payment
 */
class PaymentSettlementItem extends Model
{
    public $timestamps = false;

    protected $table = 'daily_finance_payment_settlement_items';

    protected $fillable = [
        'payment_id',
        'active_payment_id',
        'amount',
        'order_reference_no',
        'collected_at',
    ];

    /**
     * Cancellation may release a claim, but cannot rewrite the approved collection evidence.
     */
    protected static function booted(): void
    {
        static::updating(function (self $item): void {
            if (array_diff(array_keys($item->getDirty()), ['active_payment_id']) !== []) {
                throw new LogicException('Settlement collection snapshots are immutable.');
            }
            if ($item->isDirty('active_payment_id') && $item->active_payment_id !== null) {
                throw new LogicException('Settlement collection claims may only be released.');
            }
        });
        static::deleting(fn () => throw new LogicException('Settlement collection history cannot be deleted.'));
    }

    /**
     * Resolve the batch that retains this source evidence.
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(PaymentSettlement::class, 'payment_settlement_id')
            ->withOutGlobalBranchPermission();
    }

    /**
     * Retain access to the original payment, including later soft deletion.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class)
            ->withTrashed()
            ->withOutGlobalBranchPermission();
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'collected_at' => 'datetime',
        ];
    }
}
