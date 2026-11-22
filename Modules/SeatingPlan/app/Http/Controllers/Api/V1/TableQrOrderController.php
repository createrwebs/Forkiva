<?php

namespace Modules\SeatingPlan\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Order\Support\CheckoutTelemetry;
use Modules\SeatingPlan\Exceptions\TableQrOrderException;
use Modules\SeatingPlan\Http\Requests\Api\V1\SubmitTableQrOrderRequest;
use Modules\SeatingPlan\Services\TableQrOrder\TableQrOrderServiceInterface;
use Modules\SeatingPlan\Transformers\Api\V1\TableQrContextResource;
use Modules\SeatingPlan\Transformers\Api\V1\TableQrTrackingResource;
use Modules\Support\ApiResponse;

class TableQrOrderController extends Controller
{
    /**
     * Create a new instance of TableQrOrderController.
     */
    public function __construct(protected TableQrOrderServiceInterface $service) {}

    /**
     * Get public QR ordering context.
     */
    public function context(Request $request, string $uuid): JsonResponse
    {
        try {
            return ApiResponse::success(new TableQrContextResource(
                $this->service->context($uuid, $request->input('token'), $request)
            ));
        } catch (TableQrOrderException $exception) {
            return $this->domainError($exception);
        }
    }

    /**
     * Convert domain failures to the project API response contract.
     */
    private function domainError(TableQrOrderException $exception): JsonResponse
    {
        return ApiResponse::errors(
            errors: ['reason' => $exception->reason(), ...$exception->errors()],
            message: $exception->getMessage(),
            code: $exception->statusCode()
        );
    }

    /**
     * Get lightweight public QR order tracking state.
     */
    public function tracking(Request $request, string $uuid): JsonResponse
    {
        try {
            return ApiResponse::success(new TableQrTrackingResource(
                $this->service->tracking($uuid, $request->input('token'))
            ));
        } catch (TableQrOrderException $exception) {
            return $this->domainError($exception);
        }
    }

    public function quote(SubmitTableQrOrderRequest $request, string $uuid): JsonResponse
    {
        try {
            return ApiResponse::success(CheckoutTelemetry::run('table_order', 'quote', fn () => $this->service->quote($uuid, $request->input('token'), $request->validated())));
        } catch (TableQrOrderException $e) {
            return $this->domainError($e);
        }
    }

    public function submit(SubmitTableQrOrderRequest $request, string $uuid): JsonResponse
    {
        try {
            return ApiResponse::success(CheckoutTelemetry::run('table_order', 'submit', fn () => $this->service->submit($uuid, $request->input('token'), $request->validated(), $request)));
        } catch (TableQrOrderException $e) {
            return $this->domainError($e);
        }
    }
}
