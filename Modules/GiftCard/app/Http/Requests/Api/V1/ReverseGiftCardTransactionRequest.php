<?php

namespace Modules\GiftCard\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class ReverseGiftCardTransactionRequest extends Request
{
    /**
     * Get the required reversal payload rules.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:255'],
            'economic_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:10000'],
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
