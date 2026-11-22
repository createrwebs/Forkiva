<?php

namespace Modules\Inventory\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Modules\Inventory\Enums\InventoryStockSyncStatus;
use Modules\Inventory\Models\InventoryStockSyncJob;
use Modules\Inventory\Services\StockSyncJob\StockSyncJobServiceInterface;
use Throwable;

class ProcessInventoryStockSyncJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public bool $failOnTimeout = true;

    public int $timeout;

    /**
     * Create a new queued stock synchronization job.
     */
    public function __construct(
        protected int $stockSyncJobId,
        protected ?string $dispatchToken = null,
    ) {
        $this->tries = max(1, (int) config('inventory.stock_sync.attempts', 5));
        $this->timeout = max(1, (int) config('inventory.stock_sync.job_timeout', 600));
    }

    /**
     * Get queue middleware for this synchronization record.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        $leaseSeconds = max(30, (int) config('inventory.stock_sync.queue_retry_after', 900));

        return [
            (new WithoutOverlapping("inventory-stock-sync:$this->stockSyncJobId"))
                ->dontRelease()
                ->expireAfter($leaseSeconds),
        ];
    }

    /**
     * Execute the queued operation.
     *
     * @throws Throwable
     */
    public function handle(StockSyncJobServiceInterface $service): void
    {
        $service->process($this->stockSyncJobId, $this->dispatchToken);
    }

    /**
     * Return retry delays in seconds.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return array_map(
            'intval',
            (array) config('inventory.stock_sync.backoff', [15, 30, 60, 180, 360]),
        );
    }

    /**
     * Persist the terminal queue failure.
     */
    public function failed(Throwable $exception): void
    {
        $query = InventoryStockSyncJob::query()
            ->withOutGlobalBranchPermission()
            ->whereKey($this->stockSyncJobId);

        if ($this->dispatchToken !== null) {
            $query->where('dispatch_token', $this->dispatchToken)
                ->whereIn('status', [
                    InventoryStockSyncStatus::PendingStock->value,
                    InventoryStockSyncStatus::ProcessingStock->value,
                    InventoryStockSyncStatus::PendingAccounting->value,
                    InventoryStockSyncStatus::Failed->value,
                ]);
        } else {
            $query->whereIn('status', [
                InventoryStockSyncStatus::ProcessingStock->value,
                InventoryStockSyncStatus::PendingAccounting->value,
                InventoryStockSyncStatus::Failed->value,
            ]);
        }

        $query
            ->update([
                'status' => InventoryStockSyncStatus::Failed,
                'last_error' => $exception->getMessage(),
                'dispatched_at' => null,
                'dispatch_token' => null,
                'processing_started_at' => null,
            ]);
    }
}
