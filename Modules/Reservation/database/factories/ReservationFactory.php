<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Branch\Models\Branch;
use Modules\Reservation\Enums\ReservationPaymentStatus;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Enums\ReservationType;
use Modules\Reservation\Enums\TableAssignmentMode;
use Modules\Reservation\Enums\TableAssignmentStatus;
use Modules\Reservation\Models\Reservation;

class ReservationFactory extends Factory
{
    /**
     * The factory model.
     *
     * @var class-string<Reservation>
     */
    protected $model = Reservation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = now()->addDays($this->faker->numberBetween(1, 10))->setTime(18, 0);
        $duration = 90;

        return [
            'branch_id' => Branch::query()->withoutGlobalActive()->inRandomOrder()->value('id') ?? Branch::factory(),
            'reservation_number' => $this->reservationNumber(),
            'reservation_type' => ReservationType::Standard->value,
            'status' => ReservationStatus::Pending->value,
            'table_assignment_status' => TableAssignmentStatus::Unassigned->value,
            'table_assignment_mode' => TableAssignmentMode::AssignLater->value,
            'reservation_date' => $date->toDateString(),
            'start_time' => $date->format('H:i:s'),
            'end_time' => $date->copy()->addMinutes($duration)->format('H:i:s'),
            'start_at' => $date,
            'end_at' => $date->copy()->addMinutes($duration),
            'duration_minutes' => $duration,
            'guest_count' => $this->faker->numberBetween(1, 6),
            'customer_name' => $this->faker->name(),
            'phone_country_iso_code' => 'JO',
            'customer_phone' => '0799123456',
            'customer_email' => $this->faker->safeEmail(),
            'payment_status' => ReservationPaymentStatus::NotRequired->value,
            'deposit_required' => false,
            'deposit_amount' => 0,
            'deposit_paid_amount' => 0,
            'deposit_currency' => 'JOD',
            'deposit_currency_rate' => 1,
        ];
    }

    /**
     * Generate a reservation number that is unique in the database.
     */
    protected function reservationNumber(): string
    {
        $prefix = 'RSV-'.now()->format('Ymd').'-';

        do {
            $number = $prefix.$this->faker->unique()->numberBetween(1000, 999999);
        } while (Reservation::query()
            ->withOutGlobalBranchPermission()
            ->where('reservation_number', $number)
            ->exists());

        return $number;
    }
}
