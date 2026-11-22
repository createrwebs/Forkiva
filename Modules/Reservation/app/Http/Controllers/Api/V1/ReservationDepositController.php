<?php

namespace Modules\Reservation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Reservation\Http\Requests\Api\V1\RecordReservationDepositRequest;
use Modules\Reservation\Http\Requests\Api\V1\ReverseReservationDepositRequest;
use Modules\Reservation\Services\ReservationDeposit\ReservationDepositServiceInterface;
use Modules\Reservation\Transformers\Api\V1\ReservationDepositTransactionResource;
use Modules\Support\ApiResponse;

class ReservationDepositController extends Controller
{
    /**
     * Create the controller with the deposit ledger service.
     */
    public function __construct(protected ReservationDepositServiceInterface $service) {}

    /**
     * List reservation deposit ledger transactions.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: ReservationDepositTransactionResource::class,
            extra: [
                'export_api_uri' => '/v1/exports/'.$this->service->exportResourceKey(),
                'export_permission' => $this->service->exportPermission(),
                'export_resource' => $this->service->exportResourceKey(),
            ],
        );
    }

    /**
     * Show one reservation deposit ledger transaction.
     */
    public function show(int|string $transactionId): JsonResponse
    {
        return ApiResponse::success(new ReservationDepositTransactionResource(
            $this->service->show($transactionId)
        ));
    }

    /**
     * Get metadata needed to record a deposit action for a reservation.
     */
    public function getFormMeta(int|string $reservationId): JsonResponse
    {
        return ApiResponse::success($this->service->getFormMeta($reservationId));
    }

    /**
     * Search eligible orders without loading the complete order set.
     */
    public function searchEligibleOrders(Request $request, int|string $reservationId): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success($this->service->searchEligibleOrders(
            $reservationId,
            $validated['search'] ?? null,
        ));
    }

    /**
     * Record a deposit collection, application, refund, or forfeiture.
     */
    public function store(RecordReservationDepositRequest $request, int|string $reservationId): JsonResponse
    {
        return ApiResponse::created(body: new ReservationDepositTransactionResource(
            $this->service->show($this->service->record($reservationId, $request->validated())->id)
        ));
    }

    /**
     * Reverse an existing deposit ledger transaction.
     */
    public function reverse(ReverseReservationDepositRequest $request, int|string $transactionId): JsonResponse
    {
        return ApiResponse::updated(body: new ReservationDepositTransactionResource(
            $this->service->show($this->service->reverse($transactionId, $request->validated())->id)
        ));
    }
}
