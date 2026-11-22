<?php

namespace Modules\Support\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Branch\Models\Branch;
use Modules\Category\Models\Category;
use Modules\Category\Services\Category\CategoryService;
use Modules\Menu\Models\Menu;
use Modules\Order\Enums\ReasonType;
use Modules\Order\Models\Reason;
use Modules\Reservation\Models\ReservationTableBlock;
use Spatie\Activitylog\ActivityLogStatus;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function refreshApplication(): void
    {
        putenv('APP_INSTALLED=false');
        $_ENV['APP_INSTALLED'] = 'false';
        $_SERVER['APP_INSTALLED'] = 'false';

        parent::refreshApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['activitylog.enabled' => true]);
        app(ActivityLogStatus::class)->enable();
    }

    public function test_mass_delete_on_activity_logged_model_records_deleted_activity(): void
    {
        $first = $this->createReason('Damaged item');
        $second = $this->createReason('Customer cancelled');

        Activity::query()->delete();

        $deleted = Reason::query()
            ->whereIn('id', [$first->id, $second->id])
            ->delete();

        $this->assertSame(2, $deleted);

        foreach ([$first, $second] as $reason) {
            $this->assertDatabaseHas('activity_log', [
                'subject_type' => Reason::class,
                'subject_id' => $reason->id,
                'event' => 'deleted',
                'log_name' => 'reasons.deleted',
            ]);
        }
    }

    public function test_mass_delete_on_activity_logged_model_without_tag_cache_records_deleted_activity(): void
    {
        $branch = Branch::query()->create([
            'name' => ['en' => 'Main Branch'],
            'country_code' => 'JO',
            'timezone' => 'Asia/Amman',
            'currency' => 'JOD',
            'order_types' => [],
            'payment_methods' => [],
            'is_active' => true,
            'is_main' => true,
        ]);

        $first = $this->createReservationTableBlock($branch, 'Maintenance');
        $second = $this->createReservationTableBlock($branch, 'Private event');

        Activity::query()->delete();

        $deleted = ReservationTableBlock::query()
            ->whereIn('id', [$first->id, $second->id])
            ->delete();

        $this->assertSame(2, $deleted);

        foreach ([$first, $second] as $block) {
            $this->assertDatabaseHas('activity_log', [
                'subject_type' => ReservationTableBlock::class,
                'subject_id' => $block->id,
                'event' => 'deleted',
                'log_name' => 'reservation_table_blocks.deleted',
            ]);
        }
    }

    public function test_category_service_bulk_delete_records_deleted_activity(): void
    {
        $firstId = $this->insertCategory('Food', 1, 2);
        $secondId = $this->insertCategory('Drinks', 3, 4);

        Activity::query()->delete();

        $deleted = (new CategoryService)->destroy([$firstId, $secondId]);

        $this->assertTrue($deleted);

        foreach ([$firstId, $secondId] as $categoryId) {
            $this->assertDatabaseHas('activity_log', [
                'subject_type' => Category::class,
                'subject_id' => $categoryId,
                'event' => 'deleted',
                'log_name' => 'categories.deleted',
            ]);
        }
    }

    private function createReason(string $name): Reason
    {
        return Reason::query()->create([
            'name' => ['en' => $name],
            'type' => ReasonType::Cancellation,
            'is_active' => true,
        ]);
    }

    private function createReservationTableBlock(Branch $branch, string $title): ReservationTableBlock
    {
        return ReservationTableBlock::query()->create([
            'branch_id' => $branch->id,
            'title' => ['en' => $title],
            'reason' => $title,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(2),
        ]);
    }

    private function insertCategory(string $name, int $left, int $right): int
    {
        $menu = Menu::factory()->create([
            'branch_id' => Branch::factory()->create()->id,
            'is_active' => true,
        ]);

        return DB::table('categories')->insertGetId([
            'created_by' => null,
            'menu_id' => $menu->id,
            'parent_id' => null,
            'name' => json_encode(['en' => $name]),
            'slug' => str($name)->slug()->toString(),
            'order' => 0,
            'is_active' => true,
            '_lft' => $left,
            '_rgt' => $right,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
