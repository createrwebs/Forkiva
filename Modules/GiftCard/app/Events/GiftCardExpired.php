<?php

namespace Modules\GiftCard\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardTransaction;

class GiftCardExpired
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public GiftCard $giftCard,
        public ?GiftCardTransaction $transaction = null,
    ) {}
}
