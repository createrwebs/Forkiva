<?php

namespace Modules\Category\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Category\Http\Requests\Api\V1\SaveCategoryRequest;
use Modules\Category\Http\Responses\CategoryTreeResponse;
use Modules\Category\Services\Category\CategoryServiceInterface;
use Modules\Category\Services\CategoryTreeUpdater\CategoryTreeUpdaterServiceInterface;
use Modules\Category\Transformers\Api\V1\CategoryResource;
use Modules\Core\Http\Controllers\Controller;
use Modules\Menu\Services\Menu\MenuServiceInterface;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Throwable;

class CategoryController extends Controller
{
    /**
     * Create a new instance of CategoryController
     */
    public function __construct(
        protected CategoryServiceInterface $service,
        protected CategoryTreeUpdaterServiceInterface $updaterService,
        protected MenuServiceInterface $menuService) {}

    /**
     * This method retrieves and returns a list of Category models.
     */
    public function index(Request $request): JsonResponse
    {
        $menu = $this->menuService->getCurrentMenu($request->input('filters')['menu_id'] ?? null);
        $categories = null;

        if (! is_null($menu)) {
            $categories = new CategoryTreeResponse($this->service->getForTree(filters: ['menu_id' => $menu->id]));
        }

        return ApiResponse::success([
            'categories' => $categories?->transform(),
            'menu' => ! is_null($menu)
                ? [
                    'id' => $menu->id,
                    'name' => $menu->name,
                ]
                : null,
            'export_methods' => ExportType::toArrayTrans(),
            'export_api_uri' => '/v1/exports/'.$this->service->exportResourceKey(),
            'export_permission' => $this->service->exportPermission(),
            'export_resource' => $this->service->exportResourceKey(),
        ]);
    }

    /**
     * This method retrieves and returns a single Category model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new CategoryResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the Category model.
     */
    public function store(SaveCategoryRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new CategoryResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * Update category tree order.
     *
     * @throws Throwable
     */
    public function updateTree(Request $request, int $menuId): JsonResponse
    {
        $this->menuService->getCurrentMenu($menuId);
        $this->updaterService->update($menuId, $request->input('tree', []));

        return ApiResponse::success(message: __('category::messages.category_order_updated'));
    }

    /**
     * This method updates the provided data for the Category model.
     */
    public function update(SaveCategoryRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new CategoryResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the Category model based on the provided ids.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label()
        );
    }
}
