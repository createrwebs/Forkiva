<?php

namespace Modules\GiftCard\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

/**
 * Validates the reviewer confirmation that freezes a Gift Card breakage batch.
 */
class ReviewGiftCardBreakageBatchRequest extends Request
{
    /**
     * Get review date and audit-note validation rules.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'economic_date' => ['required', 'date'],
            'review_note' => ['required', 'string', 'max:10000'],
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
