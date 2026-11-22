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

        // Group OR conditions so Eloquent global scopes (tenant/active/soft-delete) cannot be bypassed.
        $product = Product::query()
            ->where(function ($query) use ($code, $lookupSku): void {
                $query->where('barcode', $code)
                    ->orWhere('sku', $lookupSku);
            })
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

        $branchId = (int) $validated['branch_id'];
        $this->assertBranchAccess($request, $branchId);

        $held = $this->holdCartService->hold(
            branchId: $branchId,
            label: (string) $validated['label'],
            items: $validated['items']
        );

        return ApiResponse::created($held);
    }

    public function listHeldCarts(Request $request): JsonResponse
    {
        $branchId = $request->integer('branch_id');
        abort_if($branchId <= 0, 422, 'branch_id is required.');
        $this->assertBranchAccess($request, $branchId);

        return ApiResponse::success($this->holdCartService->listActive($branchId));
    }

    public function resumeHeldCart(Request $request, int $id): JsonResponse
    {
        $cart = \Modules\Pos\Models\PosHeldCart::query()->whereNull('resumed_at')->findOrFail($id);
        $this->assertBranchAccess($request, (int) $cart->branch_id);

        return ApiResponse::success($this->holdCartService->resume($cart->id));
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

        $branchId = (int) $validated['branch_id'];
        $this->assertBranchAccess($request, $branchId);

        $branch = Branch::query()->findOrFail($branchId);

        return ApiResponse::success([
            'results' => $this->offlineSyncService->syncBatch($branch, $validated['orders']),
        ]);
    }

    private function assertBranchAccess(Request $request, int $branchId): void
    {
        $user = $request->user();

        if ($user !== null && method_exists($user, 'assignedToBranch') && $user->assignedToBranch() && (int) $user->branch_id !== $branchId) {
            abort(403, 'You are not authorized to access this branch.');
        }
    }
}
