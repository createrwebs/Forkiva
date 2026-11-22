<?php

namespace Modules\Pos\Services\OfflineSync;

use Illuminate\Database\QueryException;
use Modules\Branch\Models\Branch;
use Modules\Pos\Models\PosOfflineSyncReceipt;

class OfflineOrderSyncService
{
    /**
     * Process an offline POS batch idempotently using (branch_id, client_uuid).
     * Never rejects completed offline cash/PromptPay sales due to stock shortage;
     * instead flags needs_stock_reconciliation so cash totals remain intact.
     *
     * @param  list<array<string, mixed>>  $orders
     * @return list<array{client_uuid: string, status: string, receipt_id: int, needs_stock_reconciliation: bool}>
     */
    public function syncBatch(Branch $branch, array $orders): array
    {
        $results = [];

        foreach ($orders as $orderData) {
            $clientUuid = (string) $orderData['client_uuid'];

            $existing = PosOfflineSyncReceipt::query()
                ->where('branch_id', $branch->id)
                ->where('client_uuid', $clientUuid)
                ->first();

            if ($existing !== null) {
                $results[] = [
                    'client_uuid' => $clientUuid,
                    'status' => 'duplicate',
                    'receipt_id' => $existing->id,
                    'needs_stock_reconciliation' => (bool) $existing->needs_stock_reconciliation,
                ];

                continue;
            }

            try {
                $receipt = PosOfflineSyncReceipt::query()->create([
                    'tenant_id' => $branch->tenant_id,
                    'branch_id' => $branch->id,
                    'client_uuid' => $clientUuid,
                    'offline_reference' => (string) $orderData['offline_reference'],
                    'payment_method' => (string) $orderData['payment_method'],
                    'total' => (string) $orderData['total'],
                    'needs_stock_reconciliation' => (bool) ($orderData['stock_shortage'] ?? false),
                    'payload' => $orderData,
                    'synced_at' => now(),
                ]);

                $results[] = [
                    'client_uuid' => $clientUuid,
                    'status' => 'synced',
                    'receipt_id' => $receipt->id,
                    'needs_stock_reconciliation' => (bool) $receipt->needs_stock_reconciliation,
                ];
            } catch (QueryException) {
                $concurrent = PosOfflineSyncReceipt::query()
                    ->where('branch_id', $branch->id)
                    ->where('client_uuid', $clientUuid)
                    ->firstOrFail();

                $results[] = [
                    'client_uuid' => $clientUuid,
                    'status' => 'duplicate',
                    'receipt_id' => $concurrent->id,
                    'needs_stock_reconciliation' => (bool) $concurrent->needs_stock_reconciliation,
                ];
            }
        }

        return $results;
    }
}
