<?php

namespace Modules\Notification\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Branch\Models\Branch;
use Modules\Hr\Models\Employee;
use Modules\Notification\Database\Seeders\NotificationDatabaseSeeder;
use Modules\Notification\Enums\NotificationContext;
use Modules\Notification\Enums\NotificationPushTokenClient;
use Modules\Notification\Models\NotificationPushToken;
use Modules\Notification\Models\NotificationWorkflow;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Transformers\Api\V1\NotificationResource;
use Modules\Service\Enums\PushNotificationProvider;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;
use Tests\NotificationWorkflowTestCase;

class NotificationWorkflowDispatchTest extends NotificationWorkflowTestCase
{
    public function test_dispatcher_skips_channels_disabled_in_service_settings(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $this->fakeServiceRegistry(['email', 'in_app'], ['email']);
        $user = $this->createCustomerUser(['is_active' => true]);

        app(NotificationDispatcherServiceInterface::class)->send('profile_updated', $user, $this->profileVariables());

        Notification::assertSentTo($user, RenderedTemplateNotification::class, function ($notification, array $channels) {
            return $channels === ['database'];
        });
    }

    public function test_dispatcher_skips_workflow_disabled_channels(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        NotificationWorkflow::query()
            ->where('key', 'profile_updated')
            ->firstOrFail()
            ->update([
                'channels' => [
                    'email' => false,
                    'in_app' => true,
                ],
            ]);

        $user = $this->createCustomerUser(['is_active' => true]);

        app(NotificationDispatcherServiceInterface::class)->send('profile_updated', $user, $this->profileVariables());

        Notification::assertSentTo($user, RenderedTemplateNotification::class, function ($notification, array $channels) {
            return $channels === ['database'];
        });
    }

    public function test_dispatcher_filters_channels_by_notifiable_roles(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        NotificationWorkflow::query()
            ->where('key', 'order_created')
            ->firstOrFail()
            ->update([
                'channels' => [
                    'email' => [
                        DefaultRole::Customer->value => true,
                        DefaultRole::Kitchen->value => false,
                    ],
                    'in_app' => [
                        DefaultRole::Customer->value => false,
                        DefaultRole::Kitchen->value => true,
                    ],
                ],
            ]);

        $customer = $this->createUserWithRole(DefaultRole::Customer->value, ['is_active' => true]);
        $kitchen = $this->createUserWithRole(DefaultRole::Kitchen->value, ['is_active' => true]);

        app(NotificationDispatcherServiceInterface::class)->send('order_created', collect([$customer, $kitchen]), [
            'order_number' => '1001',
            'customer_name' => 'Ayman',
            'branch_name' => 'Main',
            'order_total' => '10.00',
            'currency' => 'JOD',
            'order_type' => 'Dine in',
            'order_status' => 'Pending',
            'app_name' => 'Forkiva',
        ]);

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function ($notification, array $channels) {
            return $channels === ['mail'];
        });

