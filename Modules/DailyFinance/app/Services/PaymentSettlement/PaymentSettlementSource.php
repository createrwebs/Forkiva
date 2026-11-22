<?php

namespace Modules\DailyFinance\Services\PaymentSettlement;

use Brick\Math\BigDecimal;
use Modules\Payment\Models\Payment;

class PaymentSettlementSource
{
    /**
     * Bind the reviewed source amount and collection date without using display formatting.
     */
    public static function hash(Payment $payment): string
    {
        return hash('sha256', json_encode([
            $payment->id,
            $payment->branch_id,
            $payment->currency,
            $payment->method->value,
            $payment->type?->value,
            (string) BigDecimal::of((string) $payment->getRawOriginal('amount'))->stripTrailingZeros(),
            $payment->order_reference_no,
            ($payment->received_at ?? $payment->created_at)?->getTimestamp(),
            $payment->isVoided(),
            $payment->trashed(),
        ], JSON_THROW_ON_ERROR));
    }
}
