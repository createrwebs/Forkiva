<?php

namespace Modules\Reservation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Enums\TableAssignmentStatus;
use Modules\Reservation\Http\Requests\Api\V1\AssignReservationTablesRequest;
use Modules\Reservation\Http\Requests\Api\V1\CheckReservationAvailabilityRequest;
use Modules\Reservation\Http\Requests\Api\V1\ReservationStatusActionRequest;
use Modules\Reservation\Http\Requests\Api\V1\SaveReservationRequest;
use Modules\Reservation\Services\Reservation\ReservationServiceInterface;
use Modules\Reservation\Services\ReservationAvailability\ReservationAvailabilityServiceInterface;
use Modules\Reservation\Services\ReservationStatus\ReservationStatusServiceInterface;
use Modules\Reservation\Services\ReservationTableAssignment\ReservationTableAssignmentServiceInterface;
use Modules\Reservation\Transformers\Api\V1\ReservationResource;
use Modules\Reservation\Transformers\Api\V1\ShowReservationResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Throwable;

class ReservationController extends Controller
{
    /**
     * Create a new ReservationController instance.
     */
    public function __construct(
        protected ReservationServiceInterface $service,
        protected ReservationAvailabilityServiceInterface $availability,
        protected ReservationTableAssignmentServiceInterface $assignment,
        protected ReservationStatusServiceInterface $statuses,
    ) {}

    /**
     * List reservations.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: ReservationResource::class,
            filters: $request->boolean('with_filters') ? $this->service->getStructureFilters() : null,
            extra: [
                'export_methods' => ExportType::toArrayTrans(),
                'export_api_uri' => '/v1/exports/'.$this->service->exportResourceKey(),
                'export_permission' => $this->service->exportPermission(),
                'export_resource' => $this->service->exportResourceKey(),
            ]
        );
    }

    /**
     * Get reservation form metadata.
     */
    public function getFormMeta(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getFormMeta(
                branchId: $request->integer('branch_id') ?: null,
                floorId: $request->integer('floor_id') ?: null,
                zoneId: $request->integer('zone_id') ?: null,
            )
        );
    }

    /**
     * Show one reservation.
     */
    public function show(int|string $id): JsonResponse
    {
        return ApiResponse::success(new ShowReservationResource(
            $this->service->show($id)->loadMissing([
                'reservationTables.table',
                'reservationTables.assignedBy:id,name',
            ])
        ));
    }

    /**
     * Store a reservation.
     *
     * @throws Throwable
     */
    public function store(SaveReservationRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (($data['status'] ?? null) === ReservationStatus::Confirmed->value) {
            abort_unless($request->user()?->can('admin.reservations.confirm'), 403);
        }

        return ApiResponse::created(
            body: new ShowReservationResource($this->service->store($data)),
            resource: $this->service->label()
        );
    }

    /**
     * Update a reservation.
     *
     * @throws Throwable
     */
    public function update(SaveReservationRequest $request, int|string $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new ShowReservationResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * Delete reservations.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label()
        );
    }

    /**
     * Check availability for an admin reservation flow.
     */
    public function availability(CheckReservationAvailabilityRequest $request): JsonResponse
    {
        return ApiResponse::success($this->availability->check($request->validated()));
    }

    /**
     * Get suggested tables.
     */
    public function suggestedTables(CheckReservationAvailabilityRequest $request): JsonResponse
    {
        return ApiResponse::success($this->availability->suggestedTables($request->validated()));
    }

    /**
     * Get suggested slots.
     */
    public function suggestedSlots(CheckReservationAvailabilityRequest $request): JsonResponse
    {
        return ApiResponse::success($this->availability->suggestedSlots($request->validated()));
    }

    /**
     * Get calendar reservations.
     */
    public function calendar(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->calendar($request->input('filters', [])));
    }

    /**
     * List unassigned reservations.
     */
    public function unassigned(Request $request): JsonResponse
    {
        $filters = [
            ...$request->input('filters', []),
            'table_assignment_status' => TableAssignmentStatus::Unassigned->value,
        ];

        return ApiResponse::pagination(
            paginator: $this->service->get($filters, $request->input('sorts', [])),
            resource: ReservationResource::class,
        );
    }

    /**
     * Change reservation status.
     *
     * @throws Throwable
     */
    public function changeStatus(ReservationStatusActionRequest $request, int|string $id): JsonResponse
    {
        $data = $request->validated();
        $status = ReservationStatus::from($data['status']);
        $permission = $status->transitionPermission();
        abort_unless($permission && $request->user()?->can($permission), 403);

        return ApiResponse::updated(
            body: new ShowReservationResource($this->statuses->transition(
                reservation: $id,
                status: $status,
                data: $data,
            )),
            resource: $this->service->label()
        );
    }

    /**
     * Assign tables manually.
     */
    public function assignTables(AssignReservationTablesRequest $request, int|string $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new ShowReservationResource($this->assignment->assign($id, $request->validated('table_ids'))),
            resource: $this->service->label()
        );
    }

    /**
     * Auto assign tables.
     */
    public function autoAssignTables(int|string $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new ShowReservationResource($this->assignment->autoAssign($id)),
            resource: $this->service->label()
        );
    }

    /**
     * Remove one assigned table.
     */
    public function removeTable(int|string $id, int $tableId): JsonResponse
    {
        return ApiResponse::updated(
            body: new ShowReservationResource($this->assignment->remove($id, $tableId)),
            resource: $this->service->label()
        );
    }
}
