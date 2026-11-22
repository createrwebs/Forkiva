<?php

namespace Modules\Pos\Services\PosCashMovement;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Pos\Enums\PosCashDirection;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Models\PosSession;
use Throwable;

interface PosCashMovementServiceInterface extends TableExportSourceInterface
{
    /**
     * Display a listing of the resource.
     */
    public function get(array $filters = [], array $sorts = []): LengthAwarePaginator;

    /**
     * Show the specified resource.
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): PosCashMovement;

    /**
     * Label for the resource.
     */
    public function label(): string;

    /**
     * Model for the resource.
     */
    public function model(): string;

    /**
     * Get a new instance of the model.
     */
    public function getModel(): PosCashMovement;

    /**
     * Cash sale -> IN
     *
     * @throws Throwable
     */
    public function sale(
        PosSession $session,
        float $amount,
        ?int $orderId = null,
        ?int $paymentId = null,
        ?string $reference = null,
        ?string $notes = null,
    ): PosCashMovement;

    /**
     * Cash refund -> OUT
     *
     * @throws Throwable
     */
    public function refundCash(
        PosSession $session,
        float $amount,
        ?int $orderId = null,
        ?int $paymentId = null,
        ?string $reference = null,
        ?string $notes = null,
    ): PosCashMovement;

    /**
     * Pay-in (top-up) -> IN
     *
     * @throws Throwable
     */
    public function payIn(
        PosSession $session,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,

    ): PosCashMovement;

    /**
     * Pay-out (supplier/petty cash) -> OUT
     *
     * @throws Throwable
     */
    public function payOut(
        PosSession $session,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,

    ): PosCashMovement;

    /**
     * Cash drop to safe -> OUT
     *
     * @throws Throwable
     */
    public function cashDrop(
        PosSession $session,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,

    ): PosCashMovement;

    /**
     * Closing adjust -> ADJUST (no physical in/out)
     *
     * @throws Throwable
     */
    public function closingAdjust(
        PosSession $session,
        float|string $amount,
        ?string $reference = null,
        ?string $notes = null,

    ): PosCashMovement;

    /**
     * Tip in -> IN
     *
     * @throws Throwable
     */
    public function tipIn(
        PosSession $session,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,

    ): PosCashMovement;

    /**
     * Tip out -> OUT
     *
     * @throws Throwable
     */
    public function tipOut(
        PosSession $session,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): PosCashMovement;

    /**
     * Reverse Tip out -> OUT
     *
     * @throws Throwable
     */
    public function reverseTipOut(
        PosSession $session,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): PosCashMovement;

    /**
     * Manual correction (physical add/remove OR neutral adjust)
     *
     * @throws Throwable
     */
    public function correction(
        PosSession $session,
        float $amount,
        PosCashDirection $direction,
        ?string $reference = null,
        ?string $notes = null,
    ): PosCashMovement;

    /**
     * Get structure filters for frontend
     */
    public function getStructureFilters(?int $branchId = null, ?int $sessionId = null): array;

    /**
     * Store a newly created resource in storage.
     *
     * @throws Throwable
     */
    public function store(array $data): PosCashMovement;

    /**
     * Get pos active session
     */
    public function getPosActiveSession(int $posRegisterId, ?string $action = null): ?PosSession;

    /**
     * Shared table query used by pagination and export.
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;
}
