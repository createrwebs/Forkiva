<?php

namespace Modules\DailyFinance\Transformers\Api\V1;

use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\DailyFinance\Enums\PaymentSettlementStatus;
use Modules\DailyFinance\Models\PaymentSettlement;
use Modules\DailyFinance\Models\PaymentSettlementItem;
use Modules\DailyFinance\Models\PaymentSettlementReceipt;
use Modules\Support\Money;

/** @mixin PaymentSettlement */
class PaymentSettlementResource extends JsonResource
{
    /**
     * Separate confirmed bank credits from outstanding expectations and final variances.
     */
    public function toArray(Request $request): array
    {
        $actual = BigDecimal::zero();
        if ($this->relationLoaded('receipts')) {
            foreach ($this->receipts as $receipt) {
                $actual = $actual->plus($receipt->amount);
            }
        }
        $expected = BigDecimal::of($this->expected_amount);
        $variance = $this->status === PaymentSettlementStatus::Settled ? $actual->minus($expected) : null;
        $remaining = in_array($this->status, [PaymentSettlementStatus::Settled, PaymentSettlementStatus::Cancelled], true)
            ? BigDecimal::zero() : BigDecimal::max($expected->minus($actual), 0);

        return [
            'id' => $this->id,
            'reference' => $this->reference_no,
            'batch_reference' => $this->reference,
            'branch_id' => $this->branch_id,
            'method' => $this->method->toTrans(),
            'status' => $this->status->toTrans(),
            'currency' => $this->currency,
            'today' => now($this->timezone)->toDateString(),
            'destination_name' => $this->destination_name,
            'account_key' => $this->account_key,
            'gross' => $this->money(BigDecimal::of($this->gross_amount)),
            'fees' => $this->money(BigDecimal::of($this->fees)),
            'expected' => $this->money($expected),
            'actual' => $this->money($actual),
            'remaining' => $this->money($remaining),
            'remaining_decimal' => (string) $remaining,
            'expected_decimal' => (string) $expected,
            'actual_decimal' => (string) $actual,
            'variance' => $variance === null ? null : $this->money($variance),
            'items_count' => $this->items_count,
            'created_at' => dateTimeFormat($this->created_at, timezone: $this->timezone),
            'created_by' => [
                'id' => $this->created_by,
                'name' => $this->relationLoaded('createdBy') ? $this->createdBy?->name : null,
            ],
            'notes' => $this->notes,
            'cancelled_at' => dateTimeFormat($this->cancelled_at, timezone: $this->timezone),
            'cancelled_by' => $this->relationLoaded('cancelledBy') && $this->cancelledBy ? [
                'id' => $this->cancelled_by,
                'name' => $this->cancelledBy->name,
            ] : null,
            'cancellation_reason' => $this->cancellation_reason,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (PaymentSettlementItem $item) => [
                'id' => $item->payment_id,
                'reference' => $item->order_reference_no,
                'amount' => $this->money(BigDecimal::of($item->amount)),
                'collected_at' => dateTimeFormat($item->collected_at, timezone: $this->timezone),
            ])),
            'receipts' => $this->whenLoaded('receipts', fn () => $this->receipts->map(fn (PaymentSettlementReceipt $receipt) => [
                'id' => $receipt->id,
                'reference' => $receipt->reference,
                'amount' => $this->money(BigDecimal::of($receipt->amount)),
                'received_date' => $receipt->received_date->toDateString(),
                'received_date_formatted' => $receipt->received_date->format(setting('default_date_format', 'Y-m-d')),
                'is_final' => $receipt->is_final,
                'variance_reason' => $receipt->variance_reason,
                'created_at' => dateTimeFormat($receipt->created_at, timezone: $this->timezone),
                'created_by' => [
                    'id' => $receipt->created_by,
                    'name' => $receipt->relationLoaded('createdBy') ? $receipt->createdBy?->name : null,
                ],
            ])),
            'abilities' => [
                'receive' => in_array($this->status, [PaymentSettlementStatus::Pending, PaymentSettlementStatus::Partial], true)
                    && auth()->user()->can('admin.daily_finance_settlements.receive'),
                'cancel' => $this->status === PaymentSettlementStatus::Pending && $actual->isZero()
                    && auth()->user()->can('admin.daily_finance_settlements.cancel'),
            ],
        ];
    }

    protected function money(BigDecimal $amount): Money
    {
        return new Money($amount->toFloat(), $this->currency);
    }
}
