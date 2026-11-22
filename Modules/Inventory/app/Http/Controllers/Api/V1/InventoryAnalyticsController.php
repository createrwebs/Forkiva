<?php

namespace Modules\Inventory\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\Api\V1\InventoryAnalyticsRequest;
use Modules\Inventory\Services\InventoryAnalytics\InventoryAnalyticsServiceInterface;
use Modules\Support\ApiResponse;

class InventoryAnalyticsController extends Controller
{
    /**
     * Create a new instance of InventoryAnalyticsController
     */
    public function __construct(protected InventoryAnalyticsServiceInterface $service) {}

    /**
     * Get the requested inventory analytics widgets.
     */
    public function index(InventoryAnalyticsRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->analytics($request->validated()));
    }

    /**
     * Get inventory analytics filter metadata and defaults.
     */
    public function filters(InventoryAnalyticsRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->filters($request->validated()));
    }
}
