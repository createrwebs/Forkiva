<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservation\Enums\ReservationReminderStatus;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationReminder;

class ReservationReminderFactory extends Factory
{
    /**
     * The factory model.
     *
     * @var class-string<ReservationReminder>
     */
    protected $model = ReservationReminder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::query()->withOutGlobalBranchPermission()->inRandomOrder()->value('id') ?? Reservation::factory(),
            'reminder_type' => 'before_reservation',
            'channel' => 'workflow',
            'scheduled_at' => now()->addHour(),
            'status' => ReservationReminderStatus::Pending->value,
        ];
    }
}
