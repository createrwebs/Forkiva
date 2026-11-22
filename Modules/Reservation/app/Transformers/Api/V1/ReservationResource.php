<?php

namespace Modules\Reservation\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Support\ReservationDepositCurrencyRate;
use Modules\Support\Enums\DateTimeFormat;

/** @mixin Reservation */
class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reservation_number' => $this->reservation_number,
            'branch' => [
                'id' => $this->branch_id,
                'name' => $this->relationLoaded('branch') ? $this->branch?->name : null,
                'timezone' => $this->relationLoaded('branch') ? $this->branch?->timezone : null,
                'currency' => $this->relationLoaded('branch') ? $this->branch?->currency : null,
            ],
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->relationLoaded('customer') ? $this->customer?->name : null,
                'email' => $this->relationLoaded('customer') ? $this->customer?->email : null,
                'phone' => $this->relationLoaded('customer') ? $this->customer?->phone : null,
                'phone_country_iso_code' => $this->relationLoaded('customer') ? $this->customer?->phone_country_iso_code : null,
            ],
            'reservation_type' => $this->reservation_type?->toTrans(),
            'status' => $this->status?->toTrans(),
            'table_assignment_status' => $this->table_assignment_status?->toTrans(),
            'table_assignment_mode' => $this->table_assignment_mode?->toTrans(),
            'abilities' => [
                'can_edit' => $this->status?->allowsEditing() ?? false,
                'can_change_status' => $this->status?->allowsStatusChange() ?? false,
                'can_assign_tables' => $this->status?->allowsTableAssignment() ?? false,
                'allowed_statuses' => $this->status?->allowedTransitionValues() ?? [],
            ],
            'reservation_date' => dateTimeFormat($this->reservation_date, DateTimeFormat::Date),
            'start_time' => dateTimeFormat($this->start_time, DateTimeFormat::Time),
            'end_time' => dateTimeFormat($this->end_time, DateTimeFormat::Time),
            'start_at' => dateTimeFormat($this->start_at),
            'end_at' => dateTimeFormat($this->end_at),
            'duration_minutes' => $this->duration_minutes,
            'guest_count' => $this->guest_count,
            'adult_count' => $this->adult_count,
            'child_count' => $this->child_count,
            'customer_name' => $this->customer_name,
            'phone_country_iso_code' => $this->phone_country_iso_code,
            'customer_phone' => $this->customer_phone,
            'customer_national_phone' => $this->customer_national_phone,
            'customer_email' => $this->customer_email,
            'occasion' => $this->occasion,
            'special_requests' => $this->special_requests,
            'internal_notes' => $this->internal_notes,
            'tables' => ReservationTableAssignmentResource::collection($this->whenLoaded('reservationTables')),
            'payment_status' => $this->payment_status?->toTrans(),
            'deposit_required' => $this->deposit_required,
            'deposit_amount' => $this->deposit_amount->withConvertedDefaultCurrency(
                ReservationDepositCurrencyRate::forMoneyConversion($this->deposit_currency_rate)
            ),
            'deposit_paid_amount' => $this->deposit_paid_amount->withConvertedDefaultCurrency(
                ReservationDepositCurrencyRate::forMoneyConversion($this->deposit_currency_rate)
            ),
            'deposit_currency' => $this->deposit_currency,
            'deposit_currency_rate' => $this->deposit_currency_rate,
            'deposit_policy' => [
                'refundable' => ! $this->relationLoaded('reservationSetting') || (($this->reservationSetting?->deposit_refundable ?? true)),
            ],
            'confirmed_at' => dateTimeFormat($this->confirmed_at),
            'arrived_at' => dateTimeFormat($this->arrived_at),
            'seated_at' => dateTimeFormat($this->seated_at),
            'completed_at' => dateTimeFormat($this->completed_at),
            'cancelled_at' => dateTimeFormat($this->cancelled_at),
            'no_show_at' => dateTimeFormat($this->no_show_at),
            'created_by' => [
                'id' => $this->created_by,
                'name' => $this->relationLoaded('createdBy') ? $this->createdBy?->name : null,
            ],
            'created_at' => dateTimeFormat($this->created_at),
            'updated_at' => dateTimeFormat($this->updated_at),
        ];
    }
}
