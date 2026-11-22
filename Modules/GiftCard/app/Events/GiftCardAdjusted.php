<?php

namespace Modules\GiftCard\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\GiftCard\Models\GiftCardTransaction;

class GiftCardAdjusted
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public GiftCardTransaction $transaction) {}
}
