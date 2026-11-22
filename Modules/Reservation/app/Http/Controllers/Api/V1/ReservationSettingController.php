<?php

namespace Modules\Reservation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Reservation\Http\Requests\Api\V1\SaveReservationSettingRequest;
use Modules\Reservation\Services\ReservationSetting\ReservationSettingServiceInterface;
use Modules\Reservation\Transformers\Api\V1\ReservationSettingResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class ReservationSettingController extends Controller
{
    /**
     * Create a new ReservationSettingController instance.
     */
    public function __construct(protected ReservationSettingServiceInterface $service) {}

    /**
     * List reservation settings.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get($request->input('filters', []), $request->input('sorts', [])),
            resource: ReservationSettingResource::class,
            filters: $request->boolean('with_filters') ? $this->service->getStructureFilters() : null,
            extra: [
                'export_methods' => ExportType::toArrayTrans(),
                'export_api_uri' => '/v1/exports/reservation_settings',
                'export_permission' => 'admin.reservation_settings.export',
                'export_resource' => 'reservation_settings',
            ]
        );
    }

    /**
     * Get form metadata.
     */
    public function getFormMeta(): JsonResponse
    {
        return ApiResponse::success($this->service->getFormMeta());
    }

    /**
     * Show one setting.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new ReservationSettingResource($this->service->show($id)));
    }

    /**
     * Store or upsert branch reservation settings.
     */
    public function store(SaveReservationSettingRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new ReservationSettingResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * Update branch reservation settings.
     */
    public function update(SaveReservationSettingRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new ReservationSettingResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * Delete settings.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label()
        );
    }
}
