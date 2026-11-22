<?php

namespace Modules\GiftCard\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\GiftCard\Http\Requests\Api\V1\GiftCardAnalyticsRequest;
use Modules\GiftCard\Services\GiftCardAnalytics\GiftCardAnalyticsServiceInterface;
use Modules\Support\ApiResponse;

class GiftCardAnalyticsController extends Controller
{
    /**
     * Create a new instance of GiftCardAnalyticsController.
     */
    public function __construct(protected GiftCardAnalyticsServiceInterface $service) {}

    /**
     * Return gift card analytics filter schema and defaults.
     */
    public function filters(GiftCardAnalyticsRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->filters($request->validated()));
    }

    /**
     * Return gift card analytics payload or one requested section.
     */
    public function index(GiftCardAnalyticsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $section = (string) ($validated['section'] ?? '');

        if (! empty($section)) {
            return ApiResponse::success($this->service->section($section, $validated));
        }

        return ApiResponse::success($this->service->analytics($validated));
    }
}
