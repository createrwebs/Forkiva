<?php

namespace Modules\Reservation\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Core\Http\Requests\Request;
use Modules\Reservation\Enums\TableAssignmentMode;
use Modules\Reservation\Support\TimeInput;

class CheckReservationAvailabilityRequest extends Request
{
    /**
     * Normalize time inputs before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_time' => TimeInput::normalize($this->input('start_time')),
        ]);
    }

    /**
     * Get validation rules for availability checks.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->getBranchRule(),
            'table_assignment_mode' => ['nullable', Rule::enum(TableAssignmentMode::class)],
            'reservation_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'guest_count' => ['required', 'integer', 'min:1'],
            'table_ids' => ['nullable', 'array'],
            'table_ids.*' => ['integer', 'distinct', 'exists:tables,id,deleted_at,NULL'],
            'exclude_reservation_id' => ['nullable', 'integer', 'exists:reservations,id,deleted_at,NULL'],
        ];
    }

    /**
     * Add table assignment mode validation for availability checks.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mode = $this->input('table_assignment_mode');
            $tableIds = array_values(array_filter((array) $this->input('table_ids', [])));

            if ($mode === TableAssignmentMode::AssignLater->value) {
                $validator->errors()->add('table_assignment_mode', __('reservation::messages.availability_not_available_for_assign_later'));
            }

            if ($mode === TableAssignmentMode::AssignNow->value && empty($tableIds)) {
                $validator->errors()->add('table_ids', __('reservation::messages.tables_required'));
            }

            if ($mode === TableAssignmentMode::AutoAssign->value && ! empty($tableIds)) {
                $validator->errors()->add('table_ids', __('reservation::messages.table_ids_only_assign_now'));
            }
        });
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'reservation::attributes.reservations';
    }
}
