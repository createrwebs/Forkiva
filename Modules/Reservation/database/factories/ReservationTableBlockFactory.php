<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Branch\Models\Branch;
use Modules\Reservation\Models\ReservationTableBlock;
use Modules\SeatingPlan\Models\Table;
use Modules\User\Models\User;

class ReservationTableBlockFactory extends Factory
{
    /**
     * The factory model.
     *
     * @var class-string<ReservationTableBlock>
     */
    protected $model = ReservationTableBlock::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::query()->withoutGlobalActive()->inRandomOrder()->value('id') ?? Branch::factory(),
            'table_id' => Table::query()->withOutGlobalBranchPermission()->withoutGlobalActive()->inRandomOrder()->value('id') ?? Table::factory(),
            'title' => [
                'en' => $this->faker->sentence(3),
                'ar' => $this->faker->sentence(3),
            ],
            'reason' => $this->faker->sentence(),
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(2),
            'created_by' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
        ];
    }
}
