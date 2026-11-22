<?php

namespace Modules\Order\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Core\Http\Controllers\Controller;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Facades\Pricing;
use Modules\Order\Http\Requests\Api\V1\CancelOrRefundOrderRequest;
use Modules\Order\Http\Requests\Api\V1\OrderPaymentRequest;
use Modules\Order\Http\Requests\Api\V1\SaveOrderRequest;
use Modules\Order\Models\Order;
use Modules\Order\Services\Checkout\PosCheckout;
use Modules\Order\Services\Order\OrderServiceInterface;
use Modules\Order\Services\OrderPayment\OrderPaymentServiceInterface;
use Modules\Order\Services\SaveOrder\SaveOrderServiceInterface;
use Modules\Order\Support\CheckoutTelemetry;
use Modules\Order\Support\OrderRevision;
use Modules\Order\Transformers\Api\V1\ActiveOrderResource;
use Modules\Order\Transformers\Api\V1\OrderResource;
use Modules\Order\Transformers\Api\V1\ShowOrderResource;
use Modules\Printer\Enum\PrintContentType;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class OrderController extends Controller
{
    /**
     * Create a new instance of OrderCreateController
     */
    public function __construct(
        protected OrderServiceInterface $service,
        protected SaveOrderServiceInterface $saveOrderService,
        protected OrderPaymentServiceInterface $paymentService,
    ) {}

    /**
     * This method retrieves and returns a list of Payment models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: OrderResource::class,
            filters: $request->input('with_filters')
                ? $this->service->getStructureFilters()
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
     * This method cancel order
     *
     * @throws Throwable
     */
    public function cancel(CancelOrRefundOrderRequest $request, int|string $id): JsonResponse
    {
        $this->service->cancel($id, $request->validated());

        return ApiResponse::success(
            body: ['success' => true],
            message: __('admin::messages.resources_cancelled', ['resource' => $this->service->label()])
        );
    }

    /**
     * This method Refund order
     *
     * @throws Throwable
     */
    public function refund(CancelOrRefundOrderRequest $request, int|string $id): JsonResponse
    {
        $this->service->refund($id, $request->validated());

        return ApiResponse::success(body: ['success' => true], message: __('order::messages.order_refunded'));
    }

    /**
     * This method edit order
     *
     * @throws Throwable
     */
    public function edit(int|string $id): JsonResponse
    {
        return ApiResponse::success(body: [
            ...$this->service->initEdit($id),
            'cart' => Pricing::instance(),
            'edit_version' => OrderRevision::for(Order::findOrFail($id)),
        ]);
    }

    /**
     * This method retrieves and returns a single Payment model based on the provided identifier.
     */
    public function show(int|string $id): JsonResponse
    {
        return ApiResponse::success(
            body: new ShowOrderResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the Order model.
     *
     * @throws Throwable
     */
    public function quote(SaveOrderRequest $request, ?int $orderId = null): JsonResponse
    {
        return ApiResponse::success(CheckoutTelemetry::run('pos', 'quote', fn () => app(PosCheckout::class)->quote($request->validated(), $orderId)));
    }

    /**
     * Commit a confirmed new order or replay its previously committed submission.
     *
     * @throws HttpExceptionInterface
     * @throws Throwable
     */
    public function store(SaveOrderRequest $request): JsonResponse
    {
        return ApiResponse::success(CheckoutTelemetry::run('pos', 'create', fn () => app(PosCheckout::class)->submit($request->validated())));
    }

    /**
     * Save a confirmed edit only while its persisted order revision still matches.
     *
     * @throws HttpExceptionInterface
     * @throws Throwable
     */
    public function update(SaveOrderRequest $request, int $orderId): JsonResponse
    {
        return ApiResponse::success(CheckoutTelemetry::run('pos', 'edit', fn () => app(PosCheckout::class)->submit($request->validated(), $orderId)));
    }

    /**
     * Get add payment meta
     */
    public function getPaymentMeta(int|string $id): JsonResponse
    {
        return ApiResponse::success($this->paymentService->getPaymentMeta($id));
    }

    /**
     * Get a PromptPay QR payload for collecting this order's balance.
     */
    public function promptPayQr(Request $request, int|string $id): JsonResponse
    {
        $amount = $request->query('amount');

        return ApiResponse::success(
            body: $this->paymentService->getPromptPayQr($id, is_string($amount) ? $amount : null)
        );
    }

    /**
     * Get update status meta
     */
    public function getUpdateStatusMeta(int|string $id): JsonResponse
    {
        return ApiResponse::success($this->service->getUpdateStatusMeta($id));
    }

    /**
     * Order add payment
     *
     * @throws Throwable
     */
    public function storePayment(OrderPaymentRequest $request, int|string $orderId): JsonResponse
    {
        $this->paymentService->storePayment($orderId, $request->validated());

        return ApiResponse::success(message: __('order::messages.order_paid_successfully'));
    }

    /**
     * Get active orders
     */
    public function activeOrders(Request $request): JsonResponse
    {
        $user = auth()->user();
        $branchId = $request->input('branch_id');
        $filters = $this->normalizeDrawerFilters($request);
        $branch = $user->assignedToBranch()
            ? $user->branch
            : (! is_null($branchId) ? Branch::where('id', $branchId)->first() : $user->effective_branch);

        return ApiResponse::pagination(
            paginator: $this->service->activeOrders($branch->id, $filters),
            resource: ActiveOrderResource::class,
            filters: [
                'statuses' => OrderStatus::toArrayTrans([
                    OrderStatus::Refunded->value,
                    OrderStatus::Cancelled->value,
                    OrderStatus::Merged->value,
                    OrderStatus::Served->value,
                ]),
                'order_types' => array_values(
                    array_filter(
                        OrderType::toArrayTrans(),
                        fn ($orderType) => $orderType['id'] !== OrderType::DineIn->value
                            && in_array(
                                $orderType['id'],
                                app(DeliveryFeatureInterface::class)->filterSupportedOrderTypes($branch, $branch->order_types ?: [])
                            )
                    )
                ),
                'payment_statuses' => OrderPaymentStatus::toArrayTrans(),
            ],
        );
    }

    /**
     * Normalize drawer filters
     */
    protected function normalizeDrawerFilters(Request $request): array
    {
        return [
            'search' => trim((string) $request->input('search', '')),
            'types' => $this->normalizeFilterValues($request->input('types', [])),
            'statuses' => $this->normalizeFilterValues($request->input('statuses', [])),
            'payment_statuses' => $this->normalizeFilterValues($request->input('payment_statuses', [])),
        ];
    }

    /**
     * Normalize filter values
     */
    protected function normalizeFilterValues(mixed $values): array
    {
        return collect($values)
            ->filter(fn ($value) => ! is_null($value) && $value !== '')
            ->values()
            ->all();
    }

    /**
     * Get upcoming orders
     */
    public function upcomingOrders(Request $request): JsonResponse
    {
        $user = auth()->user();
        $branchId = $request->input('branch_id');
        $filters = $this->normalizeDrawerFilters($request);
        $branch = $user->assignedToBranch()
            ? $user->branch
            : (! is_null($branchId) ? Branch::where('id', $branchId)->first() : $user->effective_branch);

        return ApiResponse::pagination(
            paginator: $this->service->upcomingOrders($branch->id, $filters),
            resource: ActiveOrderResource::class,
            filters: [
                'statuses' => OrderStatus::toArrayTrans([
                    OrderStatus::Refunded->value,
                    OrderStatus::Cancelled->value,
                    OrderStatus::Merged->value,
                    OrderStatus::Completed->value,
                    OrderStatus::Served->value,
                    OrderStatus::Ready->value,
                    OrderStatus::Preparing->value,
                ]),
                'order_types' => array_values(
                    array_filter(
                        OrderType::toArrayTrans(),
                        fn ($orderType) => in_array($orderType['id'], [OrderType::Catering->value, OrderType::PreOrder->value])
                            && in_array(
                                $orderType['id'],
                                app(DeliveryFeatureInterface::class)->filterSupportedOrderTypes($branch, $branch->order_types ?: [])
                            )
                    )
                ),
                'payment_statuses' => OrderPaymentStatus::toArrayTrans(),
            ],
        );
    }

    /**
     * Move to next status
     *
     * @throws Throwable
     */
    public function moveToNextStatus(int|string $orderId): JsonResponse
    {
        $newStatus = $this->service->moveToNextStatus($orderId);

        return ApiResponse::success(message: __('order::messages.order_update_status_to_successfully', ['status' => $newStatus->trans()]));
    }

    /**
     * Preview print
     *
     * @throws Throwable
     */
    public function previewPrint(Request $request, int|string $orderId, PrintContentType $type): JsonResponse|Response
    {
        $contentType = $request->input('content_type', 'html');
        $preview = $this->service->previewPrint(
            $orderId,
            $type,
            $request->kitchen_id,
            $request->specific_id,
            $contentType
        );

        if ($request->input('response_type', 'json') !== 'file') {
            return ApiResponse::success($preview);
        }

        $filename = "order-$orderId-$type->value";
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        [$content, $mimeType, $extension] = match ($contentType) {
            'image' => [
                base64_decode($preview['content'], true) ?: $preview['content'],
                'image/png',
                'png',
            ],
            'pdf' => [
                base64_decode($preview['content'], true) ?: $preview['content'],
                'application/pdf',
                'pdf',
            ],
            default => [
                $preview['content'],
                'text/html; charset=UTF-8',
                'html',
            ],
        };

        return response(
            $content,
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => $disposition.'; filename="'.$filename.'.'.$extension.'"',
            ]
        );
    }

    /**
     * Get print meta
     *
     * @throws Throwable
     */
    public function printMeta(Request $request, int|string $orderId): JsonResponse
    {
        return ApiResponse::success(
            $this->service->printMeta(
                $orderId,
                $request->input('branch_id'),
                $request->input('register_id'),
            )
        );
    }
}
