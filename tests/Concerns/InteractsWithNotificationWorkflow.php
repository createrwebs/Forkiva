<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use Modules\Branch\Models\Branch;
use Modules\Delivery\Models\DeliveryDriver;
use Modules\Delivery\Models\OrderDelivery;
use Modules\Hr\Models\Employee;
use Modules\Notification\Models\NotificationWorkflow;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Service\Services\ServiceRegistry\ServiceRegistryServiceInterface;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\Role;
use Modules\User\Models\User;
use ReflectionProperty;
use Yadahan\AuthenticationLog\AuthenticationLog;

trait InteractsWithNotificationWorkflow
{
    private const UNRESOLVED_NOTIFICATION_PLACEHOLDER_PATTERN = '/\{\{\s*[A-Za-z_][A-Za-z0-9_]*\s*\}\}|\{[A-Za-z_][A-Za-z0-9_]*\}/';

    /**
     * Fake settings used by notification services.
     */
    protected function fakeSettings(bool $pushEnabled = true, ?string $provider = null): void
    {
        app()->instance('setting', new class($pushEnabled, $provider)
        {
            public function __construct(private readonly bool $pushEnabled, private readonly ?string $provider) {}

            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'supported_locales' => ['en', 'ar'],
                    'default_locale' => 'en',
                    'app_name' => 'Forkiva',
                    'services.notifications.email.enabled' => true,
                    'services.notifications.in_app.enabled' => true,
                    'services.notifications.push_notification.enabled' => $this->pushEnabled,
                    'services.notifications.push_notification.provider' => $this->provider,
                    'services.notifications.push_notification.fcm.credentials' => '{"type":"service_account","project_id":"forkiva"}',
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
     * Seed one or more custom recipient roles used by notification workflows.
     *
     * @param  array<int, string>  $roles
     */
    protected function seedCustomRecipientRoles(array $roles): void
    {
        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['name' => $role, 'guard_name' => config('auth.defaults.guard', 'web')],
                [
                    'display_name' => ['en' => str($role)->replace('_', ' ')->title()->toString()],
                    'built_in' => false,
                ]
            );
        }
    }

    /**
     * Fake the Service registry notification category.
     *
     * @param  array<int, string>  $channels
     * @param  array<int, string>  $disabledChannels
     */
    protected function fakeServiceRegistry(array $channels, array $disabledChannels = []): void
    {
        $registry = new class($channels, $disabledChannels) implements ServiceRegistryServiceInterface
        {
            public function __construct(private readonly array $channels, private readonly array $disabledChannels) {}

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
                return [];
            }
        };

        app()->instance(ServiceRegistryServiceInterface::class, $registry);
    }

    /**
     * Create an active customer-role user.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createCustomerUser(array $attributes = []): User
    {
        return $this->createUserWithRole(DefaultRole::Customer->value, $attributes);
    }

    /**
     * Create a user with one recipient role.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createUserWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Override one workflow channel matrix for a test.
     *
     * @param  array<string, mixed>  $channels
     */
    protected function setWorkflowChannels(string $workflowKey, array $channels): void
    {
        NotificationWorkflow::query()
            ->where('key', $workflowKey)
            ->firstOrFail()
            ->update(['channels' => $channels]);
    }

    /**
     * Create a delivery driver linked to a user.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createDeliveryDriver(Branch $branch, User $user, array $attributes = []): DeliveryDriver
    {
        $employee = Employee::factory()
            ->forBranch($branch->id)
            ->create([
                'user_id' => $user->id,
            ]);

        return DeliveryDriver::factory()->create([
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            ...$attributes,
        ]);
    }

    /**
     * Create an order delivery linked to the supplied branch actors.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createOrderDelivery(Branch $branch, User $customer, ?DeliveryDriver $driver = null, array $attributes = []): OrderDelivery
    {
        $order = $this->createOrder($branch, [
            'customer_id' => $customer->id,
        ]);

        return OrderDelivery::factory()->create([
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'driver_id' => $driver?->id,
            ...$attributes,
        ]);
    }

    /**
     * Create a minimal order for notification tests.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createOrder(Branch $branch, array $attributes = []): Order
    {
        return Order::query()->create([
            'branch_id' => $branch->id,
            'status' => OrderStatus::Pending,
            'type' => OrderType::Delivery,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'currency' => $branch->currency,
            'currency_rate' => 1,
            'subtotal' => 20,
            'total' => 20,
            'due_amount' => 20,
            'guest_count' => 1,
            'order_date' => now(),
            ...$attributes,
        ]);
    }

    /**
     * Create an authentication log through the package relation.
     */
    protected function createAuthenticationLog(User $user, string $ipAddress, string $userAgent, mixed $loginAt): AuthenticationLog
    {
        $log = new AuthenticationLog([
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'login_at' => $loginAt,
        ]);

        $user->authentications()->save($log);

        return $log;
    }

