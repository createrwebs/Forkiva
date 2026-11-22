<?php

namespace Modules\Pos\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Branch\Models\Branch;
use Modules\Core\Http\Controllers\Controller;
use Modules\Pos\Services\HoldCart\PosHoldCartService;
use Modules\Pos\Services\OfflineSync\OfflineOrderSyncService;
use Modules\Product\Models\Product;
use Modules\Product\Services\Retail\RetailBarcodeService;
use Modules\Support\ApiResponse;

class RetailPosController extends Controller
{
    public function __construct(
        private RetailBarcodeService $barcodeService,
        private PosHoldCartService $holdCartService,
        private OfflineOrderSyncService $offlineSyncService
    ) {}

    public function lookupByBarcode(string $code): JsonResponse
    {
        $weighted = $this->barcodeService->decodeWeightedEan13($code);
        $lookupSku = $weighted !== null ? $weighted['sku_code'] : $code;

        $product = Product::query()
            ->where('barcode', $code)
            ->orWhere('sku', $lookupSku)
            ->first();

        if ($product === null) {
            abort(404, 'Product not found for barcode.');
        }

        return ApiResponse::success([
            'product' => $product,
            'weighted' => $weighted,
        ]);
    }

    public function holdCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'label' => ['required', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1'],
        ]);

        $held = $this->holdCartService->hold(
            branchId: (int) $validated['branch_id'],
            label: (string) $validated['label'],
            items: $validated['items']
        );

        return ApiResponse::created($held);
    }

    public function listHeldCarts(Request $request): JsonResponse
    {
        $branchId = $request->integer('branch_id');
        abort_if($branchId <= 0, 422, 'branch_id is required.');

        return ApiResponse::success($this->holdCartService->listActive($branchId));
    }

    public function resumeHeldCart(int $id): JsonResponse
    {
        return ApiResponse::success($this->holdCartService->resume($id));
    }

    public function offlineSync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'orders' => ['required', 'array', 'min:1', 'max:200'],
            'orders.*.client_uuid' => ['required', 'uuid'],
            'orders.*.offline_reference' => ['required', 'string', 'max:80'],
            'orders.*.payment_method' => ['required', 'string', 'max:30'],
            'orders.*.total' => ['required', 'numeric', 'min:0'],
            'orders.*.items' => ['required', 'array', 'min:1'],
            'orders.*.stock_shortage' => ['nullable', 'boolean'],
        ]);

        $branch = Branch::query()->findOrFail((int) $validated['branch_id']);

        return ApiResponse::success([
            'results' => $this->offlineSyncService->syncBatch($branch, $validated['orders']),
        ]);
    }
}
