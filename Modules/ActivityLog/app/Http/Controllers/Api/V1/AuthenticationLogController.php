<?php

namespace Modules\ActivityLog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ActivityLog\Services\AuthenticationLog\AuthenticationLogServiceInterface;
use Modules\ActivityLog\Transformers\Api\V1\AuthenticationLogResource;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class AuthenticationLogController extends Controller
{
    /**
     * Create a new instance of AuthenticationLogController
     */
    public function __construct(protected AuthenticationLogServiceInterface $service) {}

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
            resource: AuthenticationLogResource::class,
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
        return ApiResponse::success(new AuthenticationLogResource($this->service->show($id)));
    }
}
