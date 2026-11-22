<?php

namespace Modules\Pos\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Pos\Services\PosViewer\PosViewerServiceInterface;
use Modules\Pos\Transformers\Api\V1\Pos\PosProductResource;
use Modules\Support\ApiResponse;

class PosViewerController extends Controller
{
    /**
     * Create a new instance of PosController
     */
    public function __construct(protected PosViewerServiceInterface $service) {}

    /**
     * Get pos configuration
     */
    public function configuration(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getConfiguration($request->input('branch_id'))
        );
    }

    /**
     * Get menu items
     */
    public function menuItems(int $menuId): JsonResponse
    {
        return ApiResponse::success($this->service->getMenuItems($menuId));
    }

    /**
     * Get menu categories.
     */
    public function categories(int $menuId): JsonResponse
    {
        return ApiResponse::success([
            'categories' => $this->service->getCategories($menuId),
        ]);
    }

    /**
     * Get paginated menu products.
     */
    public function products(Request $request, int $menuId): JsonResponse
    {
        return ApiResponse::pagination(
            $this->service->getProducts($menuId, $request->only(['search', 'category_ids'])),
            PosProductResource::class
        );
    }

    /**
     * Get paginated customers for POS lookup.
     */
    public function customers(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            $this->service->getCustomers($request->only(['branch_id', 'search']))
        );
    }
}
