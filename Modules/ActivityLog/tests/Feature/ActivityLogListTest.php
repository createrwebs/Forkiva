<?php

namespace Modules\ActivityLog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ActivityLog\Models\ActivityLog;
use Modules\User\Models\User;
use Tests\TestCase;

class ActivityLogListTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_is_cached_and_invalidated_by_activity_log_and_user_changes(): void
    {
        $user = User::factory()->create(['name' => 'Original user']);
        ActivityLog::query()->create([
            'log_name' => 'users',
            'event' => 'created',
            'description' => 'Created',
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);

        $firstList = ActivityLog::list();

        $this->assertTrue($firstList->contains(fn (array $item): bool => $item === [
            'event' => 'created',
            'log_name' => 'users',
            'user_id' => $user->id,
            'user_name' => 'Original user',
        ]));

        ActivityLog::query()->create([
            'log_name' => 'orders',
            'event' => 'updated',
            'description' => 'Updated',
        ]);

        $this->assertTrue(ActivityLog::list()->contains(
            fn (array $item): bool => $item['event'] === 'updated'
                && $item['log_name'] === 'orders'
        ));

        $user->update(['name' => 'Updated user']);

        $this->assertTrue(ActivityLog::list()->contains(
            fn (array $item): bool => $item['user_id'] === $user->id
                && $item['user_name'] === 'Updated user'
        ));
    }
}
