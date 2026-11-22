<?php

namespace Modules\Payment\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Branch\Models\Branch;
use Modules\Core\Http\Controllers\Controller;
use Modules\Order\Services\OrderPayment\OrderPaymentServiceInterface;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentMode;
use Modules\Payment\Models\SlipVerification;
use Modules\Payment\Services\SlipVerification\SlipVerificationService;
use Modules\Payment\Support\ThaiSlipQrParser;
use Modules\Support\ApiResponse;

class SlipVerificationController extends Controller
{
    public function __construct(
        private SlipVerificationService $service,
        private OrderPaymentServiceInterface $orderPaymentService
    ) {}

    public function parse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_payload' => ['required', 'string', 'max:512'],
        ]);

        return ApiResponse::success(ThaiSlipQrParser::parse($validated['qr_payload'])->toArray());
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'order_id' => ['nullable', 'integer'],
            'session_id' => ['nullable', 'integer'],
            'auto_pay' => ['nullable', 'boolean'],
            'qr_payload' => ['required', 'string', 'max:512'],
            'expected_amount' => ['required', 'numeric', 'gt:0'],
            'driver' => ['nullable', 'in:local_mini_qr,scb_open_api,kbank_open_api'],
        ]);

        $branchId = (int) $validated['branch_id'];
        $user = $request->user();

        // Enforce branch boundary so branch-scoped staff cannot verify slips against another branch.
        if ($user !== null && method_exists($user, 'assignedToBranch') && $user->assignedToBranch() && (int) $user->branch_id !== $branchId) {
            abort(403, 'You are not authorized to verify slips for this branch.');
        }

        $branch = Branch::query()->findOrFail($branchId);
        $driver = (string) ($validated['driver'] ?? ($branch->getAttribute('payment_verify_driver') ?: 'local_mini_qr'));
        $driverConfig = is_array($branch->getAttribute('payment_verify_config'))
            ? $branch->getAttribute('payment_verify_config')
            : [];

        $verification = $this->service->verify(
            branch: $branch,
            qrPayload: (string) $validated['qr_payload'],
            expectedAmount: (string) $validated['expected_amount'],
            orderId: isset($validated['order_id']) ? (int) $validated['order_id'] : null,
            driver: $driver,
            driverConfig: $driverConfig,
        );

        if (! empty($validated['auto_pay']) && ! empty($validated['order_id']) && ! empty($validated['session_id'])) {
            $this->orderPaymentService->storePayment((int) $validated['order_id'], [
                'session_id' => (int) $validated['session_id'],
                'payment_mode' => PaymentMode::Full->value,
                'payments' => [
                    [
                        'method' => PaymentMethod::PromptPay->value,
                        'amount' => (float) $verification->amount,
                        'transaction_id' => $verification->trans_ref,
                    ],
                ],
            ]);
        }

        return ApiResponse::created($verification);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id');

        if ($user !== null && method_exists($user, 'assignedToBranch') && $user->assignedToBranch()) {
            $branchId = (int) $user->branch_id;
        }

        $verifications = SlipVerification::query()
            ->when($branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success($verifications);
    }
}
