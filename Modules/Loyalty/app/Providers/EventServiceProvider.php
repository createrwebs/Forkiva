<?php

namespace Modules\Loyalty\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Loyalty\Events\LoyaltyPointsChanged;
use Modules\Loyalty\Events\LoyaltyRewardRedeemed;
use Modules\Loyalty\Events\LoyaltyTierChanged;
use Modules\Loyalty\Listeners\SendLoyaltyPointsChangedNotification;
use Modules\Loyalty\Listeners\SendLoyaltyRewardRedeemedNotification;
use Modules\Loyalty\Listeners\SendLoyaltyTierChangedNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        LoyaltyPointsChanged::class => [
            SendLoyaltyPointsChangedNotification::class,
        ],
        LoyaltyRewardRedeemed::class => [
            SendLoyaltyRewardRedeemedNotification::class,
        ],
        LoyaltyTierChanged::class => [
            SendLoyaltyTierChangedNotification::class,
        ],
    ];
}
