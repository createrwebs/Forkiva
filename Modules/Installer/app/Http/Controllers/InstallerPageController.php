<?php

namespace Modules\Installer\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\Controller;
use Modules\Installer\Services\Configuration\InstallerConfigurationInterface;
use Modules\Installer\Services\State\InstallerStateInterface;
use Modules\Installer\Support\InstallerFailure;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class InstallerPageController extends Controller
{
    /**
     * Render the Vue shell or a safe recovery page without exposing another session's state.
     */
    public function index(InstallerStateInterface $tracker, InstallerConfigurationInterface $configuration): Response
    {
        try {
            $state = $tracker->snapshot();
            $tracker->assertOwner($state);

            // Render inside the guard: missing build files must also have a useful recovery page.
            return response(view()->file(
                module_path('Installer', 'resources/views/index.blade.php'),
                ['initialState' => $tracker->publicState($state) + ['options' => $configuration->options()]]
            )->render());
        } catch (Throwable $exception) {
            $conflict = $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 409;
            $reference = $conflict ? null : InstallerFailure::record($exception, 'opening installer');

            return response(view()->file(module_path('Installer', 'resources/views/unavailable.php'), [
                'title' => $conflict ? 'Continue your existing setup session' : 'Setup could not open',
                'message' => $conflict
                    ? 'Installation has already started in another browser session. This protects your setup from changes by another visitor.'
                    : 'The server could not load the installer. Your installation data has not been reset.',
                'steps' => $conflict ? [
                    'Open this same website address in the browser and profile where you started setup. If you used a private window, return to that window.',
                    'Keep that browser\'s cookies. A different device, an expired session, or cleared cookies will not have access to the original setup.',
                    'If that session is no longer available, ask your site administrator to review the saved installation progress and arrange recovery. Do not delete installer files or database tables to bypass this protection.',
                ] : [
                    'Ask hosting support to check PHP file permissions for storage/framework, storage/framework/views and storage/logs, and confirm that public/build was uploaded completely.',
                    'Give support the reference below so they can find the cause in storage/logs or the hosting PHP error log. Do not send passwords or your .env file.',
                    'After the cause is corrected, reload this page in the same browser.',
                ],
                'reference' => $reference,
            ])->render(), $conflict ? 409 : 503)->header('Cache-Control', 'no-store, private');
        }
    }
}
