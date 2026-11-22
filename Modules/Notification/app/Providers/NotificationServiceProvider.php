<?php

namespace Modules\Notification\Providers;

use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\ServiceProvider;
use Modules\Notification\Channels\ResilientMailChannel;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register the module services.
     */
    public function register(): void
    {
        $this->app->bind(MailChannel::class, ResilientMailChannel::class);
    }

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__, 2).'/resources/views', 'notification');
    }
}
