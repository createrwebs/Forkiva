<?php

namespace Modules\User\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Support\WebAuthn;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\User\Http\Requests\Api\V1\PasskeyRegistrationOptionsRequest;
use Modules\User\Http\Requests\Api\V1\StorePasskeyRequest;
use Modules\User\Models\User;
use Modules\User\Services\Auth\PasskeyRegistrationServiceInterface;
use Modules\User\Transformers\Api\V1\PasskeyResource;

class PasskeyController extends Controller
{
    /**
     * Create a new passkey management controller.
     */
    public function __construct(protected PasskeyRegistrationServiceInterface $registration) {}

    /**
     * List the authenticated user's passkeys.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success([
            'passkeys' => PasskeyResource::collection(
                $user->passkeys()->latest()->get()
            ),
        ]);
    }

    /**
     * Create passkey registration options for the authenticated user.
     */
    public function options(
        PasskeyRegistrationOptionsRequest $request,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $options = $this->registration->begin($user, $data['current_password'], $data['code'] ?? null);

        return ApiResponse::success([
            'options' => WebAuthn::toBrowserArray($options),
        ]);
    }

    /**
     * Verify and store a new passkey for the authenticated user.
     */
    public function store(
        StorePasskeyRequest $request,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $passkey = $this->registration->complete($user, $validated['name'], $request->credential());

        return ApiResponse::success(
            body: ['passkey' => new PasskeyResource($passkey)],
            message: __('user::auth.passkey_created')
        );
    }

    /**
     * Delete one of the authenticated user's passkeys.
     */
    public function destroy(
        Request $request,
        Passkey $passkey,
        DeletePasskey $deletePasskey,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        abort_unless((int) $passkey->user_id === $user->id, 403);

        $deletePasskey($user, $passkey);

        return ApiResponse::success(
            body: ['success' => true],
            message: __('user::auth.passkey_deleted')
        );
    }
}
