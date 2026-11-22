<?php

namespace Modules\DailyFinance\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\DailyFinance\Http\Requests\Api\V1\DayClosingRequest;
use Modules\DailyFinance\Services\DayClosing\DayClosingServiceInterface;
use Modules\DailyFinance\Transformers\Api\V1\DayClosingResource;
use Modules\DailyFinance\Transformers\Api\V1\DayClosingReviewResource;
use Modules\Support\ApiResponse;
use Throwable;

class DayClosingController extends Controller
{
    public function __construct(protected DayClosingServiceInterface $service) {}

    /**
     * Review the authorized branch day’s readiness and totals, or its saved closing snapshot.
     *
     * @throws Throwable
     */
    public function review(DayClosingRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new DayClosingReviewResource($this->service->review($request->validated()))
        );
    }

    /**
     * List the authorized branch’s closing history using the shared pagination response.
     */
    public function index(DayClosingRequest $request): JsonResponse
    {
        return ApiResponse::pagination(
            $this->service->history($request->validated()),
            DayClosingResource::class
        );
    }

    /**
     * Confirm an immutable day closing from the reviewed totals with idempotent retry handling.
     *
     * @throws Throwable
     */
    public function store(DayClosingRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new DayClosingResource($this->service->close($request->validated()))
        );
    }
}
