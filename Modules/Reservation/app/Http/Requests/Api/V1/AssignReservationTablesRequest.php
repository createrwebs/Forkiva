<?php

namespace Modules\Reservation\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class AssignReservationTablesRequest extends Request
{
    /**
     * Get validation rules for reservation table assignment.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'table_ids' => ['required', 'array', 'min:1'],
            'table_ids.*' => ['integer', 'distinct', 'exists:tables,id,deleted_at,NULL'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'reservation::attributes.reservation_tables';
    }
}
