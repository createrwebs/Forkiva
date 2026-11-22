<?php

namespace Modules\SeatingPlan\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\SeatingPlan\Services\TableMerge\TableMergeServiceInterface;
use Modules\SeatingPlan\Transformers\Api\V1\TableMergeResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class TableMergeController extends Controller
{
    /**
     * Create a new instance of TableMergeController
     */
    public function __construct(protected TableMergeServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of TableMerge models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: TableMergeResource::class,
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
     * This method retrieves and returns a single TableMerge model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new TableMergeResource($this->service->show($id))
        );
    }
}
