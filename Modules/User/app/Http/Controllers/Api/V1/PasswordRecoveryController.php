<?php

namespace Modules\User\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\User\Http\Requests\Api\V1\IdentifyPasswordRecoveryRequest;
use Modules\User\Http\Requests\Api\V1\PasswordRecoveryRequest;
use Modules\User\Http\Requests\Api\V1\ResetPasswordRequest;
use Modules\User\Http\Requests\Api\V1\VerifyPasswordRecoveryRequest;
use Modules\User\Services\PasswordRecovery\PasswordRecoveryConfiguration;
use Modules\User\Services\PasswordRecovery\PasswordRecoveryIdentityInterface;
use Modules\User\Services\PasswordRecovery\PasswordRecoveryService;

class PasswordRecoveryController extends Controller
{
    /**
     * Return the recovery channels enabled by the feature and delivery settings.
     */
    public function options(PasswordRecoveryConfiguration $configuration): JsonResponse
    {
        return ApiResponse::success(['channels' => $configuration->channels()])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Create an account recovery context without returning contact details.
     */
    public function identify(IdentifyPasswordRecoveryRequest $request, PasswordRecoveryIdentityInterface $identity, PasswordRecoveryService $service): JsonResponse
    {
        return ApiResponse::success($identity->identify($request->validated('identifier'), $service))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Exchange a valid recovery code for a short-lived password reset token.
     */
    public function verify(VerifyPasswordRecoveryRequest $request, PasswordRecoveryService $service): JsonResponse
    {
        return ApiResponse::success($service->verify($request->validated()))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Queue a recovery code and return the same acknowledgement for unknown accounts.
     */
    public function requestCode(PasswordRecoveryRequest $request, PasswordRecoveryService $service): JsonResponse
    {
        return ApiResponse::success(
            $service->requestCode($request->validated()),
            message: __('user::auth.recovery_sent')
        )
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Reset the password using a verified token or the legacy code submission.
     */
    public function reset(ResetPasswordRequest $request, PasswordRecoveryService $service): JsonResponse
    {
        $service->reset($request->validated());

        return ApiResponse::success(
            ['success' => true],
            message: __('user::auth.recovery_success')
        )
            ->header('Cache-Control', 'no-store');
    }
}
