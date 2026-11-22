<?php

namespace Modules\Reservation\Http\Requests\Api\V1;

use Closure;
use Illuminate\Validation\Validator;
use Modules\Core\Http\Requests\Request;
use Modules\Reservation\Support\TimeInput;

class SaveReservationSettingRequest extends Request
{
    /**
     * Normalize working-hours time inputs before validation.
     */
    protected function prepareForValidation(): void
    {
        $workingHours = $this->input('working_hours');

        if (! is_array($workingHours)) {
            return;
        }

        $this->merge([
            'working_hours' => collect($workingHours)
                ->map(fn (mixed $hours) => [
                    ...((array) $hours),
                    'open' => TimeInput::format(data_get($hours, 'open'), 'h:i A'),
                    'close' => TimeInput::format(data_get($hours, 'close'), 'h:i A'),
                ])
                ->all(),
        ]);
    }

    /**
     * Get validation rules for branch reservation settings.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->getBranchRule(),
            'reservations_enabled' => ['required', 'boolean'],
            'default_duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'min_duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'max_duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'slot_interval_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'min_guests' => ['required', 'integer', 'min:1'],
            'max_guests' => ['required', 'integer', 'min:1'],
            'large_party_threshold' => ['required', 'integer', 'min:1'],
            'large_party_requires_approval' => ['required', 'boolean'],
            'min_advance_minutes' => ['required', 'integer', 'min:0'],
            'max_advance_days' => ['required', 'integer', 'min:0'],
            'allow_walk_in' => ['required', 'boolean'],
            'pending_blocks_capacity' => ['required', 'boolean'],
            'require_table_on_create' => ['required', 'boolean'],
            'require_table_on_confirm' => ['required', 'boolean'],
            'require_table_on_arrival' => ['required', 'boolean'],
            'require_table_on_seating' => ['required', 'boolean'],
            'auto_confirm_enabled' => ['required', 'boolean'],
            'cancellation_allowed' => ['required', 'boolean'],
            'require_cancellation_reason' => ['required', 'boolean'],
            'cancellation_deadline_minutes' => ['nullable', 'integer', 'min:0'],
            'grace_period_minutes' => ['required', 'integer', 'min:0'],
            'auto_no_show_enabled' => ['required', 'boolean'],
            'pending_expiry_minutes' => ['required', 'integer', 'min:0'],
            'deposit_enabled' => ['required', 'boolean'],
            'deposit_required_for_large_party' => ['required', 'boolean'],
            'deposit_type' => ['nullable', 'string', 'in:fixed'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_refundable' => ['required', 'boolean'],
            'deposit_payment_deadline_minutes' => ['nullable', 'integer', 'min:0'],
            'reminder_enabled' => ['required', 'boolean'],
            'reminder_before_minutes' => ['required', 'integer', 'min:1'],
            'waitlist_enabled' => ['required', 'boolean'],
            'waitlist_expiry_minutes' => ['nullable', 'integer', 'min:0'],
            'working_hours' => ['required', 'array'],
            'working_hours.*.enabled' => ['required', 'boolean'],
            'working_hours.*.open' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                if (! TimeInput::isValid($value)) {
                    $fail(__('validation.date_format', ['attribute' => $attribute, 'format' => 'hh:mm AM/PM']));
                }
            }],
            'working_hours.*.close' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                if (! TimeInput::isValid($value)) {
                    $fail(__('validation.date_format', ['attribute' => $attribute, 'format' => 'hh:mm AM/PM']));
                }
            }],
        ];
    }

    /**
     * Add deposit policy validation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('deposit_enabled') && (float) $this->input('deposit_amount', 0) <= 0) {
                $validator->errors()->add('deposit_amount', __('validation.gt.numeric', [
                    'attribute' => __('reservation::attributes.reservation_settings.deposit_amount'),
                    'value' => 0,
                ]));
            }
        });
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'reservation::attributes.reservation_settings';
    }
}
