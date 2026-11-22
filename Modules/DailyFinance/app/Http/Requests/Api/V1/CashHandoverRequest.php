<?php

namespace Modules\DailyFinance\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;

class CashHandoverRequest extends Request
{
    public function rules(): array
    {
        $money = ['required', 'string', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'];

        return match ($this->route()->getActionMethod()) {
            'followUp' => ['branch_id' => [...$this->getBranchRule(false, true)['branch_id'], 'integer']],
            'followUpSessions' => [
                'branch_id' => [...$this->getBranchRule(false, true)['branch_id'], 'integer'],
                'kind' => ['required', Rule::in(['unhanded', 'pending'])],
                'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            ],
            'store' => [
                'request_key' => ['required', 'uuid'],
                'amount' => $money,
                'receiver_id' => ['required', 'integer'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            'receive' => [
                'received_amount' => $money,
                'variance_reason' => ['nullable', 'string', 'max:1000'],
            ],
            'cancel' => ['cancellation_reason' => ['required', 'string', 'min:3', 'max:1000']],
            default => ['page' => ['nullable', 'integer', 'min:1', 'max:100000']],
        };
    }

    protected function availableAttributes(): string
    {
        return 'dailyfinance::cash_handovers.fields';
    }
}
