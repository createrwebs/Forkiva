<?php

namespace Modules\Tool\Services\Database;

use Closure;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Queue\QueueManager;

readonly class DatabaseRestoreGuard implements DatabaseRestoreGuardInterface
{
    public function __construct(
        private MaintenanceMode $maintenance,
        private QueueManager $queues,
    ) {}

    /** {@inheritDoc} */
    public function run(Closure $restore): mixed
    {
        $maintenanceActivated = false;
        $queuePaused = false;
        $queueConnection = (string) config('queue.default');
        $queue = (string) config('tool.database.queue', 'default');

        try {
            if (
                config('tool.database.pause_queue_during_restore', true)
                && $queueConnection !== 'sync'
                && ! $this->queues->isPaused($queueConnection, $queue)
            ) {
                $this->queues->pause($queueConnection, $queue);
                $queuePaused = true;
            }

            if (config('tool.database.maintenance_during_restore', true) && ! $this->maintenance->active()) {
                $this->maintenance->activate([
                    'except' => [],
                    'redirect' => null,
                    'retry' => 60,
                    'refresh' => 15,
                    'secret' => null,
                    'status' => 503,
                    'template' => null,
                ]);
                $maintenanceActivated = true;
            }

            return $restore();
        } finally {
            if ($maintenanceActivated) {
                $this->maintenance->deactivate();
            }

            if ($queuePaused) {
                $this->queues->resume($queueConnection, $queue);
            }
        }
    }
}
