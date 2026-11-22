<?php

namespace Modules\GiftCard\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\GiftCard\Events\GiftCardAdjusted;
use Modules\GiftCard\Events\GiftCardExpired;
use Modules\GiftCard\Events\GiftCardIssued;
use Modules\GiftCard\Events\GiftCardRecharged;
use Modules\GiftCard\Events\GiftCardRedeemed;
use Modules\GiftCard\Listeners\SendGiftCardAdjustedNotification;
use Modules\GiftCard\Listeners\SendGiftCardExpiredNotification;
use Modules\GiftCard\Listeners\SendGiftCardIssuedNotification;
use Modules\GiftCard\Listeners\SendGiftCardRechargedNotification;
use Modules\GiftCard\Listeners\SendGiftCardRedeemedNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        GiftCardIssued::class => [
            SendGiftCardIssuedNotification::class,
        ],
        GiftCardRedeemed::class => [
            SendGiftCardRedeemedNotification::class,
        ],
        GiftCardRecharged::class => [
            SendGiftCardRechargedNotification::class,
        ],
        GiftCardAdjusted::class => [
            SendGiftCardAdjustedNotification::class,
        ],
        GiftCardExpired::class => [
            SendGiftCardExpiredNotification::class,
        ],
    ];
}
