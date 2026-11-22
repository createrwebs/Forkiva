<?php

namespace Modules\Reservation\Console;

use Illuminate\Console\Command;
use Modules\Reservation\Services\ReservationReminder\ReservationReminderServiceInterface;

class ExpireReservationWaitlistsCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'reservations:expire-waitlists';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire reservation waitlist entries.';

    /**
     * Execute the console command.
     */
    public function handle(ReservationReminderServiceInterface $service): int
    {
        $count = $service->expireWaitlists();
        $this->info(__('reservation::commands.expire_waitlists_result', ['count' => $count]));

        return self::SUCCESS;
    }
}
