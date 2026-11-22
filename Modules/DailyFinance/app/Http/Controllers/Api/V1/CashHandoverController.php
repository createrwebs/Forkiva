<?php

namespace Modules\DailyFinance\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\DailyFinance\Http\Requests\Api\V1\CashHandoverRequest;
use Modules\DailyFinance\Services\CashHandover\CashHandoverServiceInterface;
use Modules\DailyFinance\Transformers\Api\V1\CashHandoverFollowUpResource;
use Modules\DailyFinance\Transformers\Api\V1\CashHandoverFollowUpSessionResource;
use Modules\DailyFinance\Transformers\Api\V1\CashHandoverResource;
use Modules\Support\ApiResponse;

class CashHandoverController extends Controller
{
    public function __construct(protected CashHandoverServiceInterface $service) {}

    /**
     * Read authorized custody balances and paginated handovers for one session.
     */
    public function index(CashHandoverRequest $request, int $session): JsonResponse
    {
        return ApiResponse::success(
            $this->service->summary($session, (int) ($request->validated()['page'] ?? 1))
        );
    }

    /**
     * Read current custody balances across all dates for the authorized branch.
     */
    public function followUp(CashHandoverRequest $request): JsonResponse
    {
        return ApiResponse::success(new CashHandoverFollowUpResource(
            $this->service->followUp((int) $request->validated()['branch_id'])
        ));
    }

    /**
     * List source sessions requiring a handover or receipt confirmation.
     */
    public function followUpSessions(CashHandoverRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->service->followUpSessions((int) $data['branch_id'], $data['kind'], (int) ($data['page'] ?? 1));

        return ApiResponse::success([
            ...$result,
            'data' => CashHandoverFollowUpSessionResource::collection($result['data'])->resolve(),
        ]);
    }

    /**
     * Reserve cash for a named receiver using an idempotent request reference.
     */
    public function store(CashHandoverRequest $request, int $session): JsonResponse
    {
        return ApiResponse::success(
            new CashHandoverResource($this->service->create($session, $request->validated()))
        );
    }

    /**
     * Record the named receiver’s counted amount without rewriting POS history.
     */
    public function receive(CashHandoverRequest $request, int $session, int $handover): JsonResponse
    {
        return ApiResponse::success(
            new CashHandoverResource($this->service->receive($session, $handover, $request->validated()))
        );
    }

    /**
     * Release a pending reservation while preserving its cancellation evidence.
     */
    public function cancel(CashHandoverRequest $request, int $session, int $handover): JsonResponse
    {
        return ApiResponse::success(
            new CashHandoverResource($this->service->cancel($session, $handover, $request->validated()))
        );
    }
}
