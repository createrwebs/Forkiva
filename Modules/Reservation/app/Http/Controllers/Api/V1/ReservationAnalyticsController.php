<?php

namespace Modules\Reservation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\Reservation\Http\Requests\Api\V1\ReservationAnalyticsRequest;
use Modules\Reservation\Services\ReservationAnalytics\ReservationAnalyticsServiceInterface;
use Modules\Support\ApiResponse;

class ReservationAnalyticsController extends Controller
{
    /**
     * Create a new ReservationAnalyticsController instance.
     */
    public function __construct(protected ReservationAnalyticsServiceInterface $analytics) {}

    /**
     * Get reservation analytics filter schema and defaults.
     */
    public function filters(ReservationAnalyticsRequest $request): JsonResponse
    {
        return ApiResponse::success($this->analytics->filters($request->validated()));
    }

    /**
     * Get reservation analytics overview.
     */
    public function index(ReservationAnalyticsRequest $request): JsonResponse
    {
        return ApiResponse::success($this->analytics->analytics($request->validated()));
    }
}
