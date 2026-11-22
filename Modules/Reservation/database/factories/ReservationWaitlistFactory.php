<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Branch\Models\Branch;
use Modules\Reservation\Enums\ReservationWaitlistStatus;
use Modules\Reservation\Models\ReservationWaitlist;

class ReservationWaitlistFactory extends Factory
{
    /**
     * The factory model.
     *
     * @var class-string<ReservationWaitlist>
     */
    protected $model = ReservationWaitlist::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::query()->withoutGlobalActive()->inRandomOrder()->value('id') ?? Branch::factory(),
            'phone_country_iso_code' => 'JO',
            'customer_name' => $this->faker->name(),
            'customer_phone' => '0799123456',
            'customer_email' => $this->faker->safeEmail(),
            'preferred_date' => now()->addDay()->toDateString(),
            'preferred_start_time' => '08:00',
            'preferred_end_time' => '23:00',
            'guest_count' => $this->faker->numberBetween(1, 8),
            'estimated_wait_minutes' => 30,
            'priority' => 0,
            'status' => ReservationWaitlistStatus::Waiting->value,
        ];
    }
}
