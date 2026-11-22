<?php

namespace Modules\Reservation\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Core\Http\Requests\Request;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Enums\ReservationType;
use Modules\Reservation\Enums\TableAssignmentMode;
use Modules\Reservation\Support\TimeInput;
use Modules\Support\Country;

class SaveReservationRequest extends Request
{
    /**
     * Get validation rules for creating or updating reservations.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->getBranchRule(),
            'customer_id' => ['nullable', 'integer', 'exists:users,id,deleted_at,NULL'],
            'phone_country_iso_code' => ['required', 'string', Rule::in(Country::codes())],
            'reservation_type' => ['nullable', Rule::enum(ReservationType::class)],
            'status' => $this->isMethod('post')
                ? ['nullable', Rule::in([ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])]
                : ['prohibited'],
            'table_assignment_mode' => ['nullable', Rule::enum(TableAssignmentMode::class)],
            'reservation_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'guest_count' => ['required', 'integer', 'min:1'],
            'adult_count' => ['nullable', 'integer', 'min:0'],
            'child_count' => ['nullable', 'integer', 'min:0'],
            'customer_name' => ['required', 'string', 'min:2', 'max:255'],
            'customer_phone' => ['bail', 'required', 'phone:phone_country_iso_code'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'occasion' => ['nullable', 'string', 'max:255'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'table_ids' => ['nullable', 'array'],
            'table_ids.*' => ['integer', 'distinct', 'exists:tables,id,deleted_at,NULL'],
            'deposit_required' => ['nullable', 'boolean'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'external_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Add table assignment mode validation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mode = $this->input('table_assignment_mode');
            $tableIds = array_values(array_filter((array) $this->input('table_ids', [])));

            if ($mode === TableAssignmentMode::AssignNow->value && empty($tableIds)) {
                $validator->errors()->add('table_ids', __('reservation::messages.tables_required'));
            }

            if (
                in_array($mode, [TableAssignmentMode::AssignLater->value, TableAssignmentMode::AutoAssign->value], true)
                && ! empty($tableIds)
            ) {
                $validator->errors()->add('table_ids', __('reservation::messages.table_ids_only_assign_now'));
            }
        });
    }

    /**
     * Normalize time inputs before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_time' => TimeInput::normalize($this->input('start_time')),
        ]);
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'reservation::attributes.reservations';
    }
}
