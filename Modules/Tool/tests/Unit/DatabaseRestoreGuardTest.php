<?php

namespace Modules\Tool\Tests\Unit;

use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Queue\QueueManager;
use Mockery;
use Modules\Tool\Services\Database\DatabaseRestoreGuard;
use RuntimeException;
use Tests\TestCase;

class DatabaseRestoreGuardTest extends TestCase
{
    public function test_guard_reactivates_http_and_queue_processing_after_a_failure(): void
    {
        config([
            'queue.default' => 'database',
            'tool.database.queue' => 'default',
            'tool.database.pause_queue_during_restore' => true,
            'tool.database.maintenance_during_restore' => true,
        ]);

        $maintenance = new InMemoryMaintenanceMode;
        $queues = Mockery::mock(QueueManager::class);
        $queues->shouldReceive('isPaused')->once()->with('database', 'default')->andReturnFalse();
        $queues->shouldReceive('pause')->once()->with('database', 'default');
        $queues->shouldReceive('resume')->once()->with('database', 'default');

        $guard = new DatabaseRestoreGuard($maintenance, $queues);

        try {
            $guard->run(function () use ($maintenance): void {
                $this->assertTrue($maintenance->active());

                throw new RuntimeException('restore failed');
            });

            $this->fail('The restore failure should be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('restore failed', $exception->getMessage());
        }

        $this->assertFalse($maintenance->active());
    }

    public function test_guard_preserves_preexisting_maintenance_and_queue_pause_state(): void
    {
        config([
            'queue.default' => 'database',
            'tool.database.queue' => 'critical',
            'tool.database.pause_queue_during_restore' => true,
            'tool.database.maintenance_during_restore' => true,
        ]);

        $maintenance = new InMemoryMaintenanceMode(true);
        $queues = Mockery::mock(QueueManager::class);
        $queues->shouldReceive('isPaused')->once()->with('database', 'critical')->andReturnTrue();
        $queues->shouldNotReceive('pause');
        $queues->shouldNotReceive('resume');

        $result = (new DatabaseRestoreGuard($maintenance, $queues))->run(fn (): string => 'restored');

        $this->assertSame('restored', $result);
        $this->assertTrue($maintenance->active());
    }
}

class InMemoryMaintenanceMode implements MaintenanceMode
{
    private array $payload = [];

    public function __construct(private bool $active = false) {}

    public function activate(array $payload): void
    {
        $this->active = true;
        $this->payload = $payload;
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->payload = [];
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function data(): array
    {
        return $this->payload;
    }
}
