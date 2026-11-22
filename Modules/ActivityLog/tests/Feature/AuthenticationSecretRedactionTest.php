<?php

namespace Modules\ActivityLog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ActivityLog\Models\ActivityLog;
use Modules\ActivityLog\Support\SensitiveProperties;
use Modules\ActivityLog\Transformers\Api\V1\ActivityLogShowResource;
use Modules\User\Models\User;
use Spatie\Activitylog\ActivityLogStatus;
use Tests\TestCase;

class AuthenticationSecretRedactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_do_not_log_decrypted_secrets_or_password_hashes(): void
    {
        config(['activitylog.enabled' => true]);
        app(ActivityLogStatus::class)->enable();
        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_method' => 'authenticator',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => [bcrypt('ABCDE-12345')],
            'two_factor_confirmed_at' => now(),
        ])->save();
        $user->update(['name' => 'Updated name']);

        $activities = ActivityLog::query()->where('subject_type', User::class)->get();
        $this->assertNotEmpty($activities);
        foreach ($activities as $activity) {
            foreach (['old', 'attributes'] as $section) {
                foreach (SensitiveProperties::FIELDS as $field) {
                    $this->assertArrayNotHasKey($field, $activity->properties[$section]);
                }
            }
        }
        $this->assertSame('Updated name', $activities->last()->properties['attributes']['name']);
    }

    public function test_legacy_logs_are_redacted_on_read(): void
    {
        $activity = ActivityLog::query()->create([
            'log_name' => 'users.updated',
            'event' => 'updated',
            'description' => 'Updated',
            'properties' => [
                'old' => ['two_factor_secret' => 'fixture-secret', 'name' => 'Old name'],
                'attributes' => ['password' => 'fixture-hash', 'name' => 'New name'],
            ],
        ]);
        $response = (new ActivityLogShowResource($activity))->resolve();
        $this->assertArrayNotHasKey('two_factor_secret', $response['properties']['old']);
        $this->assertArrayNotHasKey('password', $response['properties']['attributes']);
    }
}
