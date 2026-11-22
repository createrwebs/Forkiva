<?php

namespace Modules\Reservation\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservation\Models\ReservationDepositTransaction;
use Modules\Reservation\Support\ReservationDepositCurrencyRate;

/** @mixin ReservationDepositTransaction */
class ReservationDepositTransactionResource extends JsonResource
{
    /**
     * Transform a deposit ledger transaction for the reservation API.
     */
    public function toArray(Request $request): array
    {
        $allocatedInvoice = $this->relationLoaded('payment')
            && $this->payment?->relationLoaded('allocations')
            ? $this->payment->allocations->first()?->invoice
            : null;

        return [
            'id' => $this->id,
            'type' => $this->type->toTrans(),
            'amount' => [
                ...$this->amount->withConvertedDefaultCurrency(
                    ReservationDepositCurrencyRate::forMoneyConversion($this->currency_rate)
                ),
                'converted' => $this->base_amount,
            ],
            'base_amount' => $this->base_amount,
            'balance_before' => [
                ...$this->balance_before->withConvertedDefaultCurrency(
                    ReservationDepositCurrencyRate::forMoneyConversion($this->currency_rate)
                ),
                'converted' => $this->base_balance_before,
            ],
            'base_balance_before' => $this->base_balance_before->withConvertedDefaultCurrency($this->currency_rate),
            'balance_after' => [
                ...$this->balance_after->withConvertedDefaultCurrency(
                    ReservationDepositCurrencyRate::forMoneyConversion($this->currency_rate)
                ),
                'converted' => $this->base_balance_after,
            ],
            'base_balance_after' => $this->base_balance_after->withConvertedDefaultCurrency($this->currency_rate),
            'currency' => $this->currency,
            'currency_rate' => $this->currency_rate,
            'tender_method' => $this->tender_method?->toTrans(),
            'financial_account' => $this->financial_account_snapshot,
            'economic_date' => dateTimeFormat($this->economic_date),
            'note' => $this->note,
            'order' => [
                'id' => $this->order_id,
                'reference_no' => $this->relationLoaded('order') ? $this->order?->reference_no : null,
            ],
            'invoice' => [
                'id' => $this->invoice_id ?? $allocatedInvoice?->id,
                'invoice_number' => $this->relationLoaded('invoice')
                    ? ($this->invoice?->invoice_number ?? $allocatedInvoice?->invoice_number)
                    : $allocatedInvoice?->invoice_number,
            ],
            'payment' => [
                'id' => $this->payment_id,
                'transaction_id' => $this->relationLoaded('payment') ? $this->payment?->transaction_id : null,
                'voided_at' => $this->relationLoaded('payment') ? dateTimeFormat($this->payment?->voided_at) : null,
            ],
            'original_transaction' => $this->relationLoaded('originalTransaction') && $this->originalTransaction ? [
                'id' => $this->originalTransaction->id,
                'type' => $this->originalTransaction->type->toTrans(),
            ] : null,
            'reversed_by_transaction' => $this->relationLoaded('reversedByTransaction') && $this->reversedByTransaction ? [
                'id' => $this->reversedByTransaction->id,
                'type' => $this->reversedByTransaction->type->toTrans(),
            ] : null,
            'reversed_at' => dateTimeFormat($this->reversed_at),
            'created_by' => [
                'id' => $this->created_by,
                'name' => $this->relationLoaded('createdBy') ? $this->createdBy?->name : null,
            ],
            'can_reverse' => $this->canReverse(),
            'created_at' => dateTimeFormat($this->created_at),
        ];
    }
}
