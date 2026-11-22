<?php

namespace Modules\Support\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Support\Traits\HasFilters;
use Tests\TestCase;

class HasFiltersDateRangeTest extends TestCase
{
    public function test_date_scopes_use_sargable_half_open_predicates(): void
    {
        $model = new class extends Model
        {
            use HasFilters;

            protected $table = 'filter_test_records';
        };

        $query = $model->newQuery()
            ->whereFromDate('2026-09-01')
            ->whereToDate('2026-09-30')
            ->whereUpdatedFromDate('2026-08-01')
            ->whereUpdatedToDate('2026-08-31');

        $this->assertStringNotContainsString('date(', strtolower($query->toSql()));
        $this->assertSame(
            ['2026-09-01', '2026-10-01', '2026-08-01', '2026-09-01'],
            $query->getBindings(),
        );
    }

    public function test_half_open_bounds_include_the_whole_end_day_for_dates_and_timestamps(): void
    {
        Schema::create('filter_test_records', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->index();
            $table->dateTime('occurred_at')->index();
        });

        try {
            DB::table('filter_test_records')->insert([
                ['business_date' => '2026-09-01', 'occurred_at' => '2026-09-01 00:00:00'],
                ['business_date' => '2026-09-30', 'occurred_at' => '2026-09-30 23:59:59'],
                ['business_date' => '2026-10-01', 'occurred_at' => '2026-10-01 00:00:00'],
            ]);

            $model = new class extends Model
            {
                use HasFilters;

                public $timestamps = false;

                protected $table = 'filter_test_records';
            };

            $dateQuery = $model->newQuery()
                ->whereFromDate('2026-09-01', 'business_date')
                ->whereToDate('2026-09-30', 'business_date');
            $timestampQuery = $model->newQuery()
                ->whereFromDate('2026-09-01', 'occurred_at')
                ->whereToDate('2026-09-30', 'occurred_at');

            $postgres = DB::getDriverName() === 'pgsql';
            $explain = match (DB::getDriverName()) {
                'sqlite' => 'EXPLAIN QUERY PLAN ',
                'pgsql' => 'EXPLAIN ',
                default => 'EXPLAIN FORMAT=JSON ',
            };
            // Tiny fixtures prefer a sequential scan; verify that the range can use its index.
            if ($postgres) {
                DB::statement('SET enable_seqscan = off');
            }
            $datePlan = json_encode(DB::select(
                $explain.$dateQuery->toSql(),
                $dateQuery->getBindings(),
            ));
            $timestampPlan = json_encode(DB::select(
                $explain.$timestampQuery->toSql(),
                $timestampQuery->getBindings(),
            ));

            $this->assertStringContainsString('filter_test_records_business_date_index', $datePlan);
            $this->assertStringContainsString('filter_test_records_occurred_at_index', $timestampPlan);
            $this->assertSame([1, 2], $dateQuery->pluck('id')->all());
            $this->assertSame([1, 2], $timestampQuery->pluck('id')->all());
        } finally {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('RESET enable_seqscan');
            }
            Schema::dropIfExists('filter_test_records');
        }
    }
}
