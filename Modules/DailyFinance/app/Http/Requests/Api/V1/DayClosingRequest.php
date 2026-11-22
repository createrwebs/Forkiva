<?php

namespace Modules\DailyFinance\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class DayClosingRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'branch_id' => [...$this->getBranchRule(false, true)['branch_id'], 'integer'],
            'date' => [$this->route()->getActionMethod() === 'index' ? 'nullable' : 'required', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            ...($this->route()->getActionMethod() === 'store' ? [
                'request_key' => ['required', 'uuid'],
                'review_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ] : []),
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'dailyfinance::day_closings.fields';
    }
}
