<?php

namespace Modules\GiftCard\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\GiftCard\Enums\GiftCardFundingType;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\Payment\Enums\PaymentMethod;

class RecordGiftCardFinancialActionRequest extends Request
{
    /**
     * Get action-specific validation rules for the financial transaction payload.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                GiftCardTransactionType::Recharge->value,
                GiftCardTransactionType::CashOut->value,
                GiftCardTransactionType::IssueVoid->value,
                GiftCardTransactionType::Adjustment->value,
                GiftCardTransactionType::AdjustmentDecrease->value,
            ])],
            'amount' => [
                Rule::requiredIf(fn (): bool => $this->input('type') !== GiftCardTransactionType::IssueVoid->value),
                'nullable',
                'numeric',
                'gt:0',
            ],
            'original_transaction_id' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === GiftCardTransactionType::IssueVoid->value),
                'nullable',
                'integer',
                'exists:gift_card_transactions,id',
            ],
            'funding_type' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === GiftCardTransactionType::Recharge->value),
                'nullable',
                Rule::enum(GiftCardFundingType::class),
            ],
            'tender_method' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === GiftCardTransactionType::CashOut->value
                    || ($this->input('type') === GiftCardTransactionType::Recharge->value
                        && $this->input('funding_type') === GiftCardFundingType::Paid->value)),
                'nullable',
                Rule::enum(PaymentMethod::class),
            ],
            'financial_account_key' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'economic_date' => ['required', 'date'],
            'notes' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * Resolve localized attribute names for validation messages.
     */
    protected function availableAttributes(): string
    {
        return 'giftcard::attributes.gift_card_transactions';
    }
}
