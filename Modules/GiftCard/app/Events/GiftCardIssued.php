<?php

namespace Modules\GiftCard\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\GiftCard\Models\GiftCard;

class GiftCardIssued
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public GiftCard $giftCard) {}
}
