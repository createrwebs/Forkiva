<?php

namespace Modules\GiftCard\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;

/**
 * @property int $id
 * @property int $gift_card_breakage_batch_id
 * @property int $gift_card_id
 * @property int|null $gift_card_transaction_id
 * @property Money $amount
 * @property string $currency
 * @property string $currency_rate
 * @property array<string, mixed> $source_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GiftCardBreakageBatch $batch
 * @property-read GiftCard $giftCard
 * @property-read GiftCardTransaction|null $transaction
 */
class GiftCardBreakageBatchLine extends Model
{
    protected $guarded = ['id'];

    /**
     * Get the batch that owns this frozen balance line.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(GiftCardBreakageBatch::class, 'gift_card_breakage_batch_id')
            ->withOutGlobalBranchPermission();
    }

    /**
     * Get the expired card whose balance is recognized as breakage.
     */
    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class)
            ->withOutGlobalBranchPermission();
    }

    /**
     * Get the immutable Gift Card transaction created when the batch is posted.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(GiftCardTransaction::class, 'gift_card_transaction_id')
            ->withOutGlobalBranchPermission();
    }

    /**
     * Cast the frozen balance into the line's frozen currency.
     *
     * @return Attribute<Money, static>
     */
    public function amount(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Cast the frozen monetary amount and source evidence.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency_rate' => 'decimal:8',
            'source_snapshot' => 'array',
        ];
    }
}
