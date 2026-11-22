<?php

namespace Modules\Loyalty\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Loyalty\Http\Requests\Api\V1\SaveLoyaltyRewardRequest;
use Modules\Loyalty\Services\LoyaltyReward\LoyaltyRewardServiceInterface;
use Modules\Loyalty\Transformers\Api\V1\LoyaltyRewardResource;
use Modules\Loyalty\Transformers\Api\V1\ShowLoyaltyRewardResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class LoyaltyRewardController extends Controller
{
    /**
     * Create a new instance of LoyaltyRewardController
     */
    public function __construct(protected LoyaltyRewardServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of LoyaltyReward models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: LoyaltyRewardResource::class,
            filters: $request->input('with_filters')
                ? $this->service->getStructureFilters($request->input('filters')['loyalty_program_id'] ?? null)
                : null,
            extra: [
                'export_methods' => ExportType::toArrayTrans(),
                'export_api_uri' => '/v1/exports/'.$this->service->exportResourceKey(),
                'export_permission' => $this->service->exportPermission(),
                'export_resource' => $this->service->exportResourceKey(),
            ]
        );
    }

    /**
     * This method retrieves and returns a single LoyaltyReward model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new ShowLoyaltyRewardResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the LoyaltyReward model.
     */
    public function store(SaveLoyaltyRewardRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new LoyaltyRewardResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the LoyaltyReward model.
     */
    public function update(SaveLoyaltyRewardRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new LoyaltyRewardResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the LoyaltyReward model based on the provided ids.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label()
        );
    }

    /**
     * Get form meta
     */
    public function getFormMeta(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getFormMeta($request->input('loyalty_program_id')));
    }
}
