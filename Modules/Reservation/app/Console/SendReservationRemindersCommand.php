<?php

namespace Modules\Reservation\Console;

use Illuminate\Console\Command;
use Modules\Reservation\Services\ReservationReminder\ReservationReminderServiceInterface;

class SendReservationRemindersCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'reservations:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send due reservation reminders.';

    /**
     * Execute the console command.
     */
    public function handle(ReservationReminderServiceInterface $service): int
    {
        $count = $service->sendDue();
        $this->info(__('reservation::commands.send_reminders_result', ['count' => $count]));

        return self::SUCCESS;
    }
}
