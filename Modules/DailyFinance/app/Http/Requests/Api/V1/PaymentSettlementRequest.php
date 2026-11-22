<?php

namespace Modules\DailyFinance\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\DailyFinance\Enums\PaymentSettlementStatus;
use Modules\Payment\Enums\PaymentMethod;

class PaymentSettlementRequest extends Request
{
    /**
     * Validate branch context and bounded source selections without trusting client totals.
     */
    public function rules(): array
    {
        $money = ['required', 'string', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'];
        $method = Rule::in([PaymentMethod::Card->value, PaymentMethod::BankTransfer->value, PaymentMethod::MobileWallet->value]);

        return [
            'branch_id' => [...$this->getBranchRule(false, true)['branch_id'], 'integer'],
            ...match ($this->route()->getActionMethod()) {
                'candidates' => [
                    'method' => ['required', $method],
                    'from' => ['required', 'date_format:Y-m-d'],
                    'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
                    'search' => ['nullable', 'string', 'max:120'],
                    'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
                ],
                'store' => [
                    'method' => ['required', $method],
                    'payment_ids' => ['required', 'array', 'min:1', 'max:500'],
                    'payment_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
                    'source_hashes' => ['required', 'array', 'min:1', 'max:500'],
                    'source_hashes.*' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
                    'fees' => $money,
                    'reference' => ['required', 'string', 'max:120'],
                    'account_key' => ['nullable', 'string', 'max:120'],
                    'destination_name' => ['nullable', 'required_without:account_key', 'string', 'max:160'],
                    'notes' => ['nullable', 'string', 'max:1000'],
                    'request_key' => ['required', 'uuid'],
                ],
                'receive' => [
                    'amount' => $money,
                    'received_date' => ['required', 'date_format:Y-m-d'],
                    'reference' => ['required', 'string', 'max:120'],
                    'is_final' => ['required', 'boolean'],
                    'variance_reason' => ['nullable', 'string', 'max:1000'],
                    'request_key' => ['required', 'uuid'],
                ],
                'cancel' => ['cancellation_reason' => ['required', 'string', 'min:3', 'max:1000']],
                default => [
                    'status' => ['nullable', Rule::in([...PaymentSettlementStatus::values(), 'outstanding'])],
                    'method' => ['nullable', $method],
                    'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
                ],
            },
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'dailyfinance::payment_settlements.fields';
    }
}
