<?php

namespace Modules\DailyFinance\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\DailyFinance\Http\Requests\Api\V1\PaymentSettlementRequest;
use Modules\DailyFinance\Services\PaymentSettlement\PaymentSettlementServiceInterface;
use Modules\DailyFinance\Transformers\Api\V1\PaymentSettlementCandidateResource;
use Modules\DailyFinance\Transformers\Api\V1\PaymentSettlementResource;
use Modules\DailyFinance\Transformers\Api\V1\PaymentSettlementSummaryResource;
use Modules\Support\ApiResponse;

class PaymentSettlementController extends Controller
{
    public function __construct(protected PaymentSettlementServiceInterface $service) {}

    /**
     * Read electronic methods, branch currency and permitted bank-account choices.
     */
    public function meta(PaymentSettlementRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->meta((int) $request->validated()['branch_id'])
        );
    }

    /**
     * Read the branch's current pending and partial balances independently of the selected sales day.
     */
    public function summary(PaymentSettlementRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new PaymentSettlementSummaryResource(
                $this->service->summary((int) $request->validated()['branch_id'])
            )
        );
    }

    /**
     * Read unreserved source collections without modifying their payment lifecycle.
     */
    public function candidates(PaymentSettlementRequest $request): JsonResponse
    {
        return ApiResponse::pagination(
            $this->service->candidates($request->validated()),
            PaymentSettlementCandidateResource::class
        );
    }

    /**
     * Read branch-scoped batches with expected, received and outstanding amounts.
     */
    public function index(PaymentSettlementRequest $request): JsonResponse
    {
        return ApiResponse::pagination(
            $this->service->history($request->validated()),
            PaymentSettlementResource::class
        );
    }

    /**
     * Review frozen collection details and immutable bank-credit evidence.
     */
    public function show(PaymentSettlementRequest $request, int $settlement): JsonResponse
    {
        return ApiResponse::success(
            new PaymentSettlementResource(
                $this->service->show((int) $request->validated()['branch_id'], $settlement)
            )
        );
    }

    /**
     * Reserve collections once using a durable idempotent request reference.
     */
    public function store(PaymentSettlementRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new PaymentSettlementResource(
                $this->service->create($request->validated())
            )
        );
    }

    /**
     * Append a partial or final bank credit with an explanation for final differences.
     */
    public function receive(PaymentSettlementRequest $request, int $settlement): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success(
            new PaymentSettlementResource(
                $this->service->receive((int) $data['branch_id'],
                    $settlement,
                    $data
                ))
        );
    }

    /**
     * Release an untouched pending batch while keeping its source and cancellation evidence.
     */
    public function cancel(PaymentSettlementRequest $request, int $settlement): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success(
            new PaymentSettlementResource(
                $this->service->cancel((int) $data['branch_id'],
                    $settlement,
                    $data['cancellation_reason'])
            )
        );
    }
}
