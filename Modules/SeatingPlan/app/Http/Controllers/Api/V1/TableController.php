<?php

namespace Modules\SeatingPlan\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\SeatingPlan\Http\Requests\Api\V1\SaveTableRequest;
use Modules\SeatingPlan\Services\Table\TableServiceInterface;
use Modules\SeatingPlan\Transformers\Api\V1\TableResource;
use Modules\SeatingPlan\Transformers\Api\V1\TableStatusLogResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class TableController extends Controller
{
    /**
     * Create a new instance of TableController
     */
    public function __construct(protected TableServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of Table models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: TableResource::class,
            filters: $request->input('with_filters')
                ? $this->service->getStructureFilters(
                    branchId: auth()->user()->assignedToBranch()
                        ? auth()->user()->branch_id
                        : $request->input('filters')['branch_id'] ?? null,
                    floorId: $request->input('filters')['floor_id'] ?? null,
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
     * Get table status logs
     */
    public function getStatusLogs(int $id): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->getStatusLogs($id),
            resource: TableStatusLogResource::class,
        );
    }

    /**
     * This method retrieves and returns a single Table model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new TableResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the Table model.
     */
    public function store(SaveTableRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new TableResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the Table model.
     */
    public function update(SaveTableRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new TableResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the Table model based on the provided ids.
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
                branchId: $request->input('branch_id'),
                floorId: $request->input('floor_id'),
            )
        );
    }

    /**
     * Regenerate table QR token.
     */
    public function regenerateQrToken(int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new TableResource($this->service->regenerateQrToken($id)),
            resource: $this->service->label(),
            message: __('seatingplan::messages.table_qr_token_regenerated')
        );
    }
}
