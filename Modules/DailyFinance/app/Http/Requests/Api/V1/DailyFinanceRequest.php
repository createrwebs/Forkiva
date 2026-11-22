<?php

namespace Modules\DailyFinance\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class DailyFinanceRequest extends Request
{
    /**
     * Validate an optional branch and local calendar date; defaults are resolved server-side.
     */
    public function rules(): array
    {
        return [
            ...$this->getBranchRule(true, true),
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'dailyfinance::daily_finance.filters';
    }
}
