<?php

namespace Modules\Notification\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Notification\Database\Seeders\NotificationDatabaseSeeder;
use Modules\Notification\Database\Seeders\NotificationTemplates\NotificationRequirementTemplateCatalog;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Models\NotificationTemplateMessage;
use Modules\Service\Services\ServiceRegistry\ServiceRegistryServiceInterface;
use Tests\TestCase;

class NotificationTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT_TEMPLATE_KEY = 'account_created';

    private const ORDER_TEMPLATE_KEY = 'order_created';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSettings();
    }

    public function test_seeded_templates_and_messages_exist(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);

        $this->assertFalse(Schema::hasTable('notification_template_categories'));
        $this->assertSame(count(NotificationRequirementTemplateCatalog::all()), NotificationTemplate::query()->count());
        $this->assertDatabaseHas('notification_templates', [
            'key' => self::ACCOUNT_TEMPLATE_KEY,
        ]);

        $template = NotificationTemplate::query()->where('key', self::ACCOUNT_TEMPLATE_KEY)->firstOrFail();
        $this->assertSame('Account Created', $template->getTranslation('name', 'en'));
        $this->assertSame('تم إنشاء الحساب', $template->getTranslation('name', 'ar'));
        $this->assertSame('Sent when an account is created.', $template->getTranslation('description', 'en'));
        $this->assertSame('يُرسل عند إنشاء الحساب.', $template->getTranslation('description', 'ar'));

        foreach (['email', 'in_app', 'push_notification', 'sms', 'whatsapp'] as $channel) {
            $this->assertDatabaseHas('notification_template_messages', [
                'template_key' => self::ORDER_TEMPLATE_KEY,
                'channel_key' => $channel,
            ]);
        }

        foreach (['push_notification', 'sms', 'whatsapp'] as $channel) {
            $this->assertDatabaseMissing('notification_template_messages', [
                'template_key' => 'profile_updated',
                'channel_key' => $channel,
            ]);
        }

        foreach (['email', 'sms', 'whatsapp'] as $channel) {
            $this->assertDatabaseMissing('notification_template_messages', [
                'template_key' => 'kitchen_item_ready',
                'channel_key' => $channel,
            ]);
        }

        $orderMessage = NotificationTemplateMessage::query()
            ->where('template_key', self::ORDER_TEMPLATE_KEY)
            ->where('channel_key', 'email')
            ->where('recipient_role_key', 'default')
            ->firstOrFail();

        $this->assertStringContainsString('{{ order_number }}', $orderMessage->getTranslation('subject', 'en'));
        $this->assertStringContainsString('{{ branch_name }}', $orderMessage->getTranslation('content', 'ar'));
        $this->assertDatabaseHas('notification_template_messages', [
            'template_key' => 'delivery_assigned',
            'channel_key' => 'in_app',
            'recipient_role_key' => 'driver',
        ]);

        $response = $this->withoutMiddleware()->getJson('/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY);

        $response->assertOk()
            ->assertJsonPath('body.key', self::ACCOUNT_TEMPLATE_KEY)
            ->assertJsonPath('body.supported_variables.0.token', '{app_name}');

        $indexResponse = $this->withoutMiddleware()
            ->getJson('/api/v1/notification-templates')
            ->assertOk()
            ->assertJsonPath('body.data.0.channels.0.key', 'email')
            ->assertJsonMissingPath('body.data.0.messages')
            ->assertJsonMissingPath('body.data.0.supported_variables')
            ->assertJsonMissingPath('body.data.0.available_channels');

        $this->assertNotEmpty($indexResponse->json('body.data'));
    }

    public function test_catalog_messages_only_use_declared_supported_variables(): void
    {
        foreach (NotificationRequirementTemplateCatalog::all() as $templateKey => $template) {
            $supportedVariables = $template['supported_variables'] ?? [];

            foreach ($template['messages'] ?? [] as $message) {
                foreach (['subject', 'content'] as $field) {
                    foreach (($message[$field] ?? []) as $locale => $value) {
                        preg_match_all('/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}|\{([A-Za-z_][A-Za-z0-9_]*)\}/', (string) $value, $matches);

                        $placeholders = collect([...$matches[1], ...$matches[2]])
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();

                        $unsupported = array_values(array_diff($placeholders, $supportedVariables));

                        $this->assertSame(
                            [],
                            $unsupported,
                            "Catalog template {$templateKey} {$message['channel_key']} {$field}.{$locale} contains unsupported variables."
                        );
                    }
                }
            }
        }
    }

    public function test_role_variant_titles_use_supported_locales(): void
    {
        $this->fakeSettings(['en', 'ar', 'fr']);

        $message = collect(NotificationRequirementTemplateCatalog::get('delivery_assigned')['messages'])
            ->first(fn (array $catalogMessage) => $catalogMessage['channel_key'] === 'in_app'
                && ($catalogMessage['recipient_role_key'] ?? null) === 'driver');

        $this->assertSame(['en', 'ar', 'fr'], array_keys($message['title']));
        $this->assertSame('Delivery Assigned - Driver action', $message['title']['en']);
        $this->assertSame('تعيين التوصيل - للسائق', $message['title']['ar']);
        $this->assertSame('Delivery Assigned - Driver action', $message['title']['fr']);
    }

    public function test_catalog_translatable_fields_use_supported_locales(): void
    {
        $this->fakeSettings(['en', 'ar', 'fr']);
        $expectedLocales = ['en', 'ar', 'fr'];

        foreach (NotificationRequirementTemplateCatalog::all() as $templateKey => $template) {
            $this->assertSame($expectedLocales, array_keys($template['name']), "{$templateKey} name locales do not match supported locales.");
            $this->assertSame($expectedLocales, array_keys($template['description']), "{$templateKey} description locales do not match supported locales.");

            foreach ($template['messages'] ?? [] as $message) {
                foreach (['title', 'subject', 'content'] as $field) {
                    if (! isset($message[$field]) || ! is_array($message[$field])) {
                        continue;
                    }

                    $this->assertSame(
                        $expectedLocales,
                        array_keys($message[$field]),
                        "{$templateKey} {$message['channel_key']} {$message['recipient_role_key']} {$field} locales do not match supported locales."
                    );
                }
            }
        }
    }

    public function test_reseeding_does_not_duplicate_or_overwrite_edited_template_or_messages(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);

        $template = NotificationTemplate::query()
            ->where('key', self::ACCOUNT_TEMPLATE_KEY)
            ->firstOrFail();

        $template->setTranslation('name', 'en', 'Edited Template');
        $template->setTranslation('description', 'en', 'Edited description');
        $template->save();

        $message = NotificationTemplateMessage::query()
            ->where('template_key', self::ACCOUNT_TEMPLATE_KEY)
            ->where('channel_key', 'email')
            ->where('recipient_role_key', 'default')
            ->firstOrFail();

        $message->setTranslation('content', 'en', 'Edited {user_name}');
        $this->travel(1)->seconds();
        $message->save();
        $this->travelBack();

        $this->seed(NotificationDatabaseSeeder::class);

        $this->assertSame(1, NotificationTemplate::query()->where('key', self::ACCOUNT_TEMPLATE_KEY)->count());
        $this->assertSame(
            'Edited Template',
            NotificationTemplate::query()
                ->where('key', self::ACCOUNT_TEMPLATE_KEY)
                ->firstOrFail()
                ->getTranslation('name', 'en')
        );
        $this->assertSame(
            'Edited description',
            NotificationTemplate::query()
                ->where('key', self::ACCOUNT_TEMPLATE_KEY)
                ->firstOrFail()
                ->getTranslation('description', 'en')
        );
        $this->assertSame(
            'Edited {user_name}',
            NotificationTemplateMessage::query()
                ->where('template_key', self::ACCOUNT_TEMPLATE_KEY)
                ->where('channel_key', 'email')
                ->where('recipient_role_key', 'default')
                ->firstOrFail()
                ->getTranslation('content', 'en')
        );
    }

    public function test_reseeding_upgrades_untouched_seeded_message_copy(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);

        $message = NotificationTemplateMessage::query()
            ->where('template_key', self::ACCOUNT_TEMPLATE_KEY)
            ->where('channel_key', 'email')
            ->where('recipient_role_key', 'default')
            ->firstOrFail();

        $createdAt = $message->created_at;
        $message->timestamps = false;
        $message->title = null;
        $message->subject = [
            'en' => 'Legacy subject {{ user_name }}',
            'ar' => 'موضوع قديم {{ user_name }}',
        ];
        $message->content = [
            'en' => 'Legacy copy {{ user_name }}',
            'ar' => 'نص قديم {{ user_name }}',
        ];
        $message->updated_at = $createdAt;
        $message->save();

        $expected = collect(NotificationRequirementTemplateCatalog::get(self::ACCOUNT_TEMPLATE_KEY)['messages'])
            ->first(fn (array $catalogMessage) => $catalogMessage['channel_key'] === 'email'
                && ($catalogMessage['recipient_role_key'] ?? NotificationTemplateMessage::DEFAULT_RECIPIENT_ROLE) === NotificationTemplateMessage::DEFAULT_RECIPIENT_ROLE);

        $this->seed(NotificationDatabaseSeeder::class);

        $message->refresh();

        $this->assertSame($expected['title']['en'], $message->getTranslation('title', 'en'));
        $this->assertSame($expected['subject']['en'], $message->getTranslation('subject', 'en'));
        $this->assertSame($expected['content']['en'], $message->getTranslation('content', 'en'));
    }

    public function test_create_and_delete_routes_are_not_available(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/v1/notification-templates', [])
            ->assertMethodNotAllowed();

        $this->withoutMiddleware()
            ->deleteJson('/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY)
            ->assertMethodNotAllowed();
    }

    public function test_template_name_and_description_can_be_updated(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);

        $response = $this->withoutMiddleware()->putJson(
            '/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY.'?return_all_translations=true',
            [
                'name' => [
                    'en' => 'Updated Account Template',
                    'ar' => 'قالب الحساب المحدث',
                ],
                'description' => [
                    'en' => 'Updated editable description',
                    'ar' => 'وصف قابل للتعديل محدث',
                ],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('body.name.en', 'Updated Account Template')
            ->assertJsonPath('body.description.ar', 'وصف قابل للتعديل محدث');

        $template = NotificationTemplate::query()
            ->where('key', self::ACCOUNT_TEMPLATE_KEY)
            ->firstOrFail();

        $this->assertSame('Updated Account Template', $template->getTranslation('name', 'en'));
        $this->assertSame('وصف قابل للتعديل محدث', $template->getTranslation('description', 'ar'));
    }

    public function test_available_channels_are_loaded_from_dynamic_service_registry(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);
        $this->fakeServiceRegistry(['email', 'in_app', 'whatsapp']);

        $response = $this->withoutMiddleware()->getJson('/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY);

        $response->assertOk();

        $channels = collect($response->json('body.channels'))->pluck('key');

        $this->assertTrue($channels->contains('email'));
        $this->assertTrue($channels->contains('whatsapp'));
    }

    public function test_index_search_filters_by_partial_key_and_translated_name(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);

        $this->withoutMiddleware()
            ->getJson('/api/v1/notification-templates?filters[search]=activated')
            ->assertOk()
            ->assertJsonCount(1, 'body.data')
            ->assertJsonPath('body.data.0.key', 'account_activated');

        $response = $this->withoutMiddleware()
            ->getJson('/api/v1/notification-templates?filters[search]=Account Created')
            ->assertOk();

        $keys = collect($response->json('body.data'))->pluck('key');

        $this->assertTrue($keys->contains('account_created'));
        $this->assertFalse($keys->contains('account_activated'));
    }

    public function test_disabled_service_channels_are_hidden_and_rejected(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);
        $this->fakeServiceRegistry(['email', 'in_app', 'whatsapp'], ['whatsapp']);

        $response = $this->withoutMiddleware()->getJson('/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY);

        $response->assertOk();

        $channels = collect($response->json('body.channels'))->pluck('key');

        $this->assertTrue($channels->contains('email'));
        $this->assertFalse($channels->contains('whatsapp'));

        $this->withoutMiddleware()->putJson(
            '/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY,
            [
                'name' => [
                    'en' => 'Account Created',
                    'ar' => 'إنشاء الحساب',
                ],
                'messages' => [
                    'whatsapp' => [
                        'content' => [
                            'en' => 'Your {app_name} account for {branch_name} is ready.',
                            'ar' => 'حساب {app_name} الخاص بك في {branch_name} جاهز.',
                        ],
                    ],
                ],
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['messages.whatsapp']);
    }

    public function test_template_unsupported_channel_is_rejected_even_when_service_is_enabled(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);
        $this->fakeServiceRegistry(['email', 'in_app', 'push_notification', 'sms']);

        $response = $this->withoutMiddleware()->getJson('/api/v1/notification-templates/profile_updated');
        $channels = collect($response->json('body.channels'))->pluck('key');

        $response->assertOk();
        $this->assertTrue($channels->contains('email'));
        $this->assertFalse($channels->contains('push_notification'));

        $this->withoutMiddleware()->putJson(
            '/api/v1/notification-templates/profile_updated',
            [
                'name' => [
                    'en' => 'Profile Updated',
                    'ar' => 'تحديث الملف الشخصي',
                ],
                'messages' => [
                    'push_notification' => [
                        'content' => [
                            'en' => 'Profile updated for {user_name}.',
                            'ar' => 'تم تحديث الملف الشخصي للمستخدم {user_name}.',
                        ],
                    ],
                ],
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['messages.push_notification']);
    }

    public function test_update_template_updates_messages_for_multiple_channels_with_translations(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);

        $response = $this->withoutMiddleware()->putJson(
            '/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY,
            [
                'name' => [
                    'en' => 'Account Created',
                    'ar' => 'إنشاء الحساب',
                ],
                'messages' => [
                    'email' => [
                        'subject' => [
                            'en' => 'Hello {user_name}',
                            'ar' => 'مرحباً {user_name}',
                        ],
                        'content' => [
                            'en' => '<p>Welcome {user_name} to {app_name}. Email: {account_email}.</p>',
                            'ar' => '<p>مرحباً {user_name} في {app_name}. البريد: {account_email}.</p>',
                        ],
                    ],
                    'in_app' => [
                        'content' => [
                            'en' => '{user_name} can now access {app_name} with {account_email}.',
                            'ar' => 'يمكن لـ {user_name} الآن الوصول إلى {app_name} باستخدام {account_email}.',
                        ],
                    ],
                ],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('body.messages.email.default.subject.en', 'Hello {user_name}')
            ->assertJsonPath('body.messages.in_app.default.content.en', '{user_name} can now access {app_name} with {account_email}.');
    }

    public function test_update_template_can_create_message_for_dynamic_plugin_channel(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);
        $this->fakeServiceRegistry(['email', 'whatsapp']);

        NotificationTemplateMessage::query()
            ->where('template_key', self::ACCOUNT_TEMPLATE_KEY)
            ->where('channel_key', 'whatsapp')
            ->delete();

        $response = $this->withoutMiddleware()->putJson(
            '/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY,
            [
                'name' => [
                    'en' => 'Account Created',
                    'ar' => 'إنشاء الحساب',
                ],
                'messages' => [
                    'whatsapp' => [
                        'content' => [
                            'en' => 'Your {app_name} account for {account_email} is active.',
                            'ar' => 'حساب {app_name} الخاص بك للبريد {account_email} أصبح نشطاً.',
                        ],
                    ],
                ],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('body.messages.whatsapp.default.content.en', 'Your {app_name} account for {account_email} is active.');

        $this->assertDatabaseHas('notification_template_messages', [
            'template_key' => self::ACCOUNT_TEMPLATE_KEY,
            'channel_key' => 'whatsapp',
            'recipient_role_key' => 'default',
        ]);
    }

    public function test_email_message_is_saved_as_html_content_type(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);

        $response = $this->withoutMiddleware()->putJson(
            '/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY,
            [
                'name' => [
                    'en' => 'Account Created',
                    'ar' => 'إنشاء الحساب',
                ],
                'messages' => [
                    'email' => [
                        'subject' => [
                            'en' => 'Welcome {user_name}',
                            'ar' => 'مرحباً {user_name}',
                        ],
                        'content' => [
                            'en' => '<p><strong>Welcome</strong> {user_name} to {app_name}. Email: {account_email}.</p>',
                            'ar' => '<p><strong>مرحباً</strong> {user_name} في {app_name}. البريد: {account_email}.</p>',
                        ],
                    ],
                ],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('body.messages.email.default.content_type', 'html');
    }

    public function test_update_sanitizes_email_html_and_plain_text_channels(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);

        $this->withoutMiddleware()->putJson(
            '/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY,
            [
                'name' => [
                    'en' => 'Account Created',
                    'ar' => 'إنشاء الحساب',
                ],
                'messages' => [
                    'email' => [
                        'subject' => [
                            'en' => '<strong>Welcome</strong> {user_name}',
                        ],
                        'content' => [
                            'en' => '<p><strong>Welcome</strong> {user_name}</p><img src="x" onerror="alert(1)"><script>alert(2)</script>',
                        ],
                    ],
                    'in_app' => [
                        'content' => [
                            'en' => '<p>Welcome {user_name}</p><svg onload="alert(3)"></svg>',
                        ],
                    ],
                ],
            ],
        )->assertOk();

        $email = NotificationTemplateMessage::query()
            ->where('template_key', self::ACCOUNT_TEMPLATE_KEY)
            ->where('channel_key', 'email')
            ->where('recipient_role_key', 'default')
            ->firstOrFail();
        $inApp = NotificationTemplateMessage::query()
            ->where('template_key', self::ACCOUNT_TEMPLATE_KEY)
            ->where('channel_key', 'in_app')
            ->where('recipient_role_key', 'default')
            ->firstOrFail();

        $this->assertSame('Welcome {user_name}', $email->getTranslation('subject', 'en'));
        $this->assertStringContainsString('<strong>Welcome</strong>', $email->getTranslation('content', 'en'));
        $this->assertStringNotContainsString('<img', $email->getTranslation('content', 'en'));
        $this->assertStringNotContainsString('<script', $email->getTranslation('content', 'en'));
        $this->assertStringNotContainsString('<', $inApp->getTranslation('content', 'en'));
        $this->assertStringNotContainsString('onload', $inApp->getTranslation('content', 'en'));
    }

    public function test_non_email_channels_reject_subject(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);

        $response = $this->withoutMiddleware()->putJson(
            '/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY,
            [
                'name' => [
                    'en' => 'Account Created',
                    'ar' => 'إنشاء الحساب',
                ],
                'messages' => [
                    'in_app' => [
                        'subject' => [
                            'en' => 'Not allowed',
                        ],
                        'content' => [
                            'en' => '{user_name} can now access {app_name} with {account_email}.',
                            'ar' => 'يمكن لـ {user_name} الآن الوصول إلى {app_name} باستخدام {account_email}.',
                        ],
                    ],
                ],
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['messages.in_app.default.subject']);
    }

    public function test_unsupported_channel_unknown_placeholder_and_missing_translation_placeholders_are_rejected(): void
    {
        $this->seed(NotificationDatabaseSeeder::class);

        $this->withoutMiddleware()->putJson(
            '/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY,
            [
                'name' => [
                    'en' => 'Account Created',
                    'ar' => 'إنشاء الحساب',
                ],
                'messages' => [
                    'unsupported_channel' => [
                        'content' => [
                            'en' => '{app_name}: your account for {branch_name} is active. Sign in with {login_identifier}.',
                        ],
                    ],
                ],
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['messages.unsupported_channel']);

        $this->withoutMiddleware()->putJson(
            '/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY,
            [
                'name' => [
                    'en' => 'Account Created',
                    'ar' => 'إنشاء الحساب',
                ],
                'messages' => [
                    'in_app' => [
                        'content' => [
                            'en' => 'Welcome {missing_name}.',
                        ],
                    ],
                ],
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['messages.in_app.default.content.en']);

        $this->withoutMiddleware()->putJson(
            '/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY,
            [
                'name' => [
                    'en' => 'Account Created',
                    'ar' => 'إنشاء الحساب',
                ],
                'messages' => [
                    'email' => [
                        'subject' => [
                            'en' => 'Hello {branch_name}',
                            'ar' => 'مرحباً {branch_name}',
                        ],
                        'content' => [
                            'en' => 'Hello {user_name} in {app_name} at {branch_name}. {account_status}',
                            'ar' => 'مرحباً {user_name} في {app_name} لدى {branch_name}.',
                        ],
                    ],
                ],
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['messages.email.default.content.ar']);

        $this->withoutMiddleware()->putJson(
            '/api/v1/notification-templates/'.self::ACCOUNT_TEMPLATE_KEY,
            [
                'name' => [
                    'en' => 'Account Created',
                    'ar' => 'إنشاء الحساب',
                ],
                'messages' => [
                    'in_app' => [
                        'content' => [
                            'en' => '{user_name} can now access {app_name} with {account_email}.',
                            'fr' => 'Bienvenue {user_name}.',
                        ],
                    ],
                ],
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['messages.in_app.default.content.fr']);
    }

    protected function fakeSettings(array $supportedLocales = ['en', 'ar'], string $defaultLocale = 'en'): void
    {
        app()->instance('setting', new class($supportedLocales, $defaultLocale)
        {
            public function __construct(private readonly array $supportedLocales, private readonly string $defaultLocale) {}

            public function get(string $key, mixed $default = null): mixed
            {
                if (str_starts_with($key, 'services.notifications.') && str_ends_with($key, '.enabled')) {
                    return true;
                }

                return match ($key) {
                    'supported_locales' => $this->supportedLocales,
                    'default_locale' => $this->defaultLocale,
                    default => $default,
                };
            }

            public function set(array $data): array
            {
                return $data;
            }
        });
    }

    /**
     * Fake the Service registry notification category.
     *
     * @param  array<int, string>  $channels
     * @param  array<int, string>  $disabledChannels
     */
    protected function fakeServiceRegistry(array $channels, array $disabledChannels = []): void
    {
        app()->instance(ServiceRegistryServiceInterface::class, new class($channels, $disabledChannels) implements ServiceRegistryServiceInterface
        {
            public function __construct(private array $channels, private array $disabledChannels) {}

            public function all(): array
            {
                return [
                    [
                        'key' => 'notifications',
                        'label' => 'Notifications',
                        'description' => null,
                        'icon' => 'tabler-bell',
                        'order' => 1,
                        'services' => array_map(fn (string $channel) => [
                            'key' => $channel,
                            'original_key' => "notifications.$channel",
                            'category' => 'notifications',
                            'name' => ucfirst(str_replace('_', ' ', $channel)),
                            'description' => null,
                            'icon' => 'tabler-bell',
                            'color' => '#2563eb',
                            'order' => 1,
                            'is_default' => $channel === 'email',
                            'values' => [
                                'enabled' => ! in_array($channel, $this->disabledChannels, true),
                            ],
                            'settings' => [],
                        ], $this->channels),
                    ],
                ];
            }

            public function category(string $key): ?array
            {
                foreach ($this->all() as $category) {
                    if (($category['key'] ?? null) === $key) {
                        return $category;
                    }
                }

                return null;
            }

            public function service(string $categoryKey, string $serviceKey): ?array
            {
                $category = $this->category($categoryKey);
                if (! $category) {
                    return null;
                }

                foreach ($category['services'] ?? [] as $service) {
                    if (($service['key'] ?? null) === $serviceKey) {
                        return $service;
                    }
                }

                return null;
            }

            public function show(string $category, string $service): array
            {
                return $this->service($category, $service) ?? [];
            }

            public function rules(string $category, string $service, array $data): array
            {
                return [];
            }

            public function attributes(string $category, string $service): array
            {
                return [];
            }

            public function update(string $category, string $service, array $data): array
            {
                return $this->service($category, $service) ?? [];
            }
        });
    }
}
