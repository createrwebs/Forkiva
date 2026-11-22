<?php

namespace Modules\Reservation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Reservation\Http\Requests\Api\V1\SaveReservationTableBlockRequest;
use Modules\Reservation\Services\ReservationTableBlock\ReservationTableBlockServiceInterface;
use Modules\Reservation\Transformers\Api\V1\ReservationTableBlockResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class ReservationTableBlockController extends Controller
{
    /**
     * Create a new ReservationTableBlockController instance.
     */
    public function __construct(protected ReservationTableBlockServiceInterface $service) {}

    /**
     * List table blocks.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get($request->input('filters', []), $request->input('sorts', [])),
            resource: ReservationTableBlockResource::class,
            filters: $request->boolean('with_filters') ? $this->service->getStructureFilters() : null,
            extra: [
                'export_methods' => ExportType::toArrayTrans(),
                'export_api_uri' => '/v1/exports/reservation_table_blocks',
                'export_permission' => 'admin.reservation_table_blocks.export',
                'export_resource' => 'reservation_table_blocks',
            ]
        );
    }

    /**
     * Show one table block.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new ReservationTableBlockResource($this->service->show($id)));
    }

    /**
     * Store a table block.
     */
    public function store(SaveReservationTableBlockRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new ReservationTableBlockResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * Update a table block.
     */
    public function update(SaveReservationTableBlockRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new ReservationTableBlockResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * Delete table blocks.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label()
        );
    }
}
