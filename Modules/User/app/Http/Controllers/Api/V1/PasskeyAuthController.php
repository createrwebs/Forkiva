<?php

namespace Modules\User\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Support\WebAuthn;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\User\Http\Requests\Api\V1\PasskeyLoginRequest;
use Modules\User\Models\User;
use Modules\User\Services\Auth\AuthServiceInterface;
use Modules\User\Services\Auth\PasskeyChallengeService;
use Modules\User\Transformers\Api\V1\AuthResource;

class PasskeyAuthController extends Controller
{
    /**
     * Create a new passkey authentication controller.
     */
    public function __construct(
        protected AuthServiceInterface $authService,
        protected PasskeyChallengeService $challengeService,
    ) {}

    /**
     * Create a short-lived passwordless login challenge.
     */
    public function options(GenerateVerificationOptions $generate): JsonResponse
    {
        $options = $generate();

        $this->challengeService->storeLogin($options);

        return ApiResponse::success([
            'options' => WebAuthn::toBrowserArray($options),
        ]);
    }

    /**
     * Verify a passkey assertion and issue the normal API token.
     */
    public function login(
        PasskeyLoginRequest $request,
        VerifyPasskey $verify,
    ): JsonResponse {
        $credential = $request->credential();
        $options = $this->challengeService->pullLogin($credential);

        try {
            $passkey = $verify->getPasskey($credential);
            $user = $passkey->user;

            abort_unless($user instanceof User, 401, __('auth.failed'));

            $this->authService->validateUserStatus($user);
            $verify($credential, $options, $user);
        } catch (InvalidPasskeyException) {
            throw ValidationException::withMessages([
                'credential' => [__('user::auth.passkey_invalid')],
            ]);
        }

        $data = $this->authService->grantToken($user, name: 'Passkey');

        return ApiResponse::success([
            'user' => new AuthResource($data['user']),
            'token' => $data['token'],
            'token_expires_at' => $data['token_expires_at'],
        ]);
    }
}
