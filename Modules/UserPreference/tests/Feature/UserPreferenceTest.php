<?php

namespace Modules\UserPreference\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\User\Models\User;
use Modules\UserPreference\Providers\DeferredUserPreferenceServiceProvider;
use Modules\UserPreference\Services\UserPreference\UserPreferenceService;
use Modules\UserPreference\Services\UserPreference\UserPreferenceServiceInterface;
use Tests\TestCase;

class UserPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_provider_is_deferred_and_resolves_one_stateless_service(): void
    {
        $provider = new DeferredUserPreferenceServiceProvider($this->app);

        $this->assertTrue($provider->isDeferred());
        $this->assertContains(UserPreferenceServiceInterface::class, $provider->provides());

        $service = app(UserPreferenceServiceInterface::class);
        $this->assertInstanceOf(UserPreferenceService::class, $service);
        $this->assertSame($service, app(UserPreferenceServiceInterface::class));
    }

    public function test_preferences_are_scoped_to_the_user_and_batched(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $preferences = app(UserPreferenceServiceInterface::class);

        $preferences->setMany($first, ['ui.theme' => 'dark', 'ui.density' => 'compact']);
        $preferences->set($second, 'ui.theme', 'light');

        $this->assertSame('dark', $preferences->get($first, 'ui.theme'));
        $this->assertSame('light', $preferences->get($second, 'ui.theme'));
        $this->assertSame(['ui.density' => 'compact', 'ui.theme' => 'dark'], $preferences->all($first));
        $this->assertDatabaseCount('user_preferences', 3);
    }

    public function test_plain_updates_preserve_encryption_and_translation(): void
    {
        $user = User::factory()->create();
        $preferences = app(UserPreferenceServiceInterface::class);

        $preferences->set($user, 'private.signature', 'Hello', encrypted: true, translatable: true, locale: 'en');
        $preferences->set($user, 'private.signature', 'مرحبا', locale: 'ar');

        $row = DB::table('user_preferences')->where('user_id', $user->id)->first();
        $this->assertTrue((bool) $row->is_encrypted);
        $this->assertTrue((bool) $row->is_translatable);
        $this->assertStringNotContainsString('Hello', $row->payload);
        $this->assertSame('Hello', $preferences->get($user, 'private.signature', locale: 'en'));
        $this->assertSame('مرحبا', $preferences->get($user, 'private.signature', locale: 'ar'));
    }

    public function test_invalid_identifier_is_rejected_before_a_write(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        app(UserPreferenceServiceInterface::class)->set($user, 'Invalid Key', true);
    }

    public function test_api_only_exposes_the_authenticated_users_ui_preferences(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $preferences = app(UserPreferenceServiceInterface::class);
        $preferences->set($user, 'private.token', 'secret', encrypted: true);
        $preferences->set($other, 'ui.theme', 'light');

        $this->actingAs($user)
            ->putJson('/api/v1/preferences/ui.theme', ['value' => 'dark'])
            ->assertOk()
            ->assertExactJson(['body' => ['ui.theme' => 'dark']]);

        $this->actingAs($user)
            ->getJson('/api/v1/preferences')
            ->assertOk()
            ->assertExactJson(['body' => ['ui.theme' => 'dark']]);

        $this->actingAs($user)
            ->getJson('/api/v1/preferences/private.token')
            ->assertNotFound();
    }

    public function test_api_rejects_missing_and_oversized_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/preferences/ui.theme', [])
            ->assertUnprocessable();

        $this->actingAs($user)
            ->putJson('/api/v1/preferences/ui.theme', ['value' => str_repeat('x', 16385)])
            ->assertUnprocessable();

        $this->assertDatabaseCount('user_preferences', 0);
    }

    public function test_dashboard_layout_and_filters_follow_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $layout = [['key' => 'total_sales', 'visible' => true, 'x' => 3, 'y' => 0]];
        $filters = ['from' => '2026-09-01', 'to' => '2026-09-27', 'branch_id' => null];

        $this->actingAs($user)
            ->putJson('/api/v1/preferences/ui.dashboard.layout', ['value' => $layout])
            ->assertOk();
        $this->actingAs($user)
            ->putJson('/api/v1/preferences/ui.dashboard.filters', ['value' => $filters])
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/v1/preferences')
            ->assertOk()
            ->assertExactJson(['body' => [
                'ui.dashboard.filters' => $filters,
                'ui.dashboard.layout' => $layout,
            ]]);

        $this->actingAs($other)
            ->getJson('/api/v1/preferences')
            ->assertOk()
            ->assertExactJson(['body' => []]);
    }

    public function test_analytics_dashboard_preferences_are_isolated_by_view_and_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $keys = [
            'gift_card_analytics', 'inventory_analytics', 'reservation_analytics',
            'accounting_overview', 'delivery_dashboard', 'financial_analytics',
            'payroll_analytics', 'hr_dashboard',
        ];
        $expected = [];

        foreach ($keys as $key) {
            $value = [
                'layout' => [['key' => $key, 'visible' => false, 'x' => 3, 'y' => 2]],
                'filters' => ['from' => '2026-09-01', 'to' => '2026-09-27'],
            ];
            $expected["ui.{$key}"] = $value;
            $this->actingAs($user)
                ->putJson("/api/v1/preferences/ui.{$key}", ['value' => $value])
                ->assertOk();
        }

        $this->actingAs($user)
            ->getJson('/api/v1/preferences')
            ->assertOk()
            ->assertExactJson(['body' => $expected]);

        $this->actingAs($other)
            ->getJson('/api/v1/preferences')
            ->assertOk()
            ->assertExactJson(['body' => []]);
    }

    public function test_api_requires_authentication_and_can_delete_a_preference(): void
    {
        $this->getJson('/api/v1/preferences')->assertUnauthorized();

        $user = User::factory()->create();
        $preferences = app(UserPreferenceServiceInterface::class);

        $this->actingAs($user)
            ->getJson('/api/v1/preferences/ui.theme')
            ->assertOk()
            ->assertExactJson([]);

        $preferences->set($user, 'ui.theme', 'dark');

        $this->actingAs($user)
            ->deleteJson('/api/v1/preferences/ui.theme')
            ->assertOk();

        $this->assertNull($preferences->get($user, 'ui.theme'));
    }
}
