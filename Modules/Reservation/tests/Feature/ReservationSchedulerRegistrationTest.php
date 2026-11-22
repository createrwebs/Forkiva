<?php

namespace Modules\Reservation\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ReservationSchedulerRegistrationTest extends TestCase
{
    public function test_reservation_automation_schedules_run_on_only_one_application_node(): void
    {
        $scheduledCommands = [
            'reservations:send-reminders',
            'reservations:expire-pending',
            'reservations:expire-waitlists',
            'reservations:mark-no-show',
        ];

        $events = collect($this->app->make(Schedule::class)->events());

        foreach ($scheduledCommands as $scheduledCommand) {
            $event = $events->first(
                fn ($event) => str_contains($event->command ?? '', $scheduledCommand)
            );

            $this->assertNotNull($event, "The [$scheduledCommand] schedule was not registered.");
            $this->assertTrue($event->onOneServer, "The [$scheduledCommand] schedule may run on every application node.");
            $this->assertTrue($event->withoutOverlapping, "The [$scheduledCommand] schedule may overlap a previous run.");
        }
    }
}
