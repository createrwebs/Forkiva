<?php

namespace Modules\User\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\User\Enums\TwoFactorMethod;
use Modules\User\Http\Requests\Api\V1\TwoFactorCodeRequest;
use Modules\User\Http\Requests\Api\V1\TwoFactorResendRequest;
use Modules\User\Http\Requests\Api\V1\TwoFactorSensitiveActionRequest;
use Modules\User\Http\Requests\Api\V1\TwoFactorSetupRequest;
use Modules\User\Services\TwoFactor\TwoFactorServiceInterface;

class TwoFactorController extends Controller
{
    /**
     * Create a new two-factor controller instance.
     */
    public function __construct(
        protected TwoFactorServiceInterface $service,
    ) {}

    /**
     * Return the authenticated account's two-factor status.
     */
    public function status(): JsonResponse
    {
        return ApiResponse::success($this->service->status(auth()->user()));
    }

    /**
     * Start enrollment for a selected method.
     */
    public function setup(TwoFactorSetupRequest $request): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success($this->service->beginSetup(
            auth()->user(),
            TwoFactorMethod::from($data['method']),
            $data['current_password'],
            $data['code'] ?? null,
        ));
    }

    /**
     * Resend a code for the current session's pending enrollment.
     */
    public function resendSetup(): JsonResponse
    {
        return ApiResponse::success(
            $this->service->resendSetup(auth()->user()),
            __('user::auth.two_factor_code_sent'),
        );
    }

    /**
     * Confirm pending enrollment.
     */
    public function confirm(TwoFactorCodeRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->confirmSetup(auth()->user(), $request->validated('code')),
            __('user::auth.two_factor_enabled_successfully'),
        );
    }

    /**
     * Send a code for an enrolled email or SMS factor.
     */
    public function sendCode(): JsonResponse
    {
        return ApiResponse::success(
            $this->service->sendAccountCode(auth()->user()),
            __('user::auth.two_factor_code_sent'),
        );
    }

    /**
     * Disable two-factor authentication.
     */
    public function destroy(TwoFactorSensitiveActionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->service->disable(auth()->user(), $data['code'], $data['current_password']);

        return ApiResponse::success(
            ['success' => true],
            __('user::auth.two_factor_disabled_successfully'),
        );
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(TwoFactorSensitiveActionRequest $request): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success(
            $this->service->regenerateRecoveryCodes(
                auth()->user(),
                $data['code'],
                $data['current_password'],
            ),
            __('user::auth.two_factor_recovery_codes_regenerated'),
        );
    }

    /**
     * Resend a pending login challenge.
     */
    public function resendLogin(TwoFactorResendRequest $request): JsonResponse
    {
        $challenge = $this->service->resendLoginChallenge($request->validated('challenge_token'));

        return ApiResponse::success(
            $challenge,
            $challenge['delivery_failed']
                ? __('user::auth.two_factor_delivery_failed')
                : __('user::auth.two_factor_code_sent'),
        );
    }
}
