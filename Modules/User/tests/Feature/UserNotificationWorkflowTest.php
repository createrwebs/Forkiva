<?php

namespace Modules\User\tests\Feature;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\Branch\Models\Branch;
use Modules\Hr\Models\Employee;
use Modules\Notification\Database\Seeders\NotificationDatabaseSeeder;
use Modules\Notification\Models\NotificationPushToken;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\User\Enums\CustomerType;
use Modules\User\Enums\DefaultRole;
use Modules\User\Jobs\SendNewDeviceLoginNotificationJob;
use Modules\User\Models\Role;
use Modules\User\Services\Account\AccountServiceInterface;
use Modules\User\Services\Customer\CustomerServiceInterface;
use Modules\User\Services\User\UserServiceInterface;
use NotificationChannels\Fcm\FcmChannel;
use Tests\NotificationWorkflowTestCase;
use Yadahan\AuthenticationLog\AuthenticationLog;
use Yadahan\AuthenticationLog\Notifications\NewDevice;

class UserNotificationWorkflowTest extends NotificationWorkflowTestCase
{
    public function test_customer_activation_sends_account_activated_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $customer = $this->createCustomerUser([
            'name' => 'Inactive Customer',
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);

        app(CustomerServiceInterface::class)->update($customer->id, [
            'name' => $customer->name,
            'email' => $customer->email,
            'username' => $customer->username,
            'gender' => $customer->gender?->value,
            'phone_country_iso_code' => $customer->phone_country_iso_code,
            'phone' => $customer->phone,
            'birthdate' => $customer->birthdate,
            'note' => $customer->note,
            'customer_type' => $customer->customer_type?->value ?? CustomerType::Regular->value,
            'registration_number' => $customer->registration_number,
            'vat_tin' => $customer->vat_tin,
            'password' => null,
            'is_active' => true,
            'remove_profile_photo' => false,
        ]);

        Notification::assertSentToTimes($customer->fresh(), RenderedTemplateNotification::class, 1);
        Notification::assertSentTo($customer->fresh(), RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $mail = $notification->toMail($customer->fresh());
            $payload = $notification->toDatabase($customer->fresh());

            return $channels === ['mail', 'database']
                && $this->notificationTextMatchesBase((string) $mail->subject, 'Account Activated')
                && $this->notificationTitleMatchesBase($payload, 'Account Activated');
        });
    }

    public function test_staff_activation_sends_account_activated_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $user = $this->createUserWithRole(DefaultRole::Staff->value, [
            'name' => 'Inactive Staff',
            'email' => 'inactive-staff@example.com',
            'is_active' => false,
        ]);
        $role = Role::query()->where('name', DefaultRole::Staff->value)->firstOrFail();

        app(UserServiceInterface::class)->update($user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'gender' => $user->gender?->value,
            'phone_country_iso_code' => $user->phone_country_iso_code,
            'phone' => $user->phone,
            'birthdate' => $user->birthdate,
            'note' => $user->note,
            'branch_id' => $user->branch_id,
            'printer_id' => $user->printer_id,
            'category_slugs' => $user->category_slugs,
            'password' => null,
            'is_active' => true,
            'role' => $role->id,
        ]);

        Notification::assertSentToTimes($user->fresh(), RenderedTemplateNotification::class, 1);
        Notification::assertSentTo($user->fresh(), RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($user) {
            $mail = $notification->toMail($user->fresh());
            $payload = $notification->toDatabase($user->fresh());

            return $channels === ['mail', 'database']
                && $this->notificationTextMatchesBase((string) $mail->subject, 'Account Activated')
                && $this->notificationTitleMatchesBase($payload, 'Account Activated');
        });
    }

    public function test_linked_employee_activation_sends_account_activated_notification_without_staff_role(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create();
        $user = $this->createUserWithRole(DefaultRole::Driver->value, [
            'name' => 'Inactive Employee',
            'email' => 'inactive-employee@example.com',
            'branch_id' => $branch->id,
            'is_active' => false,
        ]);
        Employee::withoutEvents(fn () => Employee::factory()->forBranch($branch->id)->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]));
        $role = Role::query()->where('name', DefaultRole::Driver->value)->firstOrFail();

        app(UserServiceInterface::class)->update($user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'gender' => $user->gender?->value,
            'phone_country_iso_code' => $user->phone_country_iso_code,
            'phone' => $user->phone,
            'birthdate' => $user->birthdate,
            'note' => $user->note,
            'branch_id' => $user->branch_id,
            'printer_id' => $user->printer_id,
            'category_slugs' => $user->category_slugs,
            'password' => null,
            'is_active' => true,
            'role' => $role->id,
        ]);

        Notification::assertSentToTimes($user->fresh(), RenderedTemplateNotification::class, 1);
        Notification::assertSentTo($user->fresh(), RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($user) {
            $mail = $notification->toMail($user->fresh());
            $payload = $notification->toDatabase($user->fresh());

            return $channels === ['mail', 'database']
                && $this->notificationTextMatchesBase((string) $mail->subject, 'Account Activated')
                && $this->notificationTitleMatchesBase($payload, 'Account Activated');
        });
    }

    public function test_password_update_sends_password_changed_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $user = $this->createCustomerUser([
            'name' => 'Password User',
            'email' => 'password@example.com',
            'password' => bcrypt('old-password'),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        app(AccountServiceInterface::class)->updatePassword([
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
            'logout_from_other_devices' => false,
        ]);

        Notification::assertSentToTimes($user->fresh(), RenderedTemplateNotification::class, 1);
        Notification::assertSentTo($user->fresh(), RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($user) {
            $mail = $notification->toMail($user->fresh());
            $payload = $notification->toDatabase($user->fresh());

            return $channels === ['mail', 'database']
                && $this->notificationTextMatchesBase((string) $mail->subject, 'Password Changed')
                && $this->notificationTitleMatchesBase($payload, 'Password Changed');
        });
    }

    public function test_profile_update_sends_only_when_tracked_fields_changed(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $user = $this->createCustomerUser([
            'name' => 'Old Name',
            'username' => 'old_name',
            'email' => 'old@example.com',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        app(AccountServiceInterface::class)->updateProfile([
            'name' => 'New Name',
            'username' => 'old_name',
            'email' => 'old@example.com',
        ]);

        Notification::assertSentToTimes($user, RenderedTemplateNotification::class, 1);

        Notification::fake();

        $this->actingAs($user->fresh());

        app(AccountServiceInterface::class)->updateProfile([
            'name' => 'New Name',
            'username' => 'old_name',
            'email' => 'old@example.com',
        ]);

        Notification::assertNothingSent();
    }

    public function test_new_device_login_job_sends_for_new_device_only(): void
    {
        Notification::fake();
        $this->fakeSettings(provider: 'fcm');
        $this->fakeServiceRegistry(['in_app', 'push_notification']);
        $this->seed(NotificationDatabaseSeeder::class);

        $user = $this->createCustomerUser([
            'created_at' => now()->subHour(),
            'is_active' => true,
        ]);
        NotificationPushToken::query()->create([
            'user_id' => $user->id,
            'provider' => 'fcm',
            'token' => 'fcm-token',
        ]);

        $log = $this->createAuthenticationLog($user, '127.0.0.1', 'Mozilla/5.0', now());

        (new SendNewDeviceLoginNotificationJob($user->id, '127.0.0.1', 'Mozilla/5.0', 'en', $log->login_at))
            ->handle(app(NotificationDispatcherServiceInterface::class));

        Notification::assertSentTo($user, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($user) {
            $message = $notification->toFcm($user)->toArray();

            return $channels === ['database', FcmChannel::class]
                && data_get($message, 'data.type') === 'new_device_login_detected'
                && data_get($message, 'data.type_id') === ''
                && ! array_key_exists('locale', $message['data'])
                && ! array_key_exists('template_key', $message['data'])
                && ! array_key_exists('channel_key', $message['data'])
                && ! array_key_exists('deeplink', $message['data'])
                && ! array_key_exists('action', $message['data'])
                && filled(data_get($message, 'data.title'))
                && filled(data_get($message, 'data.content'));
        });

        Notification::fake();
        $this->createAuthenticationLog($user, '127.0.0.1', 'Mozilla/5.0', now()->addMinute());

        (new SendNewDeviceLoginNotificationJob($user->id, '127.0.0.1', 'Mozilla/5.0', 'en', now()->addMinute()))
            ->handle(app(NotificationDispatcherServiceInterface::class));

        Notification::assertNothingSent();
    }

    public function test_new_device_login_job_ignores_log_without_login_time(): void
    {
        Notification::fake();

        $user = $this->createCustomerUser([
            'created_at' => now()->subHour(),
            'is_active' => true,
        ]);
        $this->createAuthenticationLog($user, '127.0.0.1', 'Mozilla/5.0', null);

        (new SendNewDeviceLoginNotificationJob($user->id, '127.0.0.1', 'Mozilla/5.0', 'en', now()))
            ->handle(app(NotificationDispatcherServiceInterface::class));

        Notification::assertNothingSent();
    }

    public function test_login_event_queues_new_device_job_and_package_direct_notification_has_no_channels(): void
    {
        Queue::fake();

        $user = $this->createCustomerUser([
            'created_at' => now()->subHour(),
            'is_active' => true,
        ]);
        $log = new AuthenticationLog([
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'login_at' => now(),
        ]);

        $this->assertSame([], $user->notifyAuthenticationLogVia());
        $this->assertSame([], (new NewDevice($log))->via($user));

        event(new Login('api', $user, false));

        Queue::assertPushed(SendNewDeviceLoginNotificationJob::class);
    }
}
