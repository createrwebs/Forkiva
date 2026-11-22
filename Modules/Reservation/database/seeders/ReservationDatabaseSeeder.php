<?php

namespace Modules\Reservation\Database\Seeders;

use App\Forkiva;
use Illuminate\Database\Seeder;
use Modules\Branch\Models\Branch;
use Modules\Reservation\Models\ReservationSetting;

class ReservationDatabaseSeeder extends Seeder
{
    /**
     * Run the Reservation module seeds.
     */
    public function run(): void
    {
        Branch::query()
            ->withoutGlobalActive()
            ->whereNotIn('id', ReservationSetting::query()->pluck('branch_id'))
            ->get(['id'])
            ->each(fn (Branch $branch) => ReservationSetting::factory()->create([
                'branch_id' => $branch->id,
            ]));

        if (Forkiva::seedDemoData()) {
            $this->call([
                ReservationDemoSeeder::class,
            ]);
        }
    }
}
