<?php

namespace Modules\SeatingPlan\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\SeatingPlan\Http\Requests\Api\V1\SaveFloorRequest;
use Modules\SeatingPlan\Services\Floor\FloorServiceInterface;
use Modules\SeatingPlan\Transformers\Api\V1\FloorResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class FloorController extends Controller
{
    /**
     * Create a new instance of FloorController
     */
    public function __construct(protected FloorServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of Floor models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: FloorResource::class,
            filters: $request->input('with_filters')
                ? $this->service->getStructureFilters()
                : null,
            extra: [
                'export_methods' => ExportType::toArrayTrans(),
                'export_api_uri' => '/v1/exports/'.$this->service->exportResourceKey(),
                'export_permission' => $this->service->exportPermission(),
                'export_resource' => $this->service->exportResourceKey(),
            ]
        );
    }

    /**
     * This method retrieves and returns a single Floor model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new FloorResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the Floor model.
     */
    public function store(SaveFloorRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new FloorResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the Floor model.
     */
    public function update(SaveFloorRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new FloorResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the Floor model based on the provided ids.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label()
        );
    }

    /**
     * Get form meta
     */
    public function getFormMeta(): JsonResponse
    {
        return ApiResponse::success($this->service->getFormMeta());
    }
}
