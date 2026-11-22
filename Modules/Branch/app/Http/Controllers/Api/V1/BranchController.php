<?php

namespace Modules\Branch\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Branch\Http\Requests\Api\V1\SaveBranchRequest;
use Modules\Branch\Services\Branch\BranchServiceInterface;
use Modules\Branch\Transformers\Api\V1\BranchResource;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class BranchController extends Controller
{
    /**
     * Create a new instance of BranchController
     */
    public function __construct(protected BranchServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of Branch models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: BranchResource::class,
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
     * Get form meta
     */
    public function getFormMeta(): JsonResponse
    {
        return ApiResponse::success($this->service->getFormMeta());
    }

    /**
     * This method retrieves and returns a single Branch model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new BranchResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the Branch model.
     */
    public function store(SaveBranchRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new BranchResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the Branch model.
     */
    public function update(SaveBranchRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new BranchResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the Branch model based on the provided ids.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label(),
            message: __('branch::messages.branches_cannot_be_destroyed')
        );
    }
}
