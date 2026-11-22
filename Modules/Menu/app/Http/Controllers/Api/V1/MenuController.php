<?php

namespace Modules\Menu\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Menu\Http\Requests\Api\V1\SaveMenuRequest;
use Modules\Menu\Services\Menu\MenuServiceInterface;
use Modules\Menu\Transformers\Api\V1\MenuResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class MenuController extends Controller
{
    /**
     * Create a new instance of MenuController
     */
    public function __construct(protected MenuServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of Menu models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: MenuResource::class,
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
     * This method retrieves and returns a single Menu model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new MenuResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the Menu model.
     */
    public function store(SaveMenuRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new MenuResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the Menu model.
     */
    public function update(SaveMenuRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new MenuResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the Menu model based on the provided ids.
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
