<?php

namespace Modules\GiftCard\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\GiftCard\Http\Requests\Api\V1\SaveGiftCardRequest;
use Modules\GiftCard\Services\GiftCard\GiftCardServiceInterface;
use Modules\GiftCard\Transformers\Api\V1\GiftCardResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class GiftCardController extends Controller
{
    /**
     * Create a new instance of GiftCardController.
     */
    public function __construct(protected GiftCardServiceInterface $service) {}

    /**
     * Display a listing of gift cards.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: GiftCardResource::class,
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
     * Display the specified gift card.
     */
    public function show(int|string $id): JsonResponse
    {
        return ApiResponse::success(body: new GiftCardResource($this->service->show($id)));
    }

    /**
     * Store a newly created gift card in storage.
     */
    public function store(SaveGiftCardRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new GiftCardResource($this->service->store($request->validated())),
            resource: $this->service->label(),
        );
    }

    /**
     * Update the specified gift card in storage.
     */
    public function update(SaveGiftCardRequest $request, int|string $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new GiftCardResource($this->service->update($id, $request->validated())),
            resource: $this->service->label(),
        );
    }

    /**
     * Remove the specified gift cards from storage.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label(),
        );
    }

    /**
     * Get form metadata for the gift card form.
     */
    public function getFormMeta(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getFormMeta(
            $request->integer('branch_id') ?: null,
        ));
    }

    /**
     * Get the current balance details for the specified gift card.
     */
    public function balance(int|string $id): JsonResponse
    {
        return ApiResponse::success($this->service->balance($id));
    }
}
