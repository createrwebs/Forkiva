<?php

namespace Modules\Reservation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Reservation\Http\Requests\Api\V1\CancelReservationWaitlistRequest;
use Modules\Reservation\Http\Requests\Api\V1\ConvertReservationWaitlistRequest;
use Modules\Reservation\Http\Requests\Api\V1\SaveReservationWaitlistRequest;
use Modules\Reservation\Services\ReservationWaitlist\ReservationWaitlistServiceInterface;
use Modules\Reservation\Transformers\Api\V1\ReservationResource;
use Modules\Reservation\Transformers\Api\V1\ReservationWaitlistResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Throwable;

class ReservationWaitlistController extends Controller
{
    /**
     * Create a new ReservationWaitlistController instance.
     */
    public function __construct(protected ReservationWaitlistServiceInterface $service) {}

    /**
     * List waitlist records.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get($request->input('filters', []), $request->input('sorts', [])),
            resource: ReservationWaitlistResource::class,
            filters: $request->boolean('with_filters') ? $this->service->getStructureFilters() : null,
            extra: [
                'export_methods' => ExportType::toArrayTrans(),
                'export_api_uri' => '/v1/exports/reservation_waitlists',
                'export_permission' => 'admin.reservation_waitlists.export',
                'export_resource' => 'reservation_waitlists',
            ]
        );
    }

    /**
     * Get waitlist form metadata.
     */
    public function getFormMeta(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getFormMeta($request->integer('branch_id') ?: null));
    }

    /**
     * Show a waitlist record.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new ReservationWaitlistResource($this->service->show($id)));
    }

    /**
     * Store a waitlist record.
     */
    public function store(SaveReservationWaitlistRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new ReservationWaitlistResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * Update a waitlist record.
     */
    public function update(SaveReservationWaitlistRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new ReservationWaitlistResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * Delete waitlist records.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label()
        );
    }

    /**
     * Notify a waitlist customer through system notifications.
     */
    public function notify(int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new ReservationWaitlistResource($this->service->notify($id)),
            resource: $this->service->label()
        );
    }

    /**
     * Convert a waitlist record into a reservation.
     *
     * @throws Throwable
     */
    public function convert(ConvertReservationWaitlistRequest $request, int $id): JsonResponse
    {
        return ApiResponse::created(
            body: new ReservationResource($this->service->convert($id, $request->validated())),
            resource: __('reservation::reservations.reservation')
        );
    }

    /**
     * Cancel a waitlist record.
     */
    public function cancel(CancelReservationWaitlistRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new ReservationWaitlistResource($this->service->cancel($id, $request->validated())),
            resource: $this->service->label()
        );
    }
}
