<?php

namespace Modules\Report\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Branch\Models\Branch;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Report\Report;
use Modules\Report\Reports\BranchPerformanceReport;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportQueryCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public static function reports(): iterable
    {
        $root = dirname(__DIR__, 2).'/app/Reports/';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $class = 'Modules\\Report\\Reports\\'.str_replace('/', '\\', substr($file->getPathname(), strlen($root), -4));
            if (! is_subclass_of($class, Report::class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            foreach ([null, 'days', 'weeks', 'months', 'years'] as $group) {
                yield $class.' '.($group ?? 'ungrouped') => [$class, $group];
            }
        }
    }

    #[DataProvider('reports')]
    public function test_report_query_executes(string $class, ?string $group): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $this->actingAs(User::factory()->create(['branch_id' => $branch->id]));
        $request = new Request(['filters' => array_filter(['group_by_date' => $group, 'from' => '2026-01-01', 'to' => '2026-12-31'])]);
        $report = app($class);
        $report->query($request)->limit(5)->get();
        $result = $report->query($request)->paginate(5);
        $this->assertGreaterThanOrEqual(0, $result->total());
    }

    public function test_daily_report_preserves_totals_and_branch_scope(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $otherBranch = Branch::factory()->create(['currency' => 'JOD']);
        foreach ([[$branch, '2026-09-01', 10], [$branch, '2026-09-01', 20], [$branch, '2026-09-02', 40], [$otherBranch, '2026-09-01', 900]] as $index => [$owner, $date, $total]) {
            Order::withoutEvents(fn () => Order::query()->create([
                'branch_id' => $owner->id,
                'order_number' => 'REPORT-'.$index,
                'reference_no' => 'ORD-REPORT-'.$index,
                'status' => OrderStatus::Completed,
                'type' => OrderType::Takeaway,
                'payment_status' => OrderPaymentStatus::Paid,
                'currency' => 'JOD',
                'currency_rate' => 1,
                'subtotal' => $total,
                'total' => $total,
                'due_amount' => 0,
                'guest_count' => 1,
                'order_date' => $date,
                'created_at' => $date,
            ]));
        }
        $this->actingAs(User::factory()->create(['branch_id' => $branch->id]));
        $report = app(BranchPerformanceReport::class);
        $rows = $report->query(new Request(['filters' => ['group_by_date' => 'days']]))->get();
        $this->assertCount(2, $rows);
        $this->assertSame([40.0, 30.0], $rows->map(fn ($row) => (float) $row->getRawOriginal('total'))->all());
        $this->assertSame([1, 2], $rows->pluck('total_orders')->map(fn ($value) => (int) $value)->all());
    }
}
