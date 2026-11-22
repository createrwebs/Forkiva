<?php

namespace Modules\Order\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Order\Services\ChannelPricing\ChannelPricingService;
use Modules\Order\Services\DeliveryAggregator\GrabFoodAdapter;
use Modules\Order\Services\DeliveryAggregator\LineManAdapter;
use Modules\Order\Services\OrderSplit\OrderSplitService;
use Modules\Support\ApiResponse;

class ThaiOrderToolsController extends Controller
{
    public function __construct(
        private OrderSplitService $splitService,
        private ChannelPricingService $channelPricingService
    ) {}

    public function splitEqualPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'total_amount' => ['required', 'numeric', 'gt:0'],
            'ways' => ['required', 'integer', 'min:2', 'max:50'],
            'scale' => ['nullable', 'integer', 'min:0', 'max:4'],
        ]);

        return ApiResponse::success([
            'shares' => $this->splitService->splitEqualAmounts(
                totalAmount: (string) $validated['total_amount'],
                ways: (int) $validated['ways'],
                scale: (int) ($validated['scale'] ?? 2)
            ),
        ]);
    }

    public function channelGpPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gross_amount' => ['required', 'numeric', 'gt:0'],
            'gp_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'vat_on_gp' => ['nullable', 'boolean'],
            'vat_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        return ApiResponse::success($this->channelPricingService->calculateCommission(
            grossAmount: (string) $validated['gross_amount'],
            gpPercent: (string) $validated['gp_percent'],
            vatOnGp: (bool) ($validated['vat_on_gp'] ?? true),
            vatRatePercent: (string) ($validated['vat_rate_percent'] ?? '7.00')
        ));
    }

    public function deliveryWebhook(Request $request, string $provider): JsonResponse
    {
        $adapter = match ($provider) {
            'grabfood' => new GrabFoodAdapter,
            'lineman' => new LineManAdapter,
            default => abort(404, "Unsupported delivery aggregator [{$provider}]."),
        };

        $secret = (string) config("services.delivery_aggregators.{$provider}.webhook_secret", '');
        $signature = (string) $request->header('X-Webhook-Signature', '');

        if ($secret !== '' && ! $adapter->verifyWebhookSignature($request->getContent(), $signature, $secret)) {
            abort(401, 'Invalid delivery webhook signature.');
        }

        return ApiResponse::success($adapter->normalizeOrder($request->all()));
    }
}
