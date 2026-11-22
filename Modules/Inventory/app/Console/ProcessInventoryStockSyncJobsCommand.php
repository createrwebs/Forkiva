<?php

namespace Modules\Inventory\Console;

use Illuminate\Console\Command;
use Modules\Inventory\Services\StockSyncJob\StockSyncJobServiceInterface;
use Throwable;

class ProcessInventoryStockSyncJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:process-stock-sync-jobs {--limit=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch pending inventory stock synchronization jobs and recover stale claims.';

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle(StockSyncJobServiceInterface $service): int
    {
        $limit = (int) ($this->option('limit') ?: config('inventory.stock_sync.batch_size', 100));
        $dispatched = $service->dispatchDue(max(1, $limit));

        $this->components->info("Dispatched $dispatched inventory stock synchronization job(s).");

        return self::SUCCESS;
    }
}
