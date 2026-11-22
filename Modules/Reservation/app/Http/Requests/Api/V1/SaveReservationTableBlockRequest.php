<?php

namespace Modules\Reservation\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class SaveReservationTableBlockRequest extends Request
{
    /**
     * Get validation rules for reservation table blocks.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->getBranchRule(),
            'table_id' => ['nullable', 'integer', 'exists:tables,id,deleted_at,NULL'],
            ...$this->getTranslationRules([
                'title' => 'required|string|min:2|max:255',
            ]),
            'reason' => ['nullable', 'string', 'max:1000'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'reservation::attributes.reservation_table_blocks';
    }
}
