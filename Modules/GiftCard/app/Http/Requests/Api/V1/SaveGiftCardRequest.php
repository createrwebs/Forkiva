<?php

namespace Modules\GiftCard\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\GiftCard\Enums\GiftCardFundingType;
use Modules\GiftCard\Enums\GiftCardStatus;
use Modules\Payment\Enums\PaymentMethod;

class SaveGiftCardRequest extends Request
{
    public function rules(): array
    {
        $giftCardId = $this->route('id');

        return [
            'code' => ['nullable', 'string', 'max:255', Rule::unique('gift_cards', 'code')->ignore($giftCardId)],
            'initial_balance' => [$giftCardId ? 'prohibited' : 'required', 'numeric', 'min:0.0001', 'max:99999999999999'],
            ...$this->getBranchRule(true),
            'customer_id' => ['nullable', 'numeric', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(GiftCardStatus::class)],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'funding_type' => [$giftCardId ? 'nullable' : 'required', Rule::enum(GiftCardFundingType::class)],
            'tender_method' => [
                Rule::requiredIf(fn (): bool => ! $giftCardId && $this->input('funding_type') === GiftCardFundingType::Paid->value),
                'nullable',
                Rule::enum(PaymentMethod::class),
            ],
            'financial_account_key' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => [$giftCardId ? 'nullable' : 'required', 'string', 'max:255'],
            'economic_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Get the translation namespace used for request attribute labels.
     */
    protected function availableAttributes(): string
    {
        return 'giftcard::attributes.gift_cards';
    }
}
