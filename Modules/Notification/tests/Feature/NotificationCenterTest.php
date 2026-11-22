<?php

namespace Modules\Notification\Tests\Feature;

use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Modules\User\Models\User;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_user_receives_only_their_notifications(): void
    {
        [$firstUser, $secondUser] = User::factory()->count(2)->create();

        $this->sendInAppNotification($firstUser, 'profile_updated', [
            'en' => ['title' => 'Profile updated', 'content' => 'Your profile was updated.'],
            'ar' => ['title' => 'تم تحديث الملف الشخصي', 'content' => 'تم تحديث ملفك الشخصي.'],
        ]);
        $this->sendInAppNotification($secondUser, 'new_device_login_detected', [
            'en' => ['title' => 'New device login', 'content' => 'A new device signed in.'],
            'ar' => ['title' => 'تسجيل دخول من جهاز جديد', 'content' => 'تم تسجيل الدخول من جهاز جديد.'],
        ]);

        $this->actingAs($firstUser)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'body.data')
            ->assertJsonPath('body.data.0.type', 'profile_updated');
    }

    /**
     * Send one database notification.
     *
     * @param  array<string, array<string, string|null>>  $translations
     * @return DatabaseNotification
     */
    protected function sendInAppNotification(User $user, string $type, array $translations, ?Carbon $createdAt = null, ?string $appContext = null)
    {
        $existingIds = $user->notifications()->pluck('id');

        $payload = [
            'type' => $type,
            'type_id' => null,
            'translations' => $translations,
        ];

        if (! blank($appContext)) {
            $payload['app_context'] = $appContext;
        }

        $notification = new RenderedTemplateNotification([
            'in_app' => $payload,
        ], 'Forkiva');

        $user->notify($notification);

        $stored = $user->notifications()
            ->whereNotIn('id', $existingIds)
            ->latest('created_at')
            ->firstOrFail();

        if ($createdAt) {
            $stored->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        return $stored->refresh();
    }

    public function test_summary_returns_unread_count_and_recent_items(): void
    {
        $user = User::factory()->create();

        $this->sendInAppNotification($user, 'profile_updated', [
            'en' => ['title' => 'Profile updated', 'content' => 'Your profile was updated.'],
            'ar' => ['title' => 'تم تحديث الملف الشخصي', 'content' => 'تم تحديث ملفك الشخصي.'],
        ]);

        $readNotification = $this->sendInAppNotification($user, 'account_created', [
            'en' => ['title' => 'Account created', 'content' => 'Your account is ready.'],
            'ar' => ['title' => 'تم إنشاء الحساب', 'content' => 'أصبح حسابك جاهزاً.'],
        ]);
        $readNotification->markAsRead();

        $this->actingAs($user)
            ->getJson('/api/v1/notifications/summary')
            ->assertOk()
            ->assertJsonPath('body.unread_count', 1)
            ->assertJsonCount(2, 'body.recent');
    }

    public function test_list_endpoint_paginates_and_sorts_notifications(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(now());
        /** @var Carbon $nowSubMinutes2 */
        $nowSubMinutes2 = now()->subMinutes(2);

        /** @var Carbon $nowSubMinutes */
        $nowSubMinutes = now()->subMinutes();

        /** @var Carbon $now */
        $now = now();

        $this->sendInAppNotification($user, 'account_created', [
            'en' => ['title' => 'Account created', 'content' => 'The account has been created.'],
            'ar' => ['title' => 'تم إنشاء الحساب', 'content' => 'تم إنشاء الحساب.'],
        ], $nowSubMinutes2);
        $middle = $this->sendInAppNotification($user, 'profile_updated', [
            'en' => ['title' => 'Profile updated', 'content' => 'Updated content.'],
            'ar' => ['title' => 'تم تحديث الملف الشخصي', 'content' => 'تم التحديث.'],
        ], $nowSubMinutes);
        $latest = $this->sendInAppNotification($user, 'new_device_login_detected', [
            'en' => ['title' => 'New device login', 'content' => 'Latest content.'],
            'ar' => ['title' => 'تسجيل دخول من جهاز جديد', 'content' => 'أحدث محتوى.'],
        ], $now);
        Carbon::setTestNow();

        $this->actingAs($user)
            ->getJson('/api/v1/notifications?per_page=2')
            ->assertOk()
            ->assertJsonPath('body.pagination.total', 3)
            ->assertJsonPath('body.data.0.id', $latest->id)
            ->assertJsonPath('body.data.1.id', $middle->id);
    }

    public function test_show_endpoint_returns_one_current_user_notification(): void
    {
        [$user, $otherUser] = User::factory()->count(2)->create();

        $target = $this->sendInAppNotification($user, 'profile_updated', [
            'en' => ['title' => 'Profile updated', 'content' => 'Updated content.'],
            'ar' => ['title' => 'تم تحديث الملف الشخصي', 'content' => 'تم التحديث.'],
        ]);
        $otherNotification = $this->sendInAppNotification($otherUser, 'account_created', [
            'en' => ['title' => 'Account created', 'content' => 'Other user content.'],
            'ar' => ['title' => 'تم إنشاء الحساب', 'content' => 'محتوى مستخدم آخر.'],
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/notifications/'.$target->id)
            ->assertOk()
            ->assertJsonPath('body.id', $target->id)
            ->assertJsonPath('body.type', 'profile_updated');

        $this->actingAs($user)
            ->getJson('/api/v1/notifications/'.$otherNotification->id)
            ->assertNotFound();
    }

    public function test_status_filters_and_search_work(): void
    {
        $user = User::factory()->create();

        $readNotification = $this->sendInAppNotification($user, 'account_created', [
            'en' => ['title' => 'Account created', 'content' => 'Your account is ready.'],
            'ar' => ['title' => 'تم إنشاء الحساب', 'content' => 'أصبح حسابك جاهزاً.'],
        ]);
        $readNotification->markAsRead();

        $matching = $this->sendInAppNotification($user, 'profile_updated', [
            'en' => ['title' => 'Profile updated', 'content' => 'Changed email address.'],
            'ar' => ['title' => 'تم تحديث الملف الشخصي', 'content' => 'تم تغيير البريد الإلكتروني.'],
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/notifications?status=unread&search=email')
            ->assertOk()
            ->assertJsonCount(1, 'body.data')
            ->assertJsonPath('body.data.0.id', $matching->id);

        $this->actingAs($user)
            ->getJson('/api/v1/notifications?status=read')
            ->assertOk()
            ->assertJsonCount(1, 'body.data')
            ->assertJsonPath('body.data.0.id', $readNotification->id);
    }

    public function test_search_rejects_terms_shorter_than_the_fulltext_index_minimum(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/notifications?search=em')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }

    public function test_mark_one_read_updates_only_targeted_notification(): void
    {
        [$user, $otherUser] = User::factory()->count(2)->create();

        $target = $this->sendInAppNotification($user, 'profile_updated', [
            'en' => ['title' => 'Profile updated', 'content' => 'One target.'],
            'ar' => ['title' => 'تم تحديث الملف الشخصي', 'content' => 'هدف واحد.'],
        ]);
        $otherSameUser = $this->sendInAppNotification($user, 'account_created', [
            'en' => ['title' => 'Account created', 'content' => 'Still unread.'],
            'ar' => ['title' => 'تم إنشاء الحساب', 'content' => 'ما زال غير مقروء.'],
        ]);
        $otherUserNotification = $this->sendInAppNotification($otherUser, 'account_activated', [
            'en' => ['title' => 'Account activated', 'content' => 'Other user.'],
            'ar' => ['title' => 'تم تفعيل الحساب', 'content' => 'مستخدم آخر.'],
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/notifications/'.$target->id.'/read')
            ->assertOk()
            ->assertJsonPath('body.id', $target->id)
            ->assertJsonPath('body.read_at', fn ($value) => ! blank($value));

        $this->assertNotNull($target->fresh()->read_at);
        $this->assertNull($otherSameUser->fresh()->read_at);
        $this->assertNull($otherUserNotification->fresh()->read_at);
    }

    public function test_mark_all_read_updates_only_authenticated_user_unread_notifications(): void
    {
        [$user, $otherUser] = User::factory()->count(2)->create();

        $first = $this->sendInAppNotification($user, 'profile_updated', [
            'en' => ['title' => 'Profile updated', 'content' => 'First unread.'],
            'ar' => ['title' => 'تم تحديث الملف الشخصي', 'content' => 'الأول غير مقروء.'],
        ]);
        $second = $this->sendInAppNotification($user, 'account_created', [
            'en' => ['title' => 'Account created', 'content' => 'Second unread.'],
            'ar' => ['title' => 'تم إنشاء الحساب', 'content' => 'الثاني غير مقروء.'],
        ]);
        $other = $this->sendInAppNotification($otherUser, 'account_activated', [
            'en' => ['title' => 'Account activated', 'content' => 'Other user unread.'],
            'ar' => ['title' => 'تم تفعيل الحساب', 'content' => 'غير مقروء لمستخدم آخر.'],
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('body.unread_count', 0);

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_app_context_filters_list_summary_read_and_read_all(): void
    {
        $user = User::factory()->create();

        $driverNotification = $this->sendInAppNotification($user, 'delivery_assigned', [
            'en' => ['title' => 'Delivery assigned', 'content' => 'Driver app notification.'],
            'ar' => ['title' => 'تم تعيين التوصيل', 'content' => 'إشعار تطبيق السائق.'],
        ], null, 'driver');
        $employeeNotification = $this->sendInAppNotification($user, 'employee_created', [
            'en' => ['title' => 'Employee created', 'content' => 'Employee app notification.'],
            'ar' => ['title' => 'تم إنشاء الموظف', 'content' => 'إشعار تطبيق الموظف.'],
        ], null, 'employee');
        $otherNotification = $this->sendInAppNotification($user, 'profile_updated', [
            'en' => ['title' => 'Profile updated', 'content' => 'Web notification.'],
            'ar' => ['title' => 'تم تحديث الملف الشخصي', 'content' => 'إشعار الويب.'],
        ], null, 'other');

        $this->actingAs($user)
            ->getJson('/api/v1/notifications?app_context=driver')
            ->assertOk()
            ->assertJsonCount(1, 'body.data')
            ->assertJsonPath('body.data.0.id', $driverNotification->id)
            ->assertJsonPath('body.data.0.app_context', 'driver');

        $this->actingAs($user)
            ->getJson('/api/v1/notifications/'.$driverNotification->id.'?app_context=driver')
            ->assertOk()
            ->assertJsonPath('body.id', $driverNotification->id)
            ->assertJsonPath('body.app_context', 'driver');

        $this->actingAs($user)
            ->getJson('/api/v1/notifications/'.$employeeNotification->id.'?app_context=driver')
            ->assertNotFound();

        $this->actingAs($user)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(3, 'body.data');

        $this->actingAs($user)
            ->getJson('/api/v1/notifications/summary?app_context=employee')
            ->assertOk()
            ->assertJsonPath('body.unread_count', 1)
            ->assertJsonCount(1, 'body.recent');

        $this->actingAs($user)
            ->patchJson('/api/v1/notifications/'.$employeeNotification->id.'/read?app_context=driver')
            ->assertNotFound();

        $this->actingAs($user)
            ->patchJson('/api/v1/notifications/'.$employeeNotification->id.'/read?app_context=employee')
            ->assertOk()
            ->assertJsonPath('body.id', $employeeNotification->id)
            ->assertJsonPath('body.read_at', fn ($value) => ! blank($value));

        $this->actingAs($user)
            ->patchJson('/api/v1/notifications/read-all?app_context=driver')
            ->assertOk()
            ->assertJsonPath('body.unread_count', 0);

        $this->assertNotNull($driverNotification->fresh()->read_at);
        $this->assertNotNull($employeeNotification->fresh()->read_at);
        $this->assertNull($otherNotification->fresh()->read_at);

        $this->actingAs($user)
            ->getJson('/api/v1/notifications/summary')
            ->assertOk()
            ->assertJsonPath('body.unread_count', 1);
    }

    public function test_notification_resource_returns_localized_fields(): void
    {
        config(['app.locale' => 'ar']);
        app()->setLocale('ar');

        $user = User::factory()->create();

        $notification = $this->sendInAppNotification($user, 'profile_updated', [
            'en' => [
                'title' => 'Profile updated',
                'subject' => 'Update subject',
                'content' => 'English content',
            ],
            'ar' => [
                'title' => 'تم تحديث الملف الشخصي',
                'subject' => 'موضوع التحديث',
                'content' => 'محتوى عربي',
            ],
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('body.data.0.id', $notification->id)
            ->assertJsonPath('body.data.0.title', 'تم تحديث الملف الشخصي')
            ->assertJsonPath('body.data.0.subject', 'موضوع التحديث')
            ->assertJsonPath('body.data.0.content', 'محتوى عربي');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSettings();
        $this->withoutMiddleware(Authorize::class);
    }

    /**
     * Fake settings used by locale helpers.
     */
    protected function fakeSettings(): void
    {
        setting([
            'default_locale' => 'en',
            'support_locale' => ['en', 'ar'],
        ]);
    }
}
