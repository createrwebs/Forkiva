<?php

namespace Modules\User\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Modules\User\Http\Requests\Api\V1\SaveRoleRequest;
use Modules\User\Services\Role\RoleServiceInterface;
use Modules\User\Transformers\Api\V1\RoleResource;
use Modules\User\Transformers\Api\V1\RoleShowResource;

class RoleController extends Controller
{
    /**
     * Create a new instance of RoleController
     */
    public function __construct(protected RoleServiceInterface $service) {}

    /**
     * Display a listing of the roles.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: RoleResource::class,
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
            body: new RoleShowResource($this->service->show($id))
        );
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(SaveRoleRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new RoleShowResource(
                $this->service->store($request->validated())
            ),
            resource: $this->service->label()
        );
    }

    /**
     * Update the specified role in storage.
     */
    public function update(SaveRoleRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new RoleShowResource(
                $this->service->update($id, $request->validated())
            ),
            resource: $this->service->label()
        );
    }

    /**
     * Destroy resources by given ids.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label(),
            message: __('user::messages.roles_cannot_be_destroyed')
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
