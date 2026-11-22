<?php

namespace Modules\Inventory\Services\StockSyncJob;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Inventory\Enums\InventoryStockSyncOperation;
use Modules\Inventory\Enums\InventoryStockSyncStatus;
use Modules\Inventory\Jobs\ProcessInventoryStockSyncJob;
use Modules\Inventory\Models\InventoryStockSyncJob;
use Modules\Inventory\Services\StockSync\StockSyncNotRequiredException;
use Modules\Inventory\Services\StockSync\StockSyncServiceInterface;
use Modules\Order\Models\Order;
use Throwable;

class StockSyncJobService implements StockSyncJobServiceInterface
{
    /**
     * Create a new StockSyncJobService instance.
     */
    public function __construct(
        protected StockSyncServiceInterface $stock,
        protected FinancialPostingBridgeInterface $accounting,
    ) {}

    /** {@inheritDoc} */
    public function queueDeduction(Order $order): InventoryStockSyncJob
    {
        return $this->queue($order, InventoryStockSyncOperation::Deduct);
    }

    /**
     * Persist and dispatch one stock synchronization operation.
     *
     * @throws Throwable
     */
    private function queue(Order $order, InventoryStockSyncOperation $operation): InventoryStockSyncJob
    {
        $idempotencyKey = "order-stock-sync:$operation->value:$order->id";
        [$job, $shouldDispatch] = DB::transaction(function () use ($idempotencyKey, $operation, $order): array {
            $candidate = InventoryStockSyncJob::query()
                ->withOutGlobalBranchPermission()
                ->createOrFirst(
                    ['idempotency_key' => $idempotencyKey],
                    [
                        'branch_id' => $order->branch_id,
                        'order_id' => $order->id,
                        'operation' => $operation,
                        'status' => InventoryStockSyncStatus::PendingStock,
                        'payload' => [
                            'order_number' => $order->order_number,
                            'reference_no' => $order->reference_no,
                        ],
                    ],
                );

            $job = InventoryStockSyncJob::query()
                ->withOutGlobalBranchPermission()
                ->lockForUpdate()
                ->findOrFail($candidate->id);

            if ($job->status === InventoryStockSyncStatus::Failed) {
                $job->forceFill([
                    'status' => $job->movement_ids === null
                        ? InventoryStockSyncStatus::PendingStock
                        : InventoryStockSyncStatus::PendingAccounting,
                    'dispatched_at' => null,
                    'dispatch_token' => null,
                    'processing_started_at' => null,
                    'completed_at' => null,
                    'last_error' => null,
                ])->save();
            }

            $shouldDispatch = in_array($job->status, [
                InventoryStockSyncStatus::PendingStock,
                InventoryStockSyncStatus::PendingAccounting,
            ], true) && ($job->dispatched_at === null || $job->dispatched_at->lessThanOrEqualTo($this->staleDispatchCutoff()));

            if ($shouldDispatch) {
                $job->forceFill([
                    'dispatched_at' => now(),
                    'dispatch_token' => (string) Str::uuid(),
                ])->save();
            }

            return [$job, $shouldDispatch];
        });

        if ($shouldDispatch) {
            $this->dispatchClaimed($job);
        }

        return $job;
    }

    /**
     * Get the oldest dispatch timestamp that still represents queued work.
     */
    private function staleDispatchCutoff(): CarbonInterface
    {
        $configured = (int) config('inventory.stock_sync.dispatch_stale_seconds', 600);

        return now()->subSeconds(max(1, $configured));
    }

    /**
     * Dispatch a claimed operation and release its dispatch claim on failure.
     *
     * @throws Throwable
     */
    private function dispatchClaimed(InventoryStockSyncJob $job): void
    {
        $dispatchToken = $job->dispatch_token;

        try {
            ProcessInventoryStockSyncJob::dispatch($job->id, $dispatchToken)->afterCommit();
        } catch (Throwable $exception) {
            InventoryStockSyncJob::query()
                ->withOutGlobalBranchPermission()
                ->whereKey($job->id)
                ->where('dispatch_token', $dispatchToken)
                ->update([
                    'dispatched_at' => null,
                    'dispatch_token' => null,
                ]);

            throw $exception;
        }
    }

    /** {@inheritDoc} */
    public function queueRestoration(Order $order): InventoryStockSyncJob
    {
        return $this->queue($order, InventoryStockSyncOperation::Restore);
    }

