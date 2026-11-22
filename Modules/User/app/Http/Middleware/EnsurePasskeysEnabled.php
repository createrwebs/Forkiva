<?php

namespace Modules\User\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\User\Services\Auth\PasskeyConfiguration;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasskeysEnabled
{
    /**
     * Create the passkey feature middleware.
     */
    public function __construct(protected PasskeyConfiguration $configuration) {}

    /**
     * Apply runtime configuration and reject disabled passkey requests.
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $this->configuration->apply();

        abort_unless($this->configuration->enabled(), 404, __('user::auth.passkeys_disabled'));

        return $next($request);
    }
}
