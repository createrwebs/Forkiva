<?php

namespace Modules\DailyFinance\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class DailyFinanceEntriesRequest extends DailyFinanceRequest
{
    /**
     * Require an explicit overview context before returning source records.
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'kind' => ['required', Rule::in(['sales', 'dues', 'collections', 'refunds', 'movements'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
