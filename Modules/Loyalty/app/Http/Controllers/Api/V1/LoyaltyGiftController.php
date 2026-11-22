<?php

namespace Modules\Loyalty\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Loyalty\Http\Requests\Api\V1\AvailableGiftsRequest;
use Modules\Loyalty\Http\Requests\Api\V1\GetRewardsRequest;
use Modules\Loyalty\Http\Requests\Api\V1\RedeemRewardRequest;
use Modules\Loyalty\Services\LoyaltyGift\LoyaltyGiftServiceInterface;
use Modules\Loyalty\Transformers\Api\V1\AvailableGiftResource;
use Modules\Loyalty\Transformers\Api\V1\LoyaltyGiftResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Throwable;

class LoyaltyGiftController extends Controller
{
    /**
     * Create a new instance of LoyaltyGiftController
     */
    public function __construct(protected LoyaltyGiftServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of LoyaltyPromotion models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: LoyaltyGiftResource::class,
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
     * Get available rewards for customer
     */
    public function rewards(GetRewardsRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getRewards(
                customerId: $request->customer_id,
                branchId: auth()->user()->effective_branch->id,
            )
        );
    }

    /**
     * Redeem reward
     *
     * @throws Throwable
     */
    public function redeem(RedeemRewardRequest $request, int $rewardId): JsonResponse
    {
        $this->service->redeem(rewardId: $rewardId, customerId: $request->customer_id);

        return ApiResponse::success(message: __('loyalty::messages.redeemed_successfully'));
    }

    /**
     * Get available gifts
     *
     * @throws Throwable
     */
    public function available(AvailableGiftsRequest $request): JsonResponse
    {
        return ApiResponse::success(
            AvailableGiftResource::collection(
                $this->service->availableGifts(customerId: $request->customer_id)
            )
        );
    }
}
