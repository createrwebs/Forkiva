<?php

namespace Modules\GiftCard\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GiftCard\Models\GiftCardTransaction;

/** @mixin GiftCardTransaction */
class GiftCardTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'gift_card' => [
                'id' => $this->gift_card_id,
                'code' => $this->relationLoaded('giftCard') ? $this->giftCard?->code : null,
            ],
            'type' => $this->type->toTrans(),
            'amount' => [
                'original' => $this->amount,
                'converted' => $this->amount_in_base_currency,
            ],
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'currency_rate' => $this->currency_rate,
            'currency_rate_source' => $this->currency_rate_source,
            'currency_rate_reviewed_by' => $this->currency_rate_reviewed_by,
            'currency_rate_reviewed_at' => dateTimeFormat($this->currency_rate_reviewed_at),
            'currency_rate_review_note' => $this->currency_rate_review_note,
            'funding_type' => $this->funding_type,
            'tender_method' => $this->tender_method,
            'financial_account_key' => $this->financial_account_key,
            'financial_account_snapshot' => $this->financial_account_snapshot,
            'liability_branch_id' => $this->relationLoaded('giftCard') ? $this->giftCard?->liability_branch_id : null,
            'liability_branch' => $this->relationLoaded('giftCard') ? [
                'id' => $this->giftCard?->liability_branch_id,
                'name' => $this->giftCard?->relationLoaded('liabilityBranch') ? $this->giftCard?->liabilityBranch?->name : null,
            ] : null,
            'payment' => $this->relationLoaded('payment') && $this->payment ? [
                'id' => $this->payment->id,
                'transaction_id' => $this->payment->transaction_id,
            ] : null,
            'original_transaction' => $this->relationLoaded('originalTransaction') && $this->originalTransaction ? [
                'id' => $this->originalTransaction->id,
                'uuid' => $this->originalTransaction->uuid,
                'type' => $this->originalTransaction->type->toTrans(),
            ] : null,
            'reversed_by_transaction' => $this->relationLoaded('reversedByTransaction') && $this->reversedByTransaction ? [
                'id' => $this->reversedByTransaction->id,
                'uuid' => $this->reversedByTransaction->uuid,
                'type' => $this->reversedByTransaction->type->toTrans(),
            ] : null,
            'request_fingerprint' => $this->request_fingerprint,
            'economic_date' => $this->economic_date?->toDateString(),
            'source_snapshot' => $this->source_snapshot,
            'reversed_at' => dateTimeFormat($this->reversed_at),
            'order_currency' => $this->order_currency,
            'amount_in_order_currency' => $this->amount_in_order_currency,
            'branch' => [
                'id' => $this->branch_id,
                'name' => $this->relationLoaded('branch') ? $this->branch?->name : null,
            ],
            'order' => [
                'id' => $this->order_id,
                'reference_no' => $this->relationLoaded('order') ? $this->order?->reference_no : null,
            ],
            'createdBy' => [
                'id' => $this->created_by,
                'name' => $this->relationLoaded('createdBy') ? $this->createdBy?->name : null,
            ],
            'notes' => $this->notes,
            'transaction_at' => dateTimeFormat($this->transaction_at),
            'created_at' => dateTimeFormat($this->created_at),
        ];
    }
}
