<?php

namespace Modules\Reservation\Transformers\Api\V1;

use Illuminate\Http\Request;

class ShowReservationResource extends ReservationResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'status_logs' => ReservationStatusLogResource::collection($this->whenLoaded('statusLogs')),
            'reminders' => ReservationReminderResource::collection($this->whenLoaded('reminders')),
            'deposit_transactions' => ReservationDepositTransactionResource::collection($this->whenLoaded('depositTransactions')),
            'order' => [
                'id' => $this->order_id,
                'reference_no' => $this->relationLoaded('order') ? $this->order?->reference_no : null,
            ],
            'invoice' => [
                'id' => $this->invoice_id,
                'invoice_number' => $this->relationLoaded('invoice') ? $this->invoice?->invoice_number : null,
            ],
            'cancellation_reason' => $this->cancellation_reason,
            'rejection_reason' => $this->rejection_reason,
            'no_show_reason' => $this->no_show_reason,
            'external_reference' => $this->external_reference,
        ];
    }
}