    /** {@inheritDoc} */
    public function dispatchDue(int $limit = 100): int
    {
        $ids = InventoryStockSyncJob::query()
            ->withOutGlobalBranchPermission()
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereIn('status', [
                        InventoryStockSyncStatus::PendingStock,
                        InventoryStockSyncStatus::PendingAccounting,
                    ])->where(function ($query): void {
                        $query->whereNull('dispatched_at')
                            ->orWhere('dispatched_at', '<=', $this->staleDispatchCutoff());
                    });
                })->orWhere(function ($query): void {
                    $query->where('status', InventoryStockSyncStatus::ProcessingStock)
                        ->where(function ($query): void {
                            $query->whereNull('processing_started_at')
                                ->orWhere('processing_started_at', '<=', $this->staleClaimCutoff());
                        });
                });
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->pluck('id');

        $dispatched = 0;

        foreach ($ids as $id) {
            $job = $this->claimForDispatch((int) $id);
            if (! $job) {
                continue;
            }

            try {
                $this->dispatchClaimed($job);
                $dispatched++;
            } catch (Throwable $exception) {
                Log::error('Unable to dispatch a recovered inventory stock synchronization job.', [
                    'stock_sync_job_id' => $job->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $dispatched;
    }

    /**
     * Get the oldest timestamp that is still protected by the queue claim lease.
     */
    private function staleClaimCutoff(): CarbonInterface
    {
        $leaseSeconds = (int) config('inventory.stock_sync.processing_lease_seconds', 600);

        return now()->subSeconds(max(1, $leaseSeconds));
    }

    /**
     * Atomically claim one due operation for queue dispatch.
     *
     * @throws Throwable
     */
    private function claimForDispatch(int $jobId): ?InventoryStockSyncJob
    {
        return DB::transaction(function () use ($jobId): ?InventoryStockSyncJob {
            $job = InventoryStockSyncJob::query()
                ->withOutGlobalBranchPermission()
                ->lockForUpdate()
                ->find($jobId);

            if (! $job) {
                return null;
            }

            if ($job->status === InventoryStockSyncStatus::ProcessingStock
                && ($job->processing_started_at === null || $job->processing_started_at->lessThanOrEqualTo($this->staleClaimCutoff()))) {
                $job->forceFill([
                    'status' => $job->movement_ids === null
                        ? InventoryStockSyncStatus::PendingStock
                        : InventoryStockSyncStatus::PendingAccounting,
                    'processing_started_at' => null,
                    'dispatched_at' => now(),
                    'dispatch_token' => (string) Str::uuid(),
                ])->save();

                return $job;
            }

            if (! in_array($job->status, [
                InventoryStockSyncStatus::PendingStock,
                InventoryStockSyncStatus::PendingAccounting,
            ], true)) {
                return null;
            }

            if ($job->dispatched_at?->greaterThan($this->staleDispatchCutoff())) {
                return null;
            }

            $job->forceFill([
                'dispatched_at' => now(),
                'dispatch_token' => (string) Str::uuid(),
            ])->save();

            return $job;
        });
    }

    /** {@inheritDoc} */
    public function process(int|InventoryStockSyncJob $job, ?string $dispatchToken = null): InventoryStockSyncJob
    {
        $jobId = $job instanceof InventoryStockSyncJob ? $job->id : $job;

        $claimed = DB::transaction(function () use ($jobId, $dispatchToken): ?InventoryStockSyncJob {
            $job = InventoryStockSyncJob::query()
                ->withOutGlobalBranchPermission()
                ->lockForUpdate()
                ->findOrFail($jobId);

            if ($dispatchToken !== null && ! hash_equals((string) $job->dispatch_token, $dispatchToken)) {
                return null;
            }

            if ($job->status === InventoryStockSyncStatus::Completed) {
                return null;
            }

            if ($job->processing_started_at?->greaterThan($this->staleClaimCutoff())) {
                return null;
            }

            $stockIsComplete = $job->movement_ids !== null;
            $job->forceFill([
                'status' => $stockIsComplete
                    ? InventoryStockSyncStatus::PendingAccounting
                    : InventoryStockSyncStatus::ProcessingStock,
                'attempts' => $job->attempts + 1,
                'processing_started_at' => now(),
                'last_attempt_at' => now(),
                'last_error' => null,
            ])->save();

            return $job->fresh(['order']);
        });

        if (! $claimed) {
            return InventoryStockSyncJob::query()
                ->withOutGlobalBranchPermission()
                ->findOrFail($jobId);
        }

        try {
            if ($claimed->movement_ids === null) {
                $claimed = DB::transaction(function () use ($claimed): InventoryStockSyncJob {
                    $lockedJob = InventoryStockSyncJob::query()
                        ->withOutGlobalBranchPermission()
                        ->with('order')
                        ->lockForUpdate()
                        ->findOrFail($claimed->id);

                    if ($lockedJob->movement_ids !== null) {
                        return $lockedJob;
                    }

                    $result = $lockedJob->operation === InventoryStockSyncOperation::Deduct
                        ? $this->stock->deductOrderStock($lockedJob->order)
                        : $this->stock->restoreOrderStock($lockedJob->order);

                    $lockedJob->forceFill([
                        'status' => InventoryStockSyncStatus::PendingAccounting,
                        'movement_ids' => $result->movementIds(),
                        'payload' => array_merge($lockedJob->payload ?? [], $result->toPayload()),
                    ])->save();

                    return $lockedJob;
                });
            }

            if ($claimed->movement_ids === []) {
                $reason = $claimed->operation === InventoryStockSyncOperation::Deduct
                    ? 'no_stock_deductions'
                    : 'no_original_stock_deductions';

                return $this->completeWithoutAccounting($claimed, $reason);
            }

            $event = $claimed->operation === InventoryStockSyncOperation::Deduct ? 'deducted' : 'restored';
            $this->accounting->recordEvent(
                'order_stock',
                $claimed->order_id,
                $event,
                [
                    'branch_id' => $claimed->branch_id,
                    'order_number' => $claimed->order->order_number,
                    'movement_ids' => $claimed->movement_ids,
                    'ingredient_quantities' => $claimed->payload['ingredient_quantities'] ?? [],
                    'unit_costs' => $claimed->payload['unit_costs'] ?? [],
                    'currencies' => $claimed->payload['currencies'] ?? [],
                    'order_cost' => $claimed->payload['order_cost'] ?? null,
                    'gross_profit' => $claimed->payload['gross_profit'] ?? null,
                    'stock_sync_job_id' => $claimed->id,
                    'idempotency_key' => "order_stock:$claimed->order_id:$event",
                ],
            );

            $claimed->forceFill([
                'status' => InventoryStockSyncStatus::Completed,
                'completed_at' => now(),
                'dispatched_at' => null,
                'dispatch_token' => null,
                'processing_started_at' => null,
            ])->save();

            return $claimed->fresh();
        } catch (StockSyncNotRequiredException $exception) {
            return $this->completeWithoutAccounting($claimed, $exception->getMessage());
        } catch (Throwable $exception) {
            $claimed->forceFill([
                'status' => InventoryStockSyncStatus::Failed,
                'processing_started_at' => null,
                'last_error' => $exception->getMessage(),
            ])->save();

            try {
                $this->accounting->recordFailure(
                    'order_stock_sync',
                    $claimed->id,
                    $claimed->operation->value,
                    class_basename($exception),
                    $exception->getMessage(),
                    [
                        'branch_id' => $claimed->branch_id,
                        'order_id' => $claimed->order_id,
                        'stock_sync_job_id' => $claimed->id,
                        'idempotency_key' => $claimed->idempotency_key,
                    ],
                );
            } catch (Throwable $diagnosticException) {
                Log::error('Unable to record an inventory stock synchronization failure.', [
                    'stock_sync_job_id' => $claimed->id,
                    'order_id' => $claimed->order_id,
                    'error' => $exception->getMessage(),
                    'diagnostic_error' => $diagnosticException->getMessage(),
                ]);
            }

            throw $exception;
        }
    }

    /**
     * Complete an operation that no longer requires an Accounting event.
     */
    private function completeWithoutAccounting(InventoryStockSyncJob $job, string $reason): InventoryStockSyncJob
    {
        $job->forceFill([
            'status' => InventoryStockSyncStatus::Completed,
            'payload' => array_merge($job->payload ?? [], ['skip_reason' => $reason]),
            'processing_started_at' => null,
            'dispatched_at' => null,
            'dispatch_token' => null,
            'completed_at' => now(),
        ])->save();

        return $job->fresh();
    }
}
