<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationTable;
use Modules\SeatingPlan\Models\Table;

class ReservationTableFactory extends Factory
{
    /**
     * The factory model.
     *
     * @var class-string<ReservationTable>
     */
    protected $model = ReservationTable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::query()->withOutGlobalBranchPermission()->inRandomOrder()->value('id') ?? Reservation::factory(),
            'table_id' => Table::query()->withOutGlobalBranchPermission()->withoutGlobalActive()->inRandomOrder()->value('id') ?? Table::factory(),
            'assigned_at' => now(),
            'assigned_by' => null,
        ];
    }
}
