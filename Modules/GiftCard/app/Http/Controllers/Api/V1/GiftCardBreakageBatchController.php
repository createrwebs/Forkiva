<?php

namespace Modules\GiftCard\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\GiftCard\Http\Requests\Api\V1\ReverseGiftCardTransactionRequest;
use Modules\GiftCard\Http\Requests\Api\V1\ReviewGiftCardBreakageBatchRequest;
use Modules\GiftCard\Http\Requests\Api\V1\StoreGiftCardBreakageBatchRequest;
use Modules\GiftCard\Services\GiftCardBreakage\GiftCardBreakageServiceInterface;
use Modules\GiftCard\Transformers\Api\V1\GiftCardBreakageBatchResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class GiftCardBreakageBatchController extends Controller
{
    public function __construct(protected GiftCardBreakageServiceInterface $service) {}

    /**
     * List breakage batches with export metadata.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: GiftCardBreakageBatchResource::class,
            extra: [
                'export_methods' => ExportType::toArrayTrans(),
                'export_api_uri' => '/v1/exports/'.$this->service->exportResourceKey(),
                'export_permission' => $this->service->exportPermission(),
                'export_resource' => $this->service->exportResourceKey(),
            ],
        );
    }

    /**
     * Show a breakage batch and its selected card balances.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new GiftCardBreakageBatchResource($this->service->show($id)),
        );
    }

    /**
     * Return the branch and eligible expired-card options used to create a batch.
     */
    public function getFormMeta(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getFormMeta($request->integer('branch_id') ?: null));
    }

    /**
     * Create a draft breakage batch from selected expired gift cards.
     */
    public function store(StoreGiftCardBreakageBatchRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new GiftCardBreakageBatchResource($this->service->store($request->validated())),
        );
    }

    /**
     * Post a reviewed batch and publish its accounting event.
     */
    public function post(int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new GiftCardBreakageBatchResource($this->service->post($id)),
        );
    }

    /**
     * Freeze the selected card balances for a draft batch review.
     */
    public function review(ReviewGiftCardBreakageBatchRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new GiftCardBreakageBatchResource($this->service->review($id, $request->validated())),
        );
    }

    /**
     * Append a reversal for a posted breakage batch.
     */
    public function reverse(ReverseGiftCardTransactionRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new GiftCardBreakageBatchResource($this->service->reverse($id, $request->validated())),
        );
    }
}
