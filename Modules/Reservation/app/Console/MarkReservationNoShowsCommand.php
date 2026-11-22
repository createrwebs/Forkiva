<?php

namespace Modules\Reservation\Console;

use Illuminate\Console\Command;
use Modules\Reservation\Services\ReservationReminder\ReservationReminderServiceInterface;

class MarkReservationNoShowsCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'reservations:mark-no-show';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark eligible reservations as no-show.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->description = __('reservation::commands.mark_no_show_description');
    }

    /**
     * Execute the console command.
     */
    public function handle(ReservationReminderServiceInterface $service): int
    {
        $count = $service->markNoShows();
        $this->info(__('reservation::commands.mark_no_show_result', ['count' => $count]));

        return self::SUCCESS;
    }
}