        Notification::assertSentTo($kitchen, RenderedTemplateNotification::class, function ($notification, array $channels) {
            return $channels === ['database'];
        });
    }

    public function test_driver_role_without_active_driver_profile_does_not_receive_driver_workflow(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create();
        $driverRoleOnly = $this->createUserWithRole(DefaultRole::Driver->value, [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('delivery_assigned', [
            'in_app' => [
                DefaultRole::Driver->value => true,
            ],
        ]);

        app(NotificationDispatcherServiceInterface::class)->send(
            'delivery_assigned',
            $driverRoleOnly,
            $this->deliveryAssignmentVariables(),
        );

        Notification::assertNotSentTo($driverRoleOnly, RenderedTemplateNotification::class);
    }

    public function test_active_driver_profile_receives_driver_workflow_with_driver_context_without_driver_role(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $driverUser = User::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $this->createDeliveryDriver($branch, $driverUser);

        $this->setWorkflowChannels('delivery_assigned', [
            'in_app' => [
                DefaultRole::Driver->value => true,
            ],
        ]);

        app(NotificationDispatcherServiceInterface::class)->send(
            'delivery_assigned',
            $driverUser,
            $this->deliveryAssignmentVariables(),
        );

        Notification::assertSentTo($driverUser, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($driverUser) {
            return $channels === ['database']
                && ($notification->toDatabase($driverUser)['app_context'] ?? null) === NotificationContext::Driver->value;
        });
    }

    public function test_dispatch_preloads_profile_contexts_for_bulk_driver_recipients(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $driverUsers = collect(range(1, 3))->map(function () use ($branch) {
            $user = User::factory()->create([
                'branch_id' => $branch->id,
                'is_active' => true,
            ]);
            $this->createDeliveryDriver($branch, $user);

            return $user;
        });

        $this->setWorkflowChannels('delivery_assigned', [
            'in_app' => [
                DefaultRole::Driver->value => true,
            ],
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(NotificationDispatcherServiceInterface::class)->send(
            'delivery_assigned',
            $driverUsers,
            $this->deliveryAssignmentVariables(),
        );

        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->map(fn (string $query) => strtolower($query));
        DB::disableQueryLog();

        $employeeQueries = $queries->filter(fn (string $query) => str_contains($query, 'employees'))->count();
        $driverQueries = $queries->filter(fn (string $query) => str_contains($query, 'delivery_drivers'))->count();

        $this->assertLessThanOrEqual(1, $employeeQueries);
        $this->assertLessThanOrEqual(1, $driverQueries);
    }

    public function test_staff_role_without_active_employee_profile_does_not_receive_employee_workflow(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $staffRoleOnly = $this->createUserWithRole(DefaultRole::Staff->value, ['is_active' => true]);

        $this->setWorkflowChannels('employee_created', [
            'in_app' => [
                DefaultRole::Staff->value => true,
            ],
        ]);

        app(NotificationDispatcherServiceInterface::class)->send(
            'employee_created',
            $staffRoleOnly,
            $this->employeeVariables(),
        );

        Notification::assertNotSentTo($staffRoleOnly, RenderedTemplateNotification::class);
    }

    public function test_active_employee_profile_receives_employee_workflow_with_employee_context_without_staff_role(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        Employee::factory()->forBranch($branch->id)->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('employee_created', [
            'in_app' => [
                DefaultRole::Staff->value => true,
            ],
        ]);

        app(NotificationDispatcherServiceInterface::class)->send('employee_created', $user, $this->employeeVariables());

        Notification::assertSentTo($user, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($user) {
            return $channels === ['database']
                && ($notification->toDatabase($user)['app_context'] ?? null) === NotificationContext::Employee->value;
        });
    }

    public function test_dispatcher_splits_driver_context_from_other_recipients(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser(['branch_id' => $branch->id, 'is_active' => true]);
        $driverUser = $this->createUserWithRole(DefaultRole::Staff->value, [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $this->createDeliveryDriver($branch, $driverUser);

        $this->setWorkflowChannels('delivery_assigned', [
            'in_app' => [
                DefaultRole::Customer->value => true,
                DefaultRole::Driver->value => true,
            ],
        ]);

        app(NotificationDispatcherServiceInterface::class)->send('delivery_assigned', collect([$customer, $driverUser]), $this->deliveryAssignmentVariables());

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && ($payload['app_context'] ?? null) === NotificationContext::Other->value
                && ($payload['translations']['en']['content'] ?? null) === 'Your order 04 has been assigned to Ayman Driver. ETA: 10:00.';
        });

        Notification::assertSentTo($driverUser, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($driverUser) {
            $payload = $notification->toDatabase($driverUser);

            return $channels === ['database']
                && ($payload['app_context'] ?? null) === NotificationContext::Driver->value
                && ($payload['translations']['en']['content'] ?? null) === 'New delivery 501 for order 04 assigned to you. Pickup: Main Branch, Amman. Delivery: Customer Street, Amman. ETA: 10:00.';
        });
    }

    public function test_push_routing_sends_driver_context_to_web_and_driver_app_tokens_only(): void
    {
        $user = $this->createCustomerUser(['is_active' => true]);
        $this->createPushTokens($user);

        $tokens = $user->routeNotificationForFcm(
            $this->pushNotification(NotificationContext::Driver->value)
        );
        sort($tokens);

        $this->assertSame(['driver-token', 'web-token'], $tokens);
    }

    public function test_push_routing_uses_loaded_push_tokens_without_extra_queries(): void
    {
        $user = $this->createCustomerUser(['is_active' => true]);
        $this->createPushTokens($user);
        $user->load('pushTokens');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $tokens = $user->routeNotificationForFcm(
            $this->pushNotification(NotificationContext::Driver->value)
        );
        sort($tokens);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(['driver-token', 'web-token'], $tokens);
        $this->assertCount(0, $queries);
    }

    public function test_push_routing_sends_employee_context_to_web_and_employee_app_tokens_only(): void
    {
        $user = $this->createCustomerUser(['is_active' => true]);
        $this->createPushTokens($user);

        $tokens = $user->routeNotificationForFcm(
            $this->pushNotification(NotificationContext::Employee->value)
        );
        sort($tokens);

        $this->assertSame(['employee-token', 'web-token'], $tokens);
    }

    public function test_push_routing_sends_other_context_to_web_tokens_only(): void
    {
        $user = $this->createCustomerUser(['is_active' => true]);
        $this->createPushTokens($user);

        $tokens = $user->routeNotificationForFcm(
            $this->pushNotification(NotificationContext::Other->value)
        );

        $this->assertSame(['web-token'], $tokens);
    }

    public function test_push_token_register_and_delete(): void
    {
        $user = $this->createCustomerUser(['is_active' => true]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/notification-push-tokens', [
                'provider' => 'fcm',
                'token' => 'fcm-token',
                'device_id' => 'device-1',
                'device_name' => 'Ayman iPhone',
                'platform' => 'ios',
                'client' => 'driver_app',
            ])
            ->assertOk()
            ->assertJsonPath('body.provider', 'fcm')
            ->assertJsonPath('body.device_id', 'device-1')
            ->assertJsonPath('body.client', 'driver_app');

        $id = $response->json('body.id');

        $this->assertDatabaseHas('notification_push_tokens', [
            'id' => $id,
            'user_id' => $user->id,
            'provider' => 'fcm',
            'token' => 'fcm-token',
            'client' => 'driver_app',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/notification-push-tokens/$id")
            ->assertOk();

        $this->assertDatabaseMissing('notification_push_tokens', [
            'id' => $id,
        ]);
    }

    public function test_database_notification_payload_stores_translations_and_resource_returns_unified_payload(): void
    {
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $user = $this->createCustomerUser(['is_active' => true]);

        app(NotificationDispatcherServiceInterface::class)->send('profile_updated', $user, $this->profileVariables(), 'ar');

        $notification = $user->notifications()->firstOrFail();
        $data = $notification->data;

        $this->assertSame('profile_updated', $data['type']);
        $this->assertNull($data['type_id']);
        $this->assertSame('Profile Updated - Customer update', $data['translations']['en']['title']);
        $this->assertSame('تحديث الملف الشخصي - للعميل', $data['translations']['ar']['title']);
        $this->assertSame('This update is for your record and next steps. - Forkiva Your profile information was updated.', $data['translations']['en']['content']);
        $this->assertSame('هذا التحديث خاص بسجلك والخطوات التالية. - Forkiva تم تحديث معلومات ملفك الشخصي.', $data['translations']['ar']['content']);
        $this->assertArrayNotHasKey('locale', $data);
        $this->assertArrayNotHasKey('title', $data);
        $this->assertArrayNotHasKey('subject', $data);
        $this->assertArrayNotHasKey('content', $data);
        $this->assertArrayNotHasKey('template_key', $data);
        $this->assertArrayNotHasKey('channel_key', $data);
        $this->assertArrayNotHasKey('deeplink', $data);
        $this->assertArrayNotHasKey('action', $data);

        app()->setLocale('en');
        $resource = (new NotificationResource($notification))->resolve(request());

        $this->assertSame('profile_updated', $resource['type']);
        $this->assertNull($resource['type_id']);
        $this->assertSame('Profile Updated - Customer update', $resource['title']);
        $this->assertSame('This update is for your record and next steps. - Forkiva Your profile information was updated.', $resource['content']);
        $this->assertArrayNotHasKey('locale', $resource);
        $this->assertArrayNotHasKey('notification_class', $resource);
        $this->assertArrayNotHasKey('translations', $resource);
        $this->assertArrayNotHasKey('template_key', $resource);
        $this->assertArrayNotHasKey('channel_key', $resource);
        $this->assertArrayNotHasKey('deeplink', $resource);
        $this->assertArrayNotHasKey('action', $resource);
    }

    /**
     * Get delivery-assignment template variables.
     *
     * @return array<string, mixed>
     */
    protected function deliveryAssignmentVariables(): array
    {
        return [
            'type' => 'delivery_assigned',
            'type_id' => 501,
            'delivery_number' => '501',
            'order_number' => '04',
            'driver_name' => 'Ayman Driver',
            'eta' => '10:00',
            'pickup_address' => 'Main Branch, Amman',
            'delivery_address' => 'Customer Street, Amman',
            'app_name' => 'Forkiva',
        ];
    }

    /**
     * Get employee template variables.
     *
     * @return array<string, mixed>
     */
    protected function employeeVariables(): array
    {
        return [
            'employee_name' => 'Ayman Employee',
            'employee_code' => 'EMP-01',
            'branch_name' => 'Main',
            'start_date' => '2026-06-22',
            'app_name' => 'Forkiva',
        ];
    }

    /**
     * Create push tokens for all supported clients.
     */
    protected function createPushTokens(User $user): void
    {
        foreach (
            [
                NotificationPushTokenClient::Web->value => 'web-token',
                NotificationPushTokenClient::DriverApp->value => 'driver-token',
                NotificationPushTokenClient::EmployeeApp->value => 'employee-token',
            ] as $client => $token
        ) {
            NotificationPushToken::query()->create([
                'user_id' => $user->id,
                'provider' => PushNotificationProvider::Fcm->value,
                'token' => $token,
                'client' => $client,
            ]);
        }
    }

    /**
     * Build a push notification for one app context.
     */
    protected function pushNotification(string $appContext): RenderedTemplateNotification
    {
        return new RenderedTemplateNotification([
            'push_notification' => [
                'type' => 'profile_updated',
                'type_id' => null,
                'app_context' => $appContext,
                'translations' => [
                    'en' => [
                        'title' => 'Notification',
                        'subject' => null,
                        'content' => 'Notification content.',
                    ],
                ],
            ],
        ], 'Forkiva');
    }
}
