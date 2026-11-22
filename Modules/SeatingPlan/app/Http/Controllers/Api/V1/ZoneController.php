<?php

namespace Modules\SeatingPlan\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\SeatingPlan\Http\Requests\Api\V1\SaveZoneRequest;
use Modules\SeatingPlan\Services\Zone\ZoneServiceInterface;
use Modules\SeatingPlan\Transformers\Api\V1\ZoneResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class ZoneController extends Controller
{
    /**
     * Create a new instance of ZoneController
     */
    public function __construct(protected ZoneServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of Zone models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: ZoneResource::class,
            filters: $request->input('with_filters')
                ? $this->service->getStructureFilters(
                    auth()->user()->assignedToBranch()
                        ? auth()->user()->branch_id
                        : $request->input('filters')['branch_id'] ?? null
                )
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
     * This method retrieves and returns a single Zone model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new ZoneResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the Zone model.
     */
    public function store(SaveZoneRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new ZoneResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the Zone model.
     */
    public function update(SaveZoneRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new ZoneResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the Zone model based on the provided ids.
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
    public function getFormMeta(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getFormMeta(
                $request->integer('branch_id') ?: null
            )
        );
    }
}
