<?php

namespace Modules\Reservation\Services\ReservationDeposit;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Reservation\Models\ReservationDepositTransaction;

interface ReservationDepositServiceInterface extends TableExportSourceInterface
{
    /**
     * Get paginated reservation deposit ledger transactions.
     */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator;

    /**
     * Find one reservation deposit ledger transaction by id.
     */
    public function show(int|string $transactionId): ReservationDepositTransaction;

    /**
     * Get metadata required to build the deposit action form.
     *
     * @return array<string, mixed>
     */
    public function getFormMeta(int|string $reservationId): array;

    /**
     * Search eligible open orders for a reservation deposit application.
     *
     * @return list<array<string, mixed>>
     */
    public function searchEligibleOrders(int|string $reservationId, ?string $search = null, int $limit = 20): array;

    /**
     * Record an append-only deposit ledger transaction for a reservation.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(int|string $reservationId, array $data): ReservationDepositTransaction;

    /**
     * Reverse an existing deposit ledger transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function reverse(int|string $transactionId, array $data): ReservationDepositTransaction;
}
