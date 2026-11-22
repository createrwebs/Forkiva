<?php

namespace Modules\User\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\User\Http\Requests\Api\V1\LoginRequest;
use Modules\User\Http\Requests\Api\V1\TwoFactorLoginRequest;
use Modules\User\Services\Auth\AuthServiceInterface;
use Modules\User\Transformers\Api\V1\AuthResource;

class AuthController extends Controller
{
    /**
     * Create a new instance of AuthController
     */
    public function __construct(protected AuthServiceInterface $service) {}

    /**
     * User store authentication
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $this->service->login($request->validated());

        if (! empty($data['two_factor_required'])) {
            return ApiResponse::success($data);
        }

        return ApiResponse::success([
            'user' => new AuthResource($data['user']),
            'token' => $data['token'],
            'token_expires_at' => $data['token_expires_at'],
        ]);
    }

    /**
     * Complete a password login with a second factor.
     */
    public function verifyTwoFactor(TwoFactorLoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $auth = $this->service->completeTwoFactorLogin($data['challenge_token'], $data['code']);

        return ApiResponse::success([
            'user' => new AuthResource($auth['user']),
            'token' => $auth['token'],
            'token_expires_at' => $auth['token_expires_at'],
        ]);
    }

    /**
     * Destroy user authentication
     */
    public function logout(): JsonResponse
    {
        return ApiResponse::success(
            body: ['success' => $this->service->logout()],
            message: __('auth.logout')
        );
    }

    /**
     * This API helps the frontend guarantee that the token is valid and return user data.
     */
    public function check(): JsonResponse
    {
        if (request()->boolean('driver')) {
            $this->service->validateDriverAccess(auth()->user());
        } elseif (request()->boolean('employee')) {
            $this->service->validateEmployeeAccess(auth()->user());
        }

        return ApiResponse::success(['user' => new AuthResource(auth()->user())]);
    }
}
