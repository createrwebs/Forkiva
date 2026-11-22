<?php

namespace Modules\Export\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Export\Events\ExportCompleted;
use Modules\Export\Events\ExportFailed;
use Modules\Export\Listeners\SendExportCompletedNotification;
use Modules\Export\Listeners\SendExportFailedNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ExportCompleted::class => [
            SendExportCompletedNotification::class,
        ],
        ExportFailed::class => [
            SendExportFailedNotification::class,
        ],
    ];
}
