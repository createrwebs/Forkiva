<?php

namespace Modules\GiftCard\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Modules\Core\Http\Controllers\Controller;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\GiftCard\Http\Requests\Api\V1\RecordGiftCardFinancialActionRequest;
use Modules\GiftCard\Http\Requests\Api\V1\ReverseGiftCardTransactionRequest;
use Modules\GiftCard\Services\GiftCardTransaction\GiftCardTransactionServiceInterface;
use Modules\GiftCard\Transformers\Api\V1\GiftCardTransactionResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Throwable;

class GiftCardTransactionController extends Controller
{
    /**
     * Create a new instance of GiftCardTransactionController.
     */
    public function __construct(protected GiftCardTransactionServiceInterface $service) {}

    /**
     * Display a listing of gift card transactions.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: GiftCardTransactionResource::class,
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
     * Display the specified gift card transaction.
     */
    public function show(int|string $id): JsonResponse
    {
        return ApiResponse::success(body: new GiftCardTransactionResource($this->service->show($id)));
    }

    /**
     * Record an approved financial action against a Gift Card.
     *
     * Recharge, cash-out, issue-void, and adjustment payloads are validated by
     * the request. Manual balance adjustments require their dedicated permission.
     *
     * @param  int|string  $giftCardId  The Gift Card receiving the action.
     *
     * @throws Throwable
     */
    public function storeFinancialAction(RecordGiftCardFinancialActionRequest $request, int|string $giftCardId): JsonResponse
    {
        if (in_array($request->validated('type'), [
            GiftCardTransactionType::Adjustment->value,
            GiftCardTransactionType::AdjustmentDecrease->value,
        ], true)) {
            abort_unless(auth()->user()?->can('admin.gift_card_transactions.manual_adjust'), 403);
        }

        return ApiResponse::created(
            body: new GiftCardTransactionResource($this->service->recordFinancialAction($giftCardId, $request->validated())),
            resource: $this->service->label(),
        );
    }

    /**
     * Reverse one eligible immutable Gift Card transaction through its allowed owner.
     *
     * Payment, issuance, and breakage transactions are deliberately rejected when
     * their owning workflows—not this generic endpoint—must perform the reversal.
     *
     * @throws Throwable
     * @throws JsonException
     */
    public function reverse(ReverseGiftCardTransactionRequest $request, int|string $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new GiftCardTransactionResource($this->service->reverse($id, $request->validated())),
            resource: $this->service->label(),
        );
    }
}
