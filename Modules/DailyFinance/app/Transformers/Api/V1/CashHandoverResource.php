<?php

namespace Modules\DailyFinance\Transformers\Api\V1;

use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\DailyFinance\Enums\CashHandoverStatus;
use Modules\DailyFinance\Models\CashHandover;
use Modules\Support\Money;

/** @mixin CashHandover */
class CashHandoverResource extends JsonResource
{
    /**
     * Present custody evidence in its source branch's timezone without lazy-loading actor relations.
     */
    public function toArray(Request $request): array
    {
        $actor = auth()->user();
        $pending = $this->status === CashHandoverStatus::Pending;
        $timezone = $this->relationLoaded('branch') ? $this->branch?->timezone : null;

        return [
            'id' => $this->id,
            'reference' => $this->reference_no,
            'status' => $this->status->toTrans(),
            'amount' => new Money((float) $this->amount, $this->currency),
            'amount_decimal' => $this->amount,
            'received_amount' => $this->received_amount === null ? null : new Money((float) $this->received_amount, $this->currency),
            'received_amount_decimal' => $this->received_amount,
            'variance' => $this->received_amount === null ? null : new Money(BigDecimal::of($this->received_amount)->minus($this->amount)->toFloat(), $this->currency),
            'cashier' => ['id' => $this->cashier_id, 'name' => $this->relationLoaded('cashier') ? ($this->cashier?->name ?? '') : ''],
            'created_by' => ['id' => $this->created_by, 'name' => $this->relationLoaded('createdBy') ? ($this->createdBy?->name ?? '') : ''],
            'receiver' => ['id' => $this->receiver_id, 'name' => $this->relationLoaded('receiver') ? ($this->receiver?->name ?? '') : ''],
            'created_at' => dateTimeFormat($this->created_at, timezone: $timezone),
            'received_at' => dateTimeFormat($this->received_at, timezone: $timezone),
            'cancelled_at' => dateTimeFormat($this->cancelled_at, timezone: $timezone),
            'cancelled_by' => $this->cancelled_by,
            'notes' => $this->notes,
            'variance_reason' => $this->variance_reason,
            'cancellation_reason' => $this->cancellation_reason,
            'can_receive' => $pending && $actor?->id === $this->receiver_id && $actor->can('admin.daily_finance_handovers.receive'),
            'can_cancel' => $pending && $actor?->id === $this->created_by && $actor->can('admin.daily_finance_handovers.cancel'),
        ];
    }
}
