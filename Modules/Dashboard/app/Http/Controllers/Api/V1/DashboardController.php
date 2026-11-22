<?php

namespace Modules\Dashboard\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\Dashboard\Http\Requests\Api\V1\DashboardRequest;
use Modules\Dashboard\Services\Dashboard\DashboardServiceInterface;
use Modules\Support\ApiResponse;

class DashboardController extends Controller
{
    /**
     * Create a new instance of DashboardController
     */
    public function __construct(protected DashboardServiceInterface $service) {}

    /**
     * Get dashboard filter metadata.
     */
    public function filters(DashboardRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->filters($request->validated()));
    }

    /**
     * Get the requested dashboard widgets.
     */
    public function index(DashboardRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->dashboard($request->validated()));
    }
}
