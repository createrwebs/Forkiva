<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationStatusLog;

class ReservationStatusLogFactory extends Factory
{
    /**
     * The factory model.
     *
     * @var class-string<ReservationStatusLog>
     */
    protected $model = ReservationStatusLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::query()
                ->withOutGlobalBranchPermission()
                ->inRandomOrder()->value('id') ?? Reservation::factory(),
            'old_status' => ReservationStatus::Pending->value,
            'new_status' => ReservationStatus::Confirmed->value,
            'changed_by' => null,
            'changed_at' => now(),
            'note' => $this->faker->sentence(),
        ];
    }
}
