<?php

namespace Modules\GiftCard\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\GiftCard\Http\Requests\Api\V1\StoreGiftCardBatchRequest;
use Modules\GiftCard\Services\GiftCardBatch\GiftCardBatchServiceInterface;
use Modules\GiftCard\Transformers\Api\V1\GiftCardBatchResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Throwable;

class GiftCardBatchController extends Controller
{
    /**
     * Create a new instance of GiftCardBatchController.
     */
    public function __construct(protected GiftCardBatchServiceInterface $service) {}

    /**
     * Display a listing of gift card batches.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: GiftCardBatchResource::class,
            filters: $request->input('with_filters') ? $this->service->getStructureFilters() : null,
            extra: [
                'export_methods' => ExportType::toArrayTrans(),
                'export_api_uri' => '/v1/exports/'.$this->service->exportResourceKey(),
                'export_permission' => $this->service->exportPermission(),
                'export_resource' => $this->service->exportResourceKey(),
            ],
        );
    }

    /**
     * Display the specified gift card batch.
     */
    public function show(int|string $id): JsonResponse
    {
        return ApiResponse::success(body: new GiftCardBatchResource($this->service->show($id)));
    }

    /**
     * Store a newly created gift card batch in storage.
     *
     * @throws Throwable
     */
    public function store(StoreGiftCardBatchRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new GiftCardBatchResource($this->service->store($request->validated())),
            resource: $this->service->label(),
        );
    }

    /**
     * Remove the specified gift card batches from storage.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label(),
        );
    }

    /**
     * Get form metadata for the gift card batch form.
     */
    public function getFormMeta(): JsonResponse
    {
        return ApiResponse::success($this->service->getFormMeta());
    }
}