    /**
     * Get profile update template variables.
     *
     * @return array<string, string>
     */
    protected function profileVariables(): array
    {
        return [
            'user_name' => 'Ayman',
            'change_type' => 'name',
            'updated_at' => now()->toDateTimeString(),
            'ip_address' => '127.0.0.1',
            'app_name' => 'Forkiva',
        ];
    }

    /**
     * Assert every rendered notification sent during a workflow test is production-ready.
     */
    protected function assertSentRenderedNotificationsHaveNoUnresolvedPlaceholders(): void
    {
        $fake = Notification::getFacadeRoot();

        if (! $fake instanceof NotificationFake) {
            return;
        }

        foreach ($fake->sentNotifications() as $notifiables) {
            foreach ($notifiables as $notificationsByClass) {
                foreach ($notificationsByClass[RenderedTemplateNotification::class] ?? [] as $sent) {
                    $notification = $sent['notification'] ?? null;

                    if ($notification instanceof RenderedTemplateNotification) {
                        $this->assertRenderedNotificationHasNoUnresolvedPlaceholders($notification);
                    }
                }
            }
        }
    }

    /**
     * Assert a rendered notification payload contains no template placeholders.
     */
    protected function assertRenderedNotificationHasNoUnresolvedPlaceholders(RenderedTemplateNotification $notification): void
    {
        $property = new ReflectionProperty($notification, 'renderedChannels');
        $property->setAccessible(true);
        $renderedChannels = $property->getValue($notification);

        foreach ($renderedChannels as $channel => $payload) {
            if (! is_array($payload)) {
                continue;
            }

            $this->assertNotificationPayloadHasNoUnresolvedPlaceholders($payload, (string) $channel);
        }
    }

    /**
     * Assert all translated payload fields are free of unresolved placeholders.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function assertNotificationPayloadHasNoUnresolvedPlaceholders(array $payload, string $channel): void
    {
        foreach (['title', 'subject', 'content'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $this->assertDoesNotMatchRegularExpression(
                    self::UNRESOLVED_NOTIFICATION_PLACEHOLDER_PATTERN,
                    $payload[$field],
                    "Unresolved notification placeholder in {$channel}.{$field}: {$payload[$field]}"
                );
            }
        }

        foreach (($payload['translations'] ?? []) as $locale => $translation) {
            if (! is_array($translation)) {
                continue;
            }

            foreach (['title', 'subject', 'content'] as $field) {
                if (! isset($translation[$field]) || ! is_string($translation[$field])) {
                    continue;
                }

                $this->assertDoesNotMatchRegularExpression(
                    self::UNRESOLVED_NOTIFICATION_PLACEHOLDER_PATTERN,
                    $translation[$field],
                    "Unresolved notification placeholder in {$channel}.translations.{$locale}.{$field}: {$translation[$field]}"
                );
            }
        }
    }

    /**
     * Assertion helper for individual notification callbacks.
     */
    protected function renderedNotificationHasNoUnresolvedPlaceholders(RenderedTemplateNotification $notification): bool
    {
        $this->assertRenderedNotificationHasNoUnresolvedPlaceholders($notification);

        return true;
    }

    /**
     * Check a translated notification title against the base template title.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function notificationTitleMatchesBase(array $payload, string $baseTitle, string $locale = 'en'): bool
    {
        return $this->notificationTextMatchesBase(
            $payload['translations'][$locale]['title'] ?? null,
            $baseTitle
        );
    }

    /**
     * Role-specific variants keep the base title then append a recipient suffix.
     */
    protected function notificationTextMatchesBase(mixed $value, string $baseText): bool
    {
        $value = (string) $value;

        return $value === $baseText || str_starts_with($value, "{$baseText} - ");
    }
}
