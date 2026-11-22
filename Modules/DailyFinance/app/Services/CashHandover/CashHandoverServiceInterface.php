<?php

namespace Modules\DailyFinance\Services\CashHandover;

use Modules\DailyFinance\Models\CashHandover;

/**
 * Cash custody operations for authenticated callers authorized by route middleware.
 */
interface CashHandoverServiceInterface
{
    /**
     * Read authorized session custody, exact available cash and paginated history.
     */
    public function summary(int $sessionId, int $page = 1): array;

    /**
     * Read all-date branch cash still in cashier custody and handovers awaiting receipt, by original currency.
     *
     * @return array{branch_id: int, unhanded_sessions_count: int, pending_sessions_count: int, pending_handovers_count: int, balances: list<array{currency: string, unhanded: string, pending: string}>}
     */
    public function followUp(int $branchId): array;

    /**
     * List closed sessions matching an all-date custody follow-up, with exact balances and bounded pagination.
     *
     * @param  'unhanded'|'pending'  $kind
     * @return array{data: list<array>, pagination: array{current_page: int, last_page: int, total: int}}
     */
    public function followUpSessions(int $branchId, string $kind, int $page = 1): array;

    /**
     * Reserve counted cash for the designated receiver without changing the closed POS snapshot.
     * Reusing a request key with identical actor and inputs returns the same handover.
     *
     * @param  array{request_key: string, amount: string, receiver_id: int, notes?: string|null}  $data
     */
    public function create(int $sessionId, array $data): CashHandover;

    /**
     * Confirm custody only as the designated receiver; shortages require an explanation.
     * The full handed-over amount remains consumed even when less cash is received.
     * Surpluses consume additional unreserved counted cash; no receipt may exceed that capacity.
     *
     * @param  array{received_amount: string, variance_reason?: string|null}  $data
     */
    public function receive(int $sessionId, int $id, array $data): CashHandover;

    /**
     * Cancel a pending handover as its creator, preserving evidence and releasing its reservation.
     *
     * @param  array{cancellation_reason: string}  $data
     */
    public function cancel(int $sessionId, int $id, array $data): CashHandover;
}
