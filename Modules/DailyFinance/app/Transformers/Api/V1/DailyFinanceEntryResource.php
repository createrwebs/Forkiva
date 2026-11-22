<?php

namespace Modules\DailyFinance\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;

class DailyFinanceEntryResource extends JsonResource
{
    /**
     * Expose only the source fields needed to review a daily total, in its original currency.
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        if ($item instanceof Order) {
            return [
                'id' => $item->id,
                'reference' => $item->reference_no,
                'occurred_at' => $item->created_at?->toIso8601String(),
                'amount' => $request->input('kind') === 'dues' ? $item->due_amount : $item->total,
                'status' => $item->status->toTrans(),
                'label' => $item->payment_status->toTrans(),
                'occurred_at_formatted' => dateTimeFormat($item->created_at, timezone: $item->branch?->timezone ?: config('app.timezone')),
                'order' => [
                    'type' => $item->type->toTrans(),
                    'subtotal' => $item->subtotal,
                    'discount' => $item->total_discount,
                    'tax' => $item->tax_total,
                    'charges' => $item->charge_total,
                    'total' => $item->total,
                    'due' => $item->due_amount,
                ],
            ];
        }
        if ($item instanceof Payment) {
            return [
                'id' => $item->id,
                'reference' => $item->order_reference_no,
                'occurred_at' => ($item->received_at ?? $item->created_at)?->toIso8601String(),
                'amount' => $item->amount,
                'status' => null,
                'label' => $item->method->toTrans(),
                'occurred_at_formatted' => dateTimeFormat($item->received_at ?? $item->created_at, timezone: $item->branch?->timezone ?: config('app.timezone')),
                'receipt' => ['cashier' => $item->cashier?->name],
            ];
        }

        return [
            'id' => $item->id,
            'reference' => $item->reference,
            'occurred_at' => $item->occurred_at?->toIso8601String(),
            'amount' => $item->amount,
            'status' => null,
            'label' => $item->reason->toTrans(),
            'direction' => $item->direction->toTrans(),
            'occurred_at_formatted' => dateTimeFormat($item->occurred_at, timezone: $item->branch?->timezone ?: config('app.timezone')),
            'movement' => [
                'register' => $item->posRegister?->name,
                'balance_before' => $item->balance_before,
                'balance_after' => $item->balance_after,
            ],
        ];
    }
}
