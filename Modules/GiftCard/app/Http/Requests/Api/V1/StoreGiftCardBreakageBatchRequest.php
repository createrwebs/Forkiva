<?php

namespace Modules\GiftCard\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

/**
 * Validates the creation of an idempotent draft Gift Card breakage batch.
 */
class StoreGiftCardBreakageBatchRequest extends Request
{
    /**
     * Get the branch, selected-card, review, and idempotency validation rules.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->getBranchRule(),
            'gift_card_ids' => ['required', 'array', 'min:1'],
            'gift_card_ids.*' => ['integer', 'distinct', 'exists:gift_cards,id'],
            'economic_date' => ['required', 'date'],
            'idempotency_key' => ['required', 'string', 'max:255'],
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
