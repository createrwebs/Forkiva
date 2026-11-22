<?php

namespace Modules\UserPreference\Http\Controllers\Api\V1;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\User\Models\User;
use Modules\UserPreference\Http\Requests\Api\V1\StoreUserPreferenceRequest;
use Modules\UserPreference\Services\UserPreference\UserPreferenceServiceInterface;

class UserPreferenceController extends Controller
{
    public function __construct(private readonly UserPreferenceServiceInterface $preferences) {}

    /**
     * List the authenticated user's UI preferences.
     *
     * @throws AuthenticationException
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->preferences->all($this->user($request), prefix: 'ui.'));
    }

    /**
     * Require the application's authenticated user model.
     *
     * @throws AuthenticationException
     */
    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * Return one UI preference for the authenticated user.
     *
     * @throws AuthenticationException
     */
    public function show(Request $request, string $identifier): JsonResponse
    {
        return ApiResponse::success($this->preferences->get($this->user($request), $identifier));
    }

    /**
     * Replace one UI preference using the validated request value.
     *
     * @throws AuthenticationException
     */
    public function update(StoreUserPreferenceRequest $request, string $identifier): JsonResponse
    {
        $user = $this->user($request);
        $this->preferences->set($user, $identifier, $request->validated('value'));

        return ApiResponse::success([
            $identifier => $this->preferences->get($user, $identifier),
        ]);
    }

    /**
     * Remove one UI preference owned by the authenticated user.
     *
     * @throws AuthenticationException
     */
    public function destroy(Request $request, string $identifier): JsonResponse
    {
        $this->preferences->forget($this->user($request), $identifier);

        return ApiResponse::success();
    }
}
