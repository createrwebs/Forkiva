<?php

namespace Modules\Reservation\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Modules\Branch\Models\Branch;
use Modules\Currency\Models\CurrencyRate;
use Modules\Reservation\Enums\ReservationPaymentStatus;
use Modules\Reservation\Enums\ReservationReminderStatus;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Enums\ReservationType;
use Modules\Reservation\Enums\ReservationWaitlistStatus;
use Modules\Reservation\Enums\TableAssignmentMode;
use Modules\Reservation\Enums\TableAssignmentStatus;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationReminder;
use Modules\Reservation\Models\ReservationStatusLog;
use Modules\Reservation\Models\ReservationTable;
use Modules\Reservation\Models\ReservationTableBlock;
use Modules\Reservation\Models\ReservationWaitlist;
use Modules\SeatingPlan\Models\Table;

class ReservationDemoSeeder extends Seeder
{
    /**
     * Run the Reservation demo seeds.
     */
    public function run(): void
    {
        Branch::query()
            ->withoutGlobalActive()
            ->get(['id', 'currency'])
            ->each(fn (Branch $branch) => $this->seedBranch($branch));
    }

    /**
     * Seed demo reservations and related records for a branch.
     */
    protected function seedBranch(Branch $branch): void
    {
        $tables = Table::query()
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->where('branch_id', $branch->id)
            ->limit(4)
            ->get(['id', 'branch_id']);

        $reservations = collect([
            [
                'status' => ReservationStatus::Pending,
                'type' => ReservationType::Standard,
                'days' => 1,
                'hour' => 18,
            ],
            [
                'status' => ReservationStatus::Confirmed,
                'type' => ReservationType::LargeParty,
                'days' => 2,
                'hour' => 20,
            ],
            [
                'status' => ReservationStatus::Arrived,
                'type' => ReservationType::WalkIn,
                'days' => 0,
                'hour' => 19,
            ],
        ])->map(fn (array $state, int $index) => $this->createReservation($branch, $tables->get($index), $state));

        $this->createWaitlists($branch);
        $this->createTableBlock($branch, $tables->first());
        $this->createReminders($reservations);
    }

    /**
     * Create one demo reservation and its status/table records.
     *
     * @param  array<string, mixed>  $state
     */
    protected function createReservation(Branch $branch, ?Table $table, array $state): Reservation
    {
        $startAt = Carbon::now()
            ->addDays((int) $state['days'])
            ->setTime((int) $state['hour'], 0);
        $endAt = $startAt->copy()->addMinutes(90);
        $currency = $branch->currency ?: setting('default_currency', 'JOD');

        /** @var Reservation $reservation */
        $reservation = Reservation::factory()->create([
            'branch_id' => $branch->id,
            'reservation_type' => $state['type']->value,
            'status' => $state['status']->value,
            'table_assignment_status' => $table ? TableAssignmentStatus::Assigned->value : TableAssignmentStatus::Unassigned->value,
            'table_assignment_mode' => $table ? TableAssignmentMode::AssignNow->value : TableAssignmentMode::AssignLater->value,
            'reservation_date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'start_at' => $startAt,
            'end_at' => $endAt,
            'duration_minutes' => 90,
            'guest_count' => $state['type'] === ReservationType::LargeParty ? 8 : 4,
            'payment_status' => ReservationPaymentStatus::NotRequired->value,
            'deposit_currency' => $currency,
            'deposit_currency_rate' => CurrencyRate::for($currency),
        ]);

        ReservationStatusLog::factory()->create([
            'reservation_id' => $reservation->id,
            'old_status' => $state['status'] === ReservationStatus::Pending ? null : ReservationStatus::Pending->value,
            'new_status' => $state['status']->value,
            'changed_at' => now(),
        ]);

        if ($table) {
            ReservationTable::factory()->create([
                'reservation_id' => $reservation->id,
                'table_id' => $table->id,
                'assigned_at' => now(),
            ]);
        }

        return $reservation;
    }

    /**
     * Create demo waitlist records.
     */
    protected function createWaitlists(Branch $branch): void
    {
        ReservationWaitlist::factory()
            ->count(2)
            ->sequence(
                ['status' => ReservationWaitlistStatus::Waiting->value],
                ['status' => ReservationWaitlistStatus::Notified->value],
            )
            ->create([
                'branch_id' => $branch->id,
            ]);
    }

    /**
     * Create one demo table block for the branch.
     */
    protected function createTableBlock(Branch $branch, ?Table $table): void
    {
        ReservationTableBlock::factory()->create([
            'branch_id' => $branch->id,
            'table_id' => $table?->id,
            'title' => 'Demo maintenance block',
            'start_at' => now()->addDays(3)->setTime(15, 0),
            'end_at' => now()->addDays(3)->setTime(17, 0),
        ]);
    }

    /**
     * Create reminder records for future demo reservations.
     *
     * @param  Collection<int, Reservation>  $reservations
     */
    protected function createReminders(Collection $reservations): void
    {
        $reservations
            ->filter(fn (Reservation $reservation) => $reservation->start_at->isFuture())
            ->each(fn (Reservation $reservation) => ReservationReminder::factory()->create([
                'reservation_id' => $reservation->id,
                'scheduled_at' => $reservation->start_at->copy()->subHours(2),
                'status' => ReservationReminderStatus::Pending->value,
            ]));
    }
}
