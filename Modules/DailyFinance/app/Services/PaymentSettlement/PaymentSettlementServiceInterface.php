<?php

namespace Modules\DailyFinance\Services\PaymentSettlement;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\DailyFinance\Models\PaymentSettlement;
use Modules\Payment\Models\Payment;
use Throwable;

interface PaymentSettlementServiceInterface
{
    /**
     * Read branch currency, electronic methods and permitted existing bank-account choices.
     */
    public function meta(int $branchId): array;

    /**
     * Read current open balances across all collection dates without mixing historical currencies.
     *
     * @return array{branch_id: int, open_count: int, pending_count: int, partial_count: int, balances: list<array{currency: string, open_count: int, remaining: string}>}
     */
    public function summary(int $branchId): array;

    /**
     * List unreserved source collections, bounded by branch, method and collection dates.
     *
     * @param  array{branch_id: int, method: string, from: string, to: string, search?: string|null}  $filters
     * @return LengthAwarePaginator<Payment>
     */
    public function candidates(array $filters): LengthAwarePaginator;

    /**
     * Read paginated settlement history for the authorized branch.
     *
     * @param  array{branch_id: int, status?: string|null, method?: string|null}  $filters
     * @return LengthAwarePaginator<PaymentSettlement>
     */
    public function history(array $filters): LengthAwarePaginator;

    /**
     * Read source snapshots and immutable bank-credit receipts within the authorized branch.
     */
    public function show(int $branchId, int $id): PaymentSettlement;

    /**
     * Exclusively reserve selected collections without changing their original payment records.
     * Identical request-key retries return the first accepted batch.
     *
     * @param  array{branch_id: int, method: string, payment_ids: array<int, int>, source_hashes: array<int, string>, fees: string, reference: string, account_key?: string|null, destination_name?: string|null, notes?: string|null, request_key: string}  $data
     *
     * @throws Throwable
     */
    public function create(array $data): PaymentSettlement;

    /**
     * Append an actual bank credit; final differences require an explanation.
     *
     * @param  array{amount: string, received_date: string, reference: string, is_final: bool, variance_reason?: string|null, request_key: string}  $data
     *
     * @throws Throwable
     */
    public function receive(int $branchId, int $id, array $data): PaymentSettlement;

    /**
     * Cancel only an untouched pending batch, preserving evidence and releasing source reservations.
     *
     * @throws Throwable
     */
    public function cancel(int $branchId, int $id, string $reason): PaymentSettlement;
}
