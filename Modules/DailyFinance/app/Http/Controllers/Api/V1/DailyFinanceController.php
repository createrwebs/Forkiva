<?php

namespace Modules\DailyFinance\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\DailyFinance\Http\Requests\Api\V1\DailyFinanceEntriesRequest;
use Modules\DailyFinance\Http\Requests\Api\V1\DailyFinanceRequest;
use Modules\DailyFinance\Services\DailyFinance\DailyFinanceServiceInterface;
use Modules\DailyFinance\Transformers\Api\V1\DailyFinanceEntryResource;
use Modules\DailyFinance\Transformers\Api\V1\DailyFinanceResource;
use Modules\Support\ApiResponse;

class DailyFinanceController extends Controller
{
    public function __construct(protected DailyFinanceServiceInterface $service) {}

    /**
     * Present daily activity without creating closing or accounting records.
     */
    public function index(DailyFinanceRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new DailyFinanceResource(
                $this->service->summary($request->validated())
            )
        );
    }

    /**
     * Return paginated records without leaving the daily finance workspace.
     */
    public function entries(DailyFinanceEntriesRequest $request): JsonResponse
    {
        return ApiResponse::pagination(
            $this->service->entries($request->validated()),
            DailyFinanceEntryResource::class,
        );
    }
}
