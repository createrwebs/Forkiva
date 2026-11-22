<?php

namespace Modules\Inventory\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\Api\V1\ReceiveStockTransferRequest;
use Modules\Inventory\Http\Requests\Api\V1\SaveStockTransferRequest;
use Modules\Inventory\Http\Requests\Api\V1\StockTransferFormMetaRequest;
use Modules\Inventory\Http\Requests\Api\V1\StockTransferIngredientOptionsRequest;
use Modules\Inventory\Http\Requests\Api\V1\StockTransferReasonRequest;
use Modules\Inventory\Services\StockTransfer\StockTransferServiceInterface;
use Modules\Inventory\Transformers\Api\V1\StockTransferReceiptResource;
use Modules\Inventory\Transformers\Api\V1\StockTransferResource;
use Modules\Inventory\Transformers\Api\V1\StockTransferShowResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Throwable;

class StockTransferController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected StockTransferServiceInterface $service) {}

    /**
     * Display a paginated stock transfer list.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: StockTransferResource::class,
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
     * Display one stock transfer.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new StockTransferShowResource($this->service->show($id)));
    }

    /**
     * Store a draft stock transfer.
     *
     *
     * @throws Throwable
     */
    public function store(SaveStockTransferRequest $request): JsonResponse
    {
        return ApiResponse::created(
            new StockTransferShowResource($this->service->store($request->validated())),
            $this->service->label(),
        );
    }

    /**
     * Update a draft stock transfer.
     *
     *
     * @throws Throwable
     */
    public function update(SaveStockTransferRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            new StockTransferShowResource($this->service->update($id, $request->validated())),
            $this->service->label(),
        );
    }

    /**
     * Approve a stock transfer.
     *
     *
     * @throws Throwable
     */
    public function approve(int $id): JsonResponse
    {
        return ApiResponse::success(
            new StockTransferShowResource($this->service->approve($id)),
            __('inventory::messages.stock_transfer_approved'),
        );
    }

    /**
     * Dispatch a stock transfer and create outbound movements.
     *
     *
     * @throws Throwable
     */
    public function dispatch(int $id): JsonResponse
    {
        return ApiResponse::success(
            new StockTransferShowResource($this->service->dispatch($id)),
            __('inventory::messages.stock_transfer_dispatched'),
        );
    }

    /**
     * Receive dispatched stock transfer quantities.
     *
     *
     * @throws Throwable
     */
    public function receive(ReceiveStockTransferRequest $request, int $id): JsonResponse
    {
        return ApiResponse::created(
            new StockTransferReceiptResource($this->service->receive($id, $request->validated())),
            __('inventory::stock_transfers.receipt'),
        );
    }

    /**
     * Close a stock transfer after receiving outstanding quantities.
     *
     *
     * @throws Throwable
     */
    public function close(StockTransferReasonRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(
            new StockTransferShowResource($this->service->close($id, $request->validated('reason'))),
            __('inventory::messages.stock_transfer_closed'),
        );
    }

    /**
     * Cancel a draft or approved stock transfer.
     *
     *
     * @throws Throwable
     */
    public function cancel(StockTransferReasonRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(
            new StockTransferShowResource($this->service->cancel($id, $request->validated('reason'))),
            __('inventory::messages.stock_transfer_cancelled'),
        );
    }

    /**
     * Void a stock transfer and reverse committed stock movements.
     *
     *
     * @throws Throwable
     */
    public function void(StockTransferReasonRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(
            new StockTransferShowResource($this->service->void($id, $request->validated('reason'))),
            __('inventory::messages.stock_transfer_voided'),
        );
    }

    /**
     * Get stock transfer form metadata.
     */
    public function getFormMeta(StockTransferFormMetaRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return ApiResponse::success(
            $this->service->getFormMeta(
                sourceBranchId: isset($validated['source_branch_id']) ? (int) $validated['source_branch_id'] : null,
                destinationBranchId: isset($validated['destination_branch_id']) ? (int) $validated['destination_branch_id'] : null,
                sourceIngredientIds: $validated['source_ingredient_ids'] ?? [],
                destinationIngredientIds: $validated['destination_ingredient_ids'] ?? [],
            )
        );
    }

    /**
     * Search bounded ingredient options for transfer lines.
     */
    public function getIngredientOptions(StockTransferIngredientOptionsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return ApiResponse::success(
            $this->service->getIngredientOptions(
                branchId: (int) $validated['branch_id'],
                role: $validated['role'],
                search: $validated['search'] ?? null,
                ids: $validated['ids'] ?? [],
                unitId: isset($validated['unit_id']) ? (int) $validated['unit_id'] : null,
                sourceBranchId: isset($validated['source_branch_id']) ? (int) $validated['source_branch_id'] : null,
            )
        );
    }
}
