<?php

namespace Modules\Pos\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Order\Transformers\Api\V1\Kitchen\KitchenOrderResource;
use Modules\Pos\Services\KitchenViewer\KitchenViewerServiceInterface;
use Modules\Support\ApiResponse;
use Throwable;

class KitchenViewerController extends Controller
{
    /**
     * Create a new instance of PosController
     */
    public function __construct(protected KitchenViewerServiceInterface $service) {}

    /**
     * Get Configuration
     */
    public function configuration(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getConfiguration($request->input('branch_id')));
    }

    /**
     * Get orders
     */
    public function orders(Request $request): JsonResponse
    {
        $data = $this->service->getOrders(
            $request->input('branch_id'),
            $request->input('last_updated_at'),
            $request->only(['search', 'type', 'types', 'status', 'statuses', 'limit'])
        );

        if ($data['not_modified']) {
            return ApiResponse::success([
                'not_modified' => true,
                'last_updated_at' => $data['last_updated_at'],
            ]);
        }

        return ApiResponse::success([
            'not_modified' => false,
            'orders' => KitchenOrderResource::collection($data['orders']),
            'has_more' => $data['has_more'],
            'limit' => $data['limit'],
            'last_updated_at' => $data['last_updated_at'],
        ]);
    }

    /**
     * Update Order product status
     *
     * @throws Throwable
     */
    public function updateOrderProductStatus(Request $request, int|string $orderId): JsonResponse
    {
        $order = $this->service->moveOrderProductToNextStatus($orderId, $request->input('ids', []));

        return ApiResponse::success([
            'success' => true,
            'order' => new KitchenOrderResource($order),
            'last_updated_at' => $order->products
                ->pluck('updated_at')
                ->push($order->updated_at)
                ->filter()
                ->map(fn ($timestamp) => $timestamp->toISOString())
                ->max(),
        ]);
    }
}
