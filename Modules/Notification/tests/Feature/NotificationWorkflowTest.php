<?php

namespace Modules\Notification\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notification\Database\Seeders\NotificationDatabaseSeeder;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Models\NotificationWorkflow;
use Modules\Service\Services\ServiceRegistry\ServiceRegistryServiceInterface;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\Role;
use Spatie\Activitylog\ActivityLogStatus;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class NotificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSettings();
        $this->seedRecipientRoles();
    }

    public function test_seeded_workflows_exist_with_dynamic_channels(): void
    {
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $this->assertSame(NotificationTemplate::query()->count(), NotificationWorkflow::query()->count());

        foreach (['account_created', 'account_activated', 'profile_updated'] as $key) {
            $this->assertDatabaseHas('notification_workflows', [
                'key' => $key,
            ]);
        }

        $workflow = NotificationWorkflow::query()
            ->where('key', 'account_created')
            ->firstOrFail();

        $this->assertSame('Account Created', $workflow->getTranslation('name', 'en'));
        $this->assertSame('تم إنشاء الحساب', $workflow->getTranslation('name', 'ar'));
        $this->assertSame('Sent when an account is created.', $workflow->getTranslation('description', 'en'));
        $this->assertSame('يُرسل عند إنشاء الحساب.', $workflow->getTranslation('description', 'ar'));
        $this->assertTrue($workflow->channels['email'][DefaultRole::Customer->value]);
        $this->assertTrue($workflow->channels['in_app'][DefaultRole::Customer->value]);
        $this->assertArrayNotHasKey('push_notification', $workflow->channels);

        $response = $this->withoutMiddleware()
            ->getJson('/api/v1/notification-workflows')
            ->assertOk()
            ->assertJsonMissingPath('body.data.0.channels')
            ->assertJsonMissingPath('body.data.0.available_recipients')
            ->assertJsonMissingPath('body.data.0.channel_states')
            ->assertJsonMissingPath('body.data.0.description')
            ->assertJsonMissingPath('body.headers')
            ->assertJsonMissingPath('body.available_channels')
            ->assertJsonMissingPath('body.available_recipients');

        $this->withoutMiddleware()
            ->getJson('/api/v1/notification-workflows/account_created')
            ->assertOk()
            ->assertJsonPath('body.channel_states.email.customer', true);

        $showResponse = $this->withoutMiddleware()
            ->getJson('/api/v1/notification-workflows/account_created')
            ->assertOk();

        $recipientKeys = collect($showResponse->json('body.available_recipients'))->pluck('key');

        $this->assertTrue($recipientKeys->contains(DefaultRole::Customer->value));
        $this->assertFalse($recipientKeys->contains(DefaultRole::SuperAdmin->value));
    }

    public function test_disabled_service_channels_are_not_seeded_listed_or_toggleable(): void
    {
        $this->fakeServiceRegistry(['email', 'in_app', 'push_notification'], ['push_notification']);
        $this->seed(NotificationDatabaseSeeder::class);

        $workflow = NotificationWorkflow::query()
            ->where('key', 'account_created')
            ->firstOrFail();

        $this->assertTrue($workflow->channels['email'][DefaultRole::Customer->value]);
        $this->assertTrue($workflow->channels['in_app'][DefaultRole::Customer->value]);
        $this->assertArrayNotHasKey('push_notification', $workflow->channels);

        $response = $this->withoutMiddleware()
            ->getJson('/api/v1/notification-workflows')
            ->assertOk();

        $response
            ->assertJsonMissingPath('body.headers')
            ->assertJsonMissingPath('body.available_channels')
            ->assertJsonMissingPath('body.available_recipients');

        $this->withoutMiddleware()
            ->putJson('/api/v1/notification-workflows/account_created', [
                'channels' => [
                    'push_notification' => [
                        DefaultRole::Customer->value => true,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channels.push_notification');
    }

    public function test_template_unsupported_workflow_channels_are_not_seeded_or_toggleable(): void
    {
        $this->fakeServiceRegistry(['email', 'in_app', 'push_notification', 'sms', 'whatsapp']);
        $this->seed(NotificationDatabaseSeeder::class);

        $workflow = NotificationWorkflow::query()
            ->where('key', 'profile_updated')
            ->firstOrFail();

        $this->assertTrue($workflow->channels['email'][DefaultRole::Customer->value]);
        $this->assertTrue($workflow->channels['in_app'][DefaultRole::Customer->value]);
        $this->assertArrayNotHasKey('push_notification', $workflow->channels);

        $this->withoutMiddleware()
            ->putJson('/api/v1/notification-workflows/profile_updated', [
                'channels' => [
                    'push_notification' => [
                        DefaultRole::Customer->value => true,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channels.push_notification');
    }

    public function test_seeded_workflows_use_conservative_default_recipients_and_channels(): void
    {
        $this->fakeServiceRegistry(['email', 'in_app', 'push_notification', 'sms', 'whatsapp']);
        $this->seed(NotificationDatabaseSeeder::class);

        $deliveryAssigned = NotificationWorkflow::query()
            ->where('key', 'delivery_assigned')
            ->firstOrFail();

        $this->assertTrue($deliveryAssigned->channels['in_app'][DefaultRole::Customer->value]);
        $this->assertTrue($deliveryAssigned->channels['in_app'][DefaultRole::Driver->value]);
        $this->assertTrue($deliveryAssigned->channels['push_notification'][DefaultRole::Driver->value]);
        $this->assertFalse($deliveryAssigned->channels['in_app'][DefaultRole::Dispatcher->value]);
        $this->assertFalse($deliveryAssigned->channels['in_app'][DefaultRole::Admin->value]);
        $this->assertFalse($deliveryAssigned->channels['sms'][DefaultRole::Customer->value]);
        $this->assertFalse($deliveryAssigned->channels['whatsapp'][DefaultRole::Driver->value]);

        $orderStatusChanged = NotificationWorkflow::query()
            ->where('key', 'order_status_changed')
            ->firstOrFail();

        $this->assertTrue($orderStatusChanged->channels['in_app'][DefaultRole::Customer->value]);
        $this->assertTrue($orderStatusChanged->channels['push_notification'][DefaultRole::Customer->value]);
        $this->assertFalse($orderStatusChanged->channels['email'][DefaultRole::Customer->value]);
        $this->assertFalse($orderStatusChanged->channels['in_app'][DefaultRole::Waiter->value]);
        $this->assertFalse($orderStatusChanged->channels['in_app'][DefaultRole::Admin->value]);
        $this->assertFalse($orderStatusChanged->channels['sms'][DefaultRole::Customer->value]);
        $this->assertFalse($orderStatusChanged->channels['whatsapp'][DefaultRole::Customer->value]);

        $employeeCreated = NotificationWorkflow::query()
            ->where('key', 'employee_created')
            ->firstOrFail();

        $this->assertTrue($employeeCreated->channels['email'][DefaultRole::Staff->value]);
        $this->assertTrue($employeeCreated->channels['in_app'][DefaultRole::Staff->value]);
        $this->assertTrue($employeeCreated->channels['push_notification'][DefaultRole::Staff->value]);
        $this->assertFalse($employeeCreated->channels['in_app'][DefaultRole::HrManager->value]);
        $this->assertFalse($employeeCreated->channels['sms'][DefaultRole::Staff->value]);

        $leaveSubmitted = NotificationWorkflow::query()
            ->where('key', 'leave_request_submitted')
            ->firstOrFail();

        $this->assertTrue($leaveSubmitted->channels['in_app'][DefaultRole::Staff->value]);
        $this->assertTrue($leaveSubmitted->channels['in_app'][DefaultRole::HrManager->value]);
        $this->assertTrue($leaveSubmitted->channels['push_notification'][DefaultRole::HrOfficer->value]);
        $this->assertFalse($leaveSubmitted->channels['email'][DefaultRole::Staff->value]);

        $giftCardIssued = NotificationWorkflow::query()
            ->where('key', 'gift_card_issued')
            ->firstOrFail();

        $this->assertTrue($giftCardIssued->channels['email'][DefaultRole::Customer->value]);
        $this->assertTrue($giftCardIssued->channels['in_app'][DefaultRole::Customer->value]);
        $this->assertFalse($giftCardIssued->channels['in_app'][DefaultRole::Manager->value]);
        $this->assertFalse($giftCardIssued->channels['sms'][DefaultRole::Customer->value]);
        $this->assertFalse($giftCardIssued->channels['whatsapp'][DefaultRole::Customer->value]);
    }

    public function test_reseeding_upgrades_untouched_noisy_workflow_defaults(): void
    {
        $this->fakeServiceRegistry(['email', 'in_app', 'push_notification', 'sms', 'whatsapp']);
        $this->seed(NotificationDatabaseSeeder::class);

        $workflow = NotificationWorkflow::query()
            ->where('key', 'delivery_assigned')
            ->firstOrFail();
        $createdAt = $workflow->created_at;

        $oldNoisyChannels = [];
        foreach ($workflow->channels as $channel => $roles) {
            $oldNoisyChannels[$channel] = array_fill_keys(array_keys($roles), true);
        }

        $workflow->timestamps = false;
        $workflow->channels = $oldNoisyChannels;
        $workflow->updated_at = $createdAt;
        $workflow->save();

        $this->seed(NotificationDatabaseSeeder::class);

        $workflow->refresh();

        $this->assertTrue($workflow->channels['in_app'][DefaultRole::Customer->value]);
        $this->assertTrue($workflow->channels['in_app'][DefaultRole::Driver->value]);
        $this->assertFalse($workflow->channels['in_app'][DefaultRole::Dispatcher->value]);
        $this->assertFalse($workflow->channels['sms'][DefaultRole::Customer->value]);
        $this->assertFalse($workflow->channels['whatsapp'][DefaultRole::Driver->value]);
    }

    public function test_reseeding_merges_new_dynamic_channels_without_overwriting_existing_state(): void
    {
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $workflow = NotificationWorkflow::query()
            ->where('key', 'account_created')
            ->firstOrFail();

        $this->travel(1)->seconds();
        try {
            $workflow->update([
                'channels' => [
                    ...$workflow->channels,
                    'email' => [
                        ...$workflow->channels['email'],
                        DefaultRole::Customer->value => false,
                    ],
                ],
            ]);
        } finally {
            $this->travelBack();
        }

        $this->fakeServiceRegistry(['email', 'in_app', 'push_notification']);
        $this->seed(NotificationDatabaseSeeder::class);

        $workflow->refresh();

        $this->assertFalse($workflow->channels['email'][DefaultRole::Customer->value]);
        $this->assertTrue($workflow->channels['in_app'][DefaultRole::Customer->value]);
        $this->assertFalse($workflow->channels['push_notification'][DefaultRole::Customer->value]);
    }

    public function test_newly_enabled_supported_service_is_shown_and_can_be_saved_without_reseeding(): void
    {
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $this->fakeServiceRegistry(['email', 'in_app', 'push_notification']);

        $this->withoutMiddleware()
            ->getJson('/api/v1/notification-workflows/account_created')
            ->assertOk()
            ->assertJsonPath('body.channel_support.push_notification', true)
            ->assertJsonPath('body.channel_states.push_notification.customer', false);

        $this->withoutMiddleware()
            ->putJson('/api/v1/notification-workflows/account_created', [
                'channels' => [
                    'email' => [
                        DefaultRole::Customer->value => true,
                    ],
                    'in_app' => [
                        DefaultRole::Customer->value => true,
                    ],
                    'push_notification' => [
                        DefaultRole::Customer->value => true,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('body.channel_states.push_notification.customer', true);

        $workflow = NotificationWorkflow::query()
            ->where('key', 'account_created')
            ->firstOrFail();

        $this->assertTrue($workflow->channels['push_notification'][DefaultRole::Customer->value]);
    }

    public function test_update_saves_workflow_recipient_matrix(): void
    {
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $this->withoutMiddleware()
            ->putJson('/api/v1/notification-workflows/account_created', [
                'channels' => [
                    'email' => [
                        DefaultRole::Customer->value => false,
                    ],
                    'in_app' => [
                        DefaultRole::Customer->value => true,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('body.channel_states.email.customer', false)
            ->assertJsonPath('body.channel_states.in_app.customer', true);

        $workflow = NotificationWorkflow::query()
            ->where('key', 'account_created')
            ->firstOrFail();

        $this->assertFalse($workflow->channels['email'][DefaultRole::Customer->value]);
        $this->assertTrue($workflow->channels['in_app'][DefaultRole::Customer->value]);

        $otherWorkflow = NotificationWorkflow::query()
            ->where('key', 'account_activated')
            ->firstOrFail();

        $this->assertTrue($otherWorkflow->channels['email'][DefaultRole::Customer->value]);
    }

    public function test_unsupported_channel_is_rejected(): void
    {
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $this->withoutMiddleware()
            ->putJson('/api/v1/notification-workflows/account_created', [
                'channels' => [
                    'whatsapp' => [
                        DefaultRole::Customer->value => true,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channels.whatsapp');
    }

    public function test_unknown_roles_and_empty_recipients_are_rejected(): void
    {
        $this->fakeServiceRegistry(['email']);
        $this->seed(NotificationDatabaseSeeder::class);

        $this->withoutMiddleware()
            ->putJson('/api/v1/notification-workflows/account_created', [
                'channels' => [
                    'email' => [
                        'unknown_role' => true,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channels.email.unknown_role');

        $this->withoutMiddleware()
            ->putJson('/api/v1/notification-workflows/account_created', [
                'channels' => [
                    'email' => [
                        DefaultRole::SuperAdmin->value => true,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channels.email.super_admin');

        $this->withoutMiddleware()
            ->putJson('/api/v1/notification-workflows/account_created', [
                'channels' => [
                    'email' => [
                        DefaultRole::Customer->value => false,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channels');
    }

    public function test_create_direct_channel_update_bulk_update_and_delete_routes_are_not_available(): void
    {
        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $postResponse = $this->withoutMiddleware()
            ->postJson('/api/v1/notification-workflows', []);

        $patchResponse = $this->withoutMiddleware()
            ->patchJson('/api/v1/notification-workflows/account_created', [
                'name' => ['en' => 'Changed'],
            ]);

        $directChannelResponse = $this->withoutMiddleware()
            ->patchJson('/api/v1/notification-workflows/account_created/channels/email', [
                'enabled' => false,
            ]);

        $bulkWorkflowResponse = $this->withoutMiddleware()
            ->patchJson('/api/v1/notification-workflows/account_created/channels', [
                'enabled' => false,
            ]);

        $bulkGlobalResponse = $this->withoutMiddleware()
            ->patchJson('/api/v1/notification-workflows/channels', [
                'enabled' => false,
            ]);

        $deleteResponse = $this->withoutMiddleware()
            ->deleteJson('/api/v1/notification-workflows/account_created');

        $this->assertContains($postResponse->getStatusCode(), [404, 405]);
        $this->assertContains($patchResponse->getStatusCode(), [404, 405]);
        $this->assertContains($directChannelResponse->getStatusCode(), [404, 405]);
        $this->assertContains($bulkWorkflowResponse->getStatusCode(), [404, 405]);
        $this->assertContains($bulkGlobalResponse->getStatusCode(), [404, 405]);
        $this->assertContains($deleteResponse->getStatusCode(), [404, 405]);
    }

    public function test_channel_toggle_writes_activity_log(): void
    {
        config(['activitylog.enabled' => true]);
        app(ActivityLogStatus::class)->enable();

        $this->fakeServiceRegistry(['email', 'in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $workflow = NotificationWorkflow::query()
            ->where('key', 'account_created')
            ->firstOrFail();

        $this->withoutMiddleware()
            ->putJson('/api/v1/notification-workflows/account_created', [
                'channels' => [
                    'email' => [
                        DefaultRole::Customer->value => false,
                    ],
                    'in_app' => [
                        DefaultRole::Customer->value => true,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => NotificationWorkflow::class,
            'subject_id' => $workflow->id,
            'event' => 'updated',
            'log_name' => 'notification_workflows.updated',
        ]);

        $activity = Activity::query()
            ->where('subject_type', NotificationWorkflow::class)
            ->where('subject_id', $workflow->id)
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(false, data_get($activity->properties, 'attributes.channels.email.customer'));
    }

    /**
     * Seed default roles needed by recipient matrices.
     */
    protected function seedRecipientRoles(): void
    {
        foreach (DefaultRole::cases() as $role) {
            Role::query()->updateOrCreate(
                ['name' => $role->value, 'guard_name' => config('auth.defaults.guard', 'web')],
                [
                    'display_name' => ['en' => str($role->value)->replace('_', ' ')->title()->toString()],
                    'built_in' => true,
                ]
            );
        }
    }

    /**
     * Fake settings used by locale helpers.
     */
    protected function fakeSettings(): void
    {
        app()->instance('setting', new class
        {
            public function get(string $key, mixed $default = null): mixed
            {
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
                            'values' => [
                                'enabled' => ! in_array($channel, $this->disabledChannels, true),
                            ],
                            'settings' => [],
                        ], $this->channels),
                    ],
                ];
            }

            public function show(string $category, string $service): array
            {
                foreach ($this->all()[0]['services'] as $item) {
                    if ($category === 'notifications' && $item['key'] === $service) {
                        return $item;
                    }
                }

                return [];
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
                return $this->show($category, $service);
            }
        });
    }
}
