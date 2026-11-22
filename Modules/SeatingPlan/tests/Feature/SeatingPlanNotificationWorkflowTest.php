<?php

namespace Modules\SeatingPlan\tests\Feature;

use Illuminate\Support\Facades\Notification;
use Modules\Branch\Models\Branch;
use Modules\Notification\Database\Seeders\NotificationDatabaseSeeder;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Modules\SeatingPlan\Enums\TableShape;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Models\Floor;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Models\Zone;
use Modules\SeatingPlan\Services\TableViewer\TableViewerServiceInterface;
use Modules\User\Enums\DefaultRole;
use Tests\NotificationWorkflowTestCase;

class SeatingPlanNotificationWorkflowTest extends NotificationWorkflowTestCase
{
    public function test_table_assigned_to_waiter_sends_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $waiter = $this->createUserWithRole(DefaultRole::Waiter->value, [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $table = $this->tableFixture($branch, TableStatus::Available);

        $this->setWorkflowChannels('table_assigned_to_waiter', [
            'in_app' => [
                DefaultRole::Waiter->value => true,
            ],
        ]);

        app(TableViewerServiceInterface::class)->assignWaiter($table->id, [
            'waiter_id' => $waiter->id,
        ]);

        Notification::assertSentTo($waiter, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($waiter) {
            $payload = $notification->toDatabase($waiter);

            return $channels === ['database']
                && $payload['type'] === 'table'
                && $this->notificationTitleMatchesBase($payload, 'Table Assigned to Waiter');
        });
    }

    public function test_table_status_changed_sends_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $admin = $this->createUserWithRole(DefaultRole::Admin->value, [
            'branch_id' => null,
            'is_active' => true,
        ]);
        $waiter = $this->createUserWithRole(DefaultRole::Waiter->value, [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $table = $this->tableFixture($branch, TableStatus::Cleaning, $waiter->id);

        $this->setWorkflowChannels('table_status_changed', [
            'in_app' => [
                DefaultRole::Waiter->value => true,
            ],
        ]);

        $this->actingAs($admin);

        app(TableViewerServiceInterface::class)->makeAsAvailable($table->id);

        Notification::assertSentTo($waiter, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($waiter) {
            $payload = $notification->toDatabase($waiter);

            return $channels === ['database']
                && $payload['type'] === 'table'
                && $this->notificationTitleMatchesBase($payload, 'Table Status Changed');
        });
    }

    /**
     * Create a table fixture with floor and zone.
     */
    protected function tableFixture(Branch $branch, TableStatus $status, ?int $waiterId = null): Table
    {
        $floor = Floor::factory()->create([
            'branch_id' => $branch->id,
            'name' => [
                'en' => 'Main Floor',
                'ar' => 'الطابق الرئيسي',
            ],
            'is_active' => true,
        ]);

        $zone = Zone::factory()->create([
            'branch_id' => $branch->id,
            'floor_id' => $floor->id,
            'name' => [
                'en' => 'Indoor',
                'ar' => 'داخلي',
            ],
            'is_active' => true,
        ]);

        return Table::factory()->create([
            'branch_id' => $branch->id,
            'floor_id' => $floor->id,
            'zone_id' => $zone->id,
            'assigned_waiter_id' => $waiterId,
            'name' => [
                'en' => 'T1',
                'ar' => 'ط1',
            ],
            'shape' => TableShape::Square->value,
            'status' => $status->value,
            'is_active' => true,
        ]);
    }
}
