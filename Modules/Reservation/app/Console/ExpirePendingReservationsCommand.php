<?php

namespace Modules\Reservation\Console;

use Illuminate\Console\Command;
use Modules\Reservation\Services\ReservationReminder\ReservationReminderServiceInterface;

class ExpirePendingReservationsCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'reservations:expire-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending reservations past their start time.';

    /**
     * Execute the console command.
     */
    public function handle(ReservationReminderServiceInterface $service): int
    {
        $count = $service->expirePending();
        $this->info(__('reservation::commands.expire_pending_result', ['count' => $count]));

        return self::SUCCESS;
    }
}
