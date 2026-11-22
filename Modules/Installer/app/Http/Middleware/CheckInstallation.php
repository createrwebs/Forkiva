<?php

namespace Modules\Installer\Http\Middleware;

use App\Forkiva;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckInstallation
{
    /**
     * Deny access after installation and prevent caching of installer responses.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Forkiva::installed()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Installation is already complete.'], 403)
                : redirect('/auth/login');
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
