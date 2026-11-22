<?php

namespace Modules\Reservation\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservation\Models\ReservationSetting;

/** @mixin ReservationSetting */
class ReservationSettingResource extends JsonResource
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
            'branch' => [
                'id' => $this->branch_id,
                'name' => $this->relationLoaded('branch') ? $this->branch?->name : null,
                'timezone' => $this->relationLoaded('branch') ? $this->branch?->timezone : null,
            ],
            'created_by' => [
                'id' => $this->created_by,
                'name' => $this->relationLoaded('createdBy') ? $this->createdBy?->name : null,
            ],
            'reservations_enabled' => $this->reservations_enabled,
            'default_duration_minutes' => $this->default_duration_minutes,
            'min_duration_minutes' => $this->min_duration_minutes,
            'max_duration_minutes' => $this->max_duration_minutes,
            'buffer_minutes' => $this->buffer_minutes,
            'slot_interval_minutes' => $this->slot_interval_minutes,
            'min_guests' => $this->min_guests,
            'max_guests' => $this->max_guests,
            'large_party_threshold' => $this->large_party_threshold,
            'large_party_requires_approval' => $this->large_party_requires_approval,
            'min_advance_minutes' => $this->min_advance_minutes,
            'max_advance_days' => $this->max_advance_days,
            'allow_walk_in' => $this->allow_walk_in,
            'pending_blocks_capacity' => $this->pending_blocks_capacity,
            'require_table_on_create' => $this->require_table_on_create,
            'require_table_on_confirm' => $this->require_table_on_confirm,
            'require_table_on_arrival' => $this->require_table_on_arrival,
            'require_table_on_seating' => $this->require_table_on_seating,
            'auto_confirm_enabled' => $this->auto_confirm_enabled,
            'cancellation_allowed' => $this->cancellation_allowed,
            'require_cancellation_reason' => $this->require_cancellation_reason,
            'cancellation_deadline_minutes' => $this->cancellation_deadline_minutes,
            'grace_period_minutes' => $this->grace_period_minutes,
            'auto_no_show_enabled' => $this->auto_no_show_enabled,
            'pending_expiry_minutes' => $this->pending_expiry_minutes,
            'deposit_enabled' => $this->deposit_enabled,
            'deposit_required_for_large_party' => $this->deposit_required_for_large_party,
            'deposit_type' => 'fixed',
            'deposit_amount' => $this->deposit_amount,
            'deposit_refundable' => $this->deposit_refundable,
            'deposit_payment_deadline_minutes' => $this->deposit_payment_deadline_minutes,
            'reminder_enabled' => $this->reminder_enabled,
            'reminder_before_minutes' => $this->reminder_before_minutes,
            'waitlist_enabled' => $this->waitlist_enabled,
            'waitlist_expiry_minutes' => $this->waitlist_expiry_minutes,
            'working_hours' => ReservationSetting::normalizeWorkingHours($this->working_hours),
            'created_at' => dateTimeFormat($this->created_at),
            'updated_at' => dateTimeFormat($this->updated_at),
        ];
    }
}
