<?php

namespace Modules\ActivityLog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ActivityLog\Services\ActivityLog\ActivityLogServiceInterface;
use Modules\ActivityLog\Transformers\Api\V1\ActivityLogResource;
use Modules\ActivityLog\Transformers\Api\V1\ActivityLogShowResource;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class ActivityLogController extends Controller
{
    /**
     * Create a new instance of ActivityLogController
     */
    public function __construct(protected ActivityLogServiceInterface $service) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: ActivityLogResource::class,
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
     * Show the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new ActivityLogShowResource($this->service->show($id))
        );
    }
}
