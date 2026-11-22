<?php

namespace Modules\Pos\tests\Feature;

use Illuminate\Support\Facades\Notification;
use Modules\Branch\Models\Branch;
use Modules\Notification\Database\Seeders\NotificationDatabaseSeeder;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Events\ClosePosSession;
use Modules\Pos\Events\OpenPosSession;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\User\Enums\DefaultRole;
use Tests\NotificationWorkflowTestCase;

class PosNotificationWorkflowTest extends NotificationWorkflowTestCase
{
    public function test_pos_session_opened_event_sends_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $cashier = $this->createUserWithRole(DefaultRole::Cashier->value, [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $register = PosRegister::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $this->setWorkflowChannels('pos_session_opened', [
            'in_app' => [
                DefaultRole::Cashier->value => true,
            ],
        ]);

        $session = PosSession::factory()->create([
            'branch_id' => $branch->id,
            'pos_register_id' => $register->id,
            'opened_by' => $cashier->id,
            'status' => PosSessionStatus::Open,
            'opening_float' => 20,
            'opened_at' => now(),
        ]);

        event(new OpenPosSession($session));

        Notification::assertSentTo($cashier, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($cashier) {
            $payload = $notification->toDatabase($cashier);

            return $channels === ['database']
                && $this->notificationTitleMatchesBase($payload, 'POS Session Opened');
        });
    }

    public function test_pos_session_closed_event_sends_closed_and_variance_notifications(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $cashier = $this->createUserWithRole(DefaultRole::Cashier->value, [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $register = PosRegister::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $this->setWorkflowChannels('pos_session_closed', [
            'in_app' => [
                DefaultRole::Cashier->value => true,
            ],
        ]);
        $this->setWorkflowChannels('pos_cash_variance_found', [
            'in_app' => [
                DefaultRole::Cashier->value => true,
            ],
        ]);

        $session = PosSession::factory()->create([
            'branch_id' => $branch->id,
            'pos_register_id' => $register->id,
            'opened_by' => $cashier->id,
            'closed_by' => $cashier->id,
            'status' => PosSessionStatus::Closed,
            'opened_at' => now()->subHour(),
            'closed_at' => now(),
            'opening_float' => 20,
            'declared_cash' => 105,
            'system_cash_sales' => 100,
            'cash_over_short' => 5,
            'orders_count' => 3,
        ]);

        event(new ClosePosSession($session));

        Notification::assertSentToTimes($cashier, RenderedTemplateNotification::class, 2);
        Notification::assertSentTo($cashier, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($cashier) {
            $payload = $notification->toDatabase($cashier);

            return $channels === ['database']
                && $this->notificationTitleMatchesBase($payload, 'POS Session Closed');
        });
        Notification::assertSentTo($cashier, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($cashier) {
            $payload = $notification->toDatabase($cashier);

            return $channels === ['database']
                && $this->notificationTitleMatchesBase($payload, 'POS Cash Variance Detected');
        });
    }
}
