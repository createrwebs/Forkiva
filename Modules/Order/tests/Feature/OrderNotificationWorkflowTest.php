<?php

namespace Modules\Order\tests\Feature;

use Illuminate\Support\Facades\Notification;
use Modules\Branch\Models\Branch;
use Modules\Notification\Database\Seeders\NotificationDatabaseSeeder;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Events\OrderCreated;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Events\OrderUpdated;
use Modules\Order\Events\OrderUpdateStatus;
use Modules\Order\Events\OrderVoided;
use Modules\Order\Services\OrderNotification\OrderNotificationServiceInterface;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\User\Enums\DefaultRole;
use Tests\NotificationWorkflowTestCase;

class OrderNotificationWorkflowTest extends NotificationWorkflowTestCase
{
    public function test_order_created_event_sends_created_notification_to_matching_roles(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $kitchen = $this->createUserWithRole(DefaultRole::Kitchen->value, [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('order_created', [
            'in_app' => [
                DefaultRole::Customer->value => true,
                DefaultRole::Kitchen->value => true,
            ],
        ]);

        $order = $this->createOrder($branch, [
            'customer_id' => $customer->id,
        ]);

        event(new OrderCreated($order));

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && $payload['type'] === 'order'
                && ($payload['translations']['en']['title'] ?? null) === 'Order Created - Customer update';
        });

        Notification::assertSentTo($kitchen, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($kitchen) {
            $payload = $notification->toDatabase($kitchen);

            return $channels === ['database']
                && $payload['type'] === 'order'
                && ($payload['translations']['en']['title'] ?? null) === 'Order Created - Kitchen action';
        });
    }

    public function test_order_updated_event_sends_updated_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('order_updated', [
            'in_app' => [
                DefaultRole::Customer->value => true,
            ],
        ]);

        $order = $this->createOrder($branch, [
            'customer_id' => $customer->id,
            'total' => 25,
            'due_amount' => 25,
        ]);

        event(new OrderUpdated($order));

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && $payload['type'] === 'order'
                && ($payload['translations']['en']['title'] ?? null) === 'Order Updated - Customer update';
        });
    }

    public function test_order_status_changed_sends_role_specific_content(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $kitchen = $this->createUserWithRole(DefaultRole::Kitchen->value, [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('order_status_changed', [
            'in_app' => [
                DefaultRole::Customer->value => true,
                DefaultRole::Kitchen->value => true,
            ],
        ]);

        $order = $this->createOrder($branch, [
            'customer_id' => $customer->id,
            'status' => OrderStatus::Served,
        ]);

        event(new OrderUpdateStatus(
            order: $order,
            status: OrderStatus::Served,
        ));

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && str_contains($payload['translations']['en']['content'] ?? '', 'Your order')
                && str_contains($payload['translations']['en']['content'] ?? '', 'is now Served');
        });

        Notification::assertSentTo($kitchen, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($kitchen) {
            $payload = $notification->toDatabase($kitchen);

            return $channels === ['database']
                && str_contains($payload['translations']['en']['content'] ?? '', 'Continue the service workflow');
        });
    }

    public function test_cancelled_order_sends_specific_notification_without_status_changed_duplicate(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $cashier = $this->createUserWithRole(DefaultRole::Cashier->value, [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('order_cancelled', [
            'in_app' => [
                DefaultRole::Customer->value => true,
                DefaultRole::Cashier->value => true,
            ],
        ]);
        $this->setWorkflowChannels('order_status_changed', [
            'in_app' => [
                DefaultRole::Customer->value => true,
                DefaultRole::Cashier->value => true,
            ],
        ]);

        $order = $this->createOrder($branch, [
            'customer_id' => $customer->id,
            'cashier_id' => $cashier->id,
            'status' => OrderStatus::Cancelled,
        ]);

        event(new OrderUpdateStatus(
            order: $order,
            status: OrderStatus::Cancelled,
            changedById: $cashier->id,
            note: 'Customer requested cancellation',
        ));

        event(new OrderVoided(
            order: $order,
            status: OrderStatus::Cancelled,
            changedById: $cashier->id,
            note: 'Customer requested cancellation',
        ));

        Notification::assertSentToTimes($customer, RenderedTemplateNotification::class, 1);
        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && $payload['type'] === 'order'
                && ($payload['translations']['en']['title'] ?? null) === 'Order Cancelled - Customer update';
        });
    }

    public function test_order_paid_event_sends_payment_completed_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $cashier = $this->createUserWithRole(DefaultRole::Cashier->value, [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('order_payment_completed', [
            'in_app' => [
                DefaultRole::Customer->value => true,
                DefaultRole::Cashier->value => true,
            ],
        ]);

        $order = $this->createOrder($branch, [
            'customer_id' => $customer->id,
            'cashier_id' => $cashier->id,
            'status' => OrderStatus::Completed,
            'payment_status' => OrderPaymentStatus::Paid,
            'total' => 40,
            'due_amount' => 0,
        ]);

        $order->payments()->create([
            'branch_id' => $branch->id,
            'order_reference_no' => $order->reference_no,
            'cashier_id' => $cashier->id,
            'method' => PaymentMethod::Cash,
            'amount' => 40,
            'currency' => $order->currency,
            'currency_rate' => 1,
            'type' => PaymentType::Payment,
            'received_at' => now(),
        ]);

        event(new OrderPaid($order));

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && $payload['type'] === 'order'
                && ($payload['translations']['en']['title'] ?? null) === 'Order Payment Completed - Customer update';
        });
    }

    public function test_ready_order_sends_kitchen_item_ready_without_status_changed_duplicate(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $kitchen = $this->createUserWithRole(DefaultRole::Kitchen->value, [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('kitchen_item_ready', [
            'in_app' => [
                DefaultRole::Customer->value => true,
                DefaultRole::Kitchen->value => true,
            ],
        ]);
        $this->setWorkflowChannels('order_status_changed', [
            'in_app' => [
                DefaultRole::Customer->value => true,
                DefaultRole::Kitchen->value => true,
            ],
        ]);

        $order = $this->createOrder($branch, [
            'customer_id' => $customer->id,
            'status' => OrderStatus::Ready,
        ]);
        $order->previous_status = OrderStatus::Preparing;

        event(new OrderUpdateStatus(
            order: $order,
            status: OrderStatus::Ready,
            changedById: $kitchen->id,
        ));

        Notification::assertSentToTimes($customer, RenderedTemplateNotification::class, 1);
        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && ($payload['translations']['en']['title'] ?? null) === 'Kitchen Item Ready - Customer update';
        });
    }

    public function test_order_voided_template_is_used_for_generic_void_statuses(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('order_voided', [
            'in_app' => [
                DefaultRole::Customer->value => true,
            ],
        ]);

        $order = $this->createOrder($branch, [
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
        ]);

        app(OrderNotificationServiceInterface::class)
            ->sendVoided(new OrderVoided(
                order: $order,
                status: OrderStatus::Completed,
                note: 'Generic void',
            ));

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && ($payload['translations']['en']['title'] ?? null) === 'Order Voided - Customer update';
        });
    }
}
