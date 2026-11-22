<?php

namespace Modules\Reservation\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Reservation\Console\ExpirePendingReservationsCommand;
use Modules\Reservation\Console\ExpireReservationWaitlistsCommand;
use Modules\Reservation\Console\MarkReservationNoShowsCommand;
use Modules\Reservation\Console\SendReservationRemindersCommand;

class ReservationServiceProvider extends ServiceProvider
{
    /**
     * Boot the Reservation module service provider.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerScheduler();
    }

    /**
     * Register Reservation console commands when running in console mode.
     */
    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            SendReservationRemindersCommand::class,
            ExpirePendingReservationsCommand::class,
            ExpireReservationWaitlistsCommand::class,
            MarkReservationNoShowsCommand::class,
        ]);
    }

    /**
     * Register Reservation scheduled automation commands.
     */
    protected function registerScheduler(): void
    {
        $this->app->booted(function ($app) {
            $schedule = $app[Schedule::class];

            $schedule->command(SendReservationRemindersCommand::class)
                ->everyMinute()
                ->onOneServer()
                ->withoutOverlapping();

            $schedule->command(ExpirePendingReservationsCommand::class)
                ->everyFiveMinutes()
                ->onOneServer()
                ->withoutOverlapping();

            $schedule->command(ExpireReservationWaitlistsCommand::class)
                ->everyFiveMinutes()
                ->onOneServer()
                ->withoutOverlapping();

            $schedule->command(MarkReservationNoShowsCommand::class)
                ->everyFiveMinutes()
                ->onOneServer()
                ->withoutOverlapping();
        });
    }
}
