<?php

namespace Modules\Pos\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Pos\Events\ClosePosSession;
use Modules\Pos\Events\OpenPosSession;
use Modules\Pos\Listeners\SendPosCashVarianceFoundNotification;
use Modules\Pos\Listeners\SendPosSessionClosedNotification;
use Modules\Pos\Listeners\SendPosSessionOpenedNotification;
use Modules\Pos\Listeners\UpdateRegisterLastSession;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        OpenPosSession::class => [
            UpdateRegisterLastSession::class,
            SendPosSessionOpenedNotification::class,
        ],
        ClosePosSession::class => [
            SendPosSessionClosedNotification::class,
            SendPosCashVarianceFoundNotification::class,
        ],
    ];
}
