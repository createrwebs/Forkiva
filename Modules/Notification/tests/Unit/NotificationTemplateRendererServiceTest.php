<?php

namespace Modules\Notification\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notification\Database\Seeders\NotificationDatabaseSeeder;
use Modules\Notification\Models\NotificationTemplateMessage;
use Modules\Notification\Services\NotificationTemplate\NotificationTemplateRendererServiceInterface;
use Modules\User\Enums\DefaultRole;
use Tests\TestCase;

class NotificationTemplateRendererServiceTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT_TEMPLATE_KEY = 'account_created';

    private NotificationTemplateRendererServiceInterface $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        app()->instance('setting', new class
        {
            public function get(string $key, mixed $default = null): mixed
            {
                if (str_starts_with($key, 'services.notifications.') && str_ends_with($key, '.enabled')) {
                    return true;
                }

                return match ($key) {
                    'supported_locales' => ['en', 'ar'],
                    'default_locale' => 'en',
                    default => $default,
                };
            }

            public function set(array $data): array
            {
                return $data;
            }
        });

        $this->seed(NotificationDatabaseSeeder::class);
        $this->renderer = app(NotificationTemplateRendererServiceInterface::class);
    }

    public function test_renders_email_subject_and_content(): void
    {
        $rendered = $this->renderer->render(self::ACCOUNT_TEMPLATE_KEY, 'email', [
            'app_name' => 'Forkiva',
            'user_name' => 'Ayman',
            'account_email' => 'ayman@example.com',
        ], 'en');

        $this->assertSame('Welcome to Forkiva', $rendered['subject']);
        $this->assertSame('Account Created', $rendered['title']);
        $this->assertSame('تم إنشاء الحساب', $rendered['translations']['ar']['title']);
        $this->assertSame('Welcome to Forkiva', $rendered['translations']['en']['subject']);
        $this->assertStringContainsString('Hello Ayman', $rendered['content']);
        $this->assertStringContainsString('مرحباً Ayman', $rendered['translations']['ar']['content']);
        $this->assertStringContainsString('ayman@example.com', $rendered['content']);
        $this->assertStringContainsString('start using the available features', $rendered['content']);
    }

    public function test_renders_non_email_text_content_only(): void
    {
        $rendered = $this->renderer->render(self::ACCOUNT_TEMPLATE_KEY, 'in_app', [
            'app_name' => 'Forkiva',
            'user_name' => 'Ayman',
            'account_email' => 'ayman@example.com',
        ], 'en');

        $this->assertNull($rendered['subject']);
        $this->assertSame('Your account for ayman@example.com has been created successfully.', $rendered['content']);
    }

    public function test_renderer_sanitizes_legacy_html_and_escapes_email_variables(): void
    {
        $message = NotificationTemplateMessage::query()
            ->where('template_key', self::ACCOUNT_TEMPLATE_KEY)
            ->where('channel_key', 'email')
            ->where('recipient_role_key', 'default')
            ->firstOrFail();

        $message->setTranslation(
            'content',
            'en',
            '<p><strong>Hello</strong> {{ user_name }}</p><img src="x" onerror="alert(1)"><script>alert(2)</script>',
        );
        $message->save();

        $rendered = $this->renderer->render(self::ACCOUNT_TEMPLATE_KEY, 'email', [
            'app_name' => 'Forkiva',
            'user_name' => '<strong onmouseover="alert(3)">Ayman</strong>',
            'account_email' => 'ayman@example.com',
        ], 'en');

        $this->assertStringContainsString('<strong>Hello</strong>', $rendered['content']);
        $this->assertStringNotContainsString('<img', $rendered['content']);
        $this->assertStringNotContainsString('<script', $rendered['content']);
        $this->assertStringNotContainsString('<strong onmouseover', $rendered['content']);
        $this->assertStringContainsString('&lt;strong', $rendered['content']);
    }

    public function test_renderer_forces_non_email_content_to_plain_text(): void
    {
        $message = NotificationTemplateMessage::query()
            ->where('template_key', self::ACCOUNT_TEMPLATE_KEY)
            ->where('channel_key', 'in_app')
            ->where('recipient_role_key', 'default')
            ->firstOrFail();

        $message->setTranslation(
            'content',
            'en',
            '<p>Hello {{ user_name }}</p><img src="x" onerror="alert(1)"><script>alert(2)</script>',
        );
        $message->save();

        $rendered = $this->renderer->render(self::ACCOUNT_TEMPLATE_KEY, 'in_app', [
            'app_name' => 'Forkiva',
            'user_name' => '<svg onload="alert(3)">Ayman</svg>',
            'account_email' => 'ayman@example.com',
        ], 'en');

        $this->assertStringNotContainsString('<', $rendered['content']);
        $this->assertStringNotContainsString('onerror', $rendered['content']);
        $this->assertStringNotContainsString('onload', $rendered['content']);
        $this->assertStringContainsString('Ayman', $rendered['content']);
    }

    public function test_renders_order_status_changed_template(): void
    {
        $variables = [
            'app_name' => 'Forkiva',
            'user_name' => 'Ayman',
            'order_number' => 'ORD-1001',
            'branch_name' => [
                'en' => 'Main Branch',
                'ar' => 'الفرع الرئيسي',
            ],
            'order_status' => [
                'en' => 'Ready',
                'ar' => 'جاهز',
            ],
        ];

        $email = $this->renderer->render('order_status_changed', 'email', $variables, 'en');
        $inApp = $this->renderer->render('order_status_changed', 'in_app', $variables, 'ar');
        $push = $this->renderer->render('order_status_changed', 'push_notification', $variables, 'en');

        $this->assertSame('Order ORD-1001 status changed', $email['subject']);
        $this->assertStringContainsString('ORD-1001', $email['content']);
        $this->assertStringContainsString('جاهز', $inApp['content']);
        $this->assertSame('Order ORD-1001 is now Ready.', $push['content']);
    }

    public function test_renderer_falls_back_to_default_locale(): void
    {
        $message = NotificationTemplateMessage::query()
            ->where('template_key', self::ACCOUNT_TEMPLATE_KEY)
            ->where('channel_key', 'in_app')
            ->where('recipient_role_key', 'default')
            ->firstOrFail();

        $message->replaceTranslations('content', [
            'en' => '{{ user_name }} can now access {{ app_name }}.',
        ]);
        $message->save();

        $rendered = $this->renderer->render(self::ACCOUNT_TEMPLATE_KEY, 'in_app', [
            'app_name' => 'Forkiva',
            'user_name' => 'Ayman',
        ], 'ar');

        $this->assertSame('Ayman can now access Forkiva.', $rendered['translations']['en']['content']);
        $this->assertSame('Ayman can now access Forkiva.', $rendered['content']);
    }

    public function test_renderer_ignores_unknown_variables(): void
    {
        $message = NotificationTemplateMessage::query()
            ->where('template_key', self::ACCOUNT_TEMPLATE_KEY)
            ->where('channel_key', 'in_app')
            ->where('recipient_role_key', 'default')
            ->firstOrFail();

        $message->setTranslation('content', 'en', 'Welcome {{ unknown_name }}.');
        $message->save();

        $rendered = $this->renderer->render(self::ACCOUNT_TEMPLATE_KEY, 'in_app', [
            'app_name' => 'Forkiva',
            'user_name' => 'Ayman',
        ], 'en');

        $this->assertSame('Welcome {{ unknown_name }}.', $rendered['content']);
    }

    public function test_renderer_keeps_supported_variable_placeholder_when_value_is_missing(): void
    {
        $rendered = $this->renderer->render(self::ACCOUNT_TEMPLATE_KEY, 'email', [
            'app_name' => 'Forkiva',
        ], 'en');

        $this->assertStringContainsString('{{ user_name }}', $rendered['content']);
    }

    public function test_renderer_keeps_content_placeholder_when_supported_variable_is_missing(): void
    {
        $message = NotificationTemplateMessage::query()
            ->where('template_key', self::ACCOUNT_TEMPLATE_KEY)
            ->where('channel_key', 'in_app')
            ->where('recipient_role_key', 'default')
            ->firstOrFail();

        $message->setTranslation('content', 'en', '{{ user_name }}');
        $message->save();

        $rendered = $this->renderer->render(self::ACCOUNT_TEMPLATE_KEY, 'in_app', [
            'app_name' => 'Forkiva',
        ], 'en');

        $this->assertSame('{{ user_name }}', $rendered['content']);
        $this->assertSame('{{ user_name }}', $rendered['translations']['en']['content']);
    }

    public function test_renderer_uses_recipient_role_variant_and_falls_back_to_default(): void
    {
        $driver = $this->renderer->render('delivery_assigned', 'in_app', [
            'app_name' => 'Forkiva',
            'user_name' => 'Ayman',
            'delivery_number' => '15',
            'order_number' => 'ORD-1001',
            'driver_name' => 'Ayman Driver',
            'eta' => '20 minutes',
            'pickup_address' => 'Main Branch',
            'delivery_address' => 'Customer Home',
        ], 'en', DefaultRole::Driver->value);

        $fallback = $this->renderer->render('delivery_assigned', 'in_app', [
            'app_name' => 'Forkiva',
            'user_name' => 'Ayman',
            'delivery_number' => '15',
            'order_number' => 'ORD-1001',
            'driver_name' => 'Ayman Driver',
            'eta' => '20 minutes',
            'pickup_address' => 'Main Branch',
            'delivery_address' => 'Customer Home',
        ], 'en', 'unsupported_role');

        $this->assertSame('New delivery 15 for order ORD-1001 assigned to you. Pickup: Main Branch. Delivery: Customer Home. ETA: 20 minutes.', $driver['content']);
        $this->assertSame('Delivery 15 for order ORD-1001 assigned to Ayman Driver. ETA: 20 minutes.', $fallback['content']);
    }
}
