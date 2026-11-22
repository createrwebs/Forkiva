<?php

namespace Modules\Installer\Http\Middleware;

use App\Forkiva;
use Closure;
use Dotenv\Dotenv;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Modules\Installer\Services\Environment\InstallerEnvironmentInterface;
use Modules\Installer\Services\State\InstallerStateInterface;
use Modules\Installer\Support\InstallerFailure;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PrepareInstaller
{
    /**
     * Isolate setup from database sessions and initialize encryption before CSRF handling.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $installerAction = $request->is('installer/status')
            || ($request->isMethod('POST') && $request->is(
                'installer/checks',
                'installer/configuration',
                'installer/database',
                'installer/process',
                'installer/admin',
                'installer/finish'
            ));

        if ($installerAction) {
            $request->headers->set('Accept', 'application/json');
        }

        if (! Forkiva::installed()) {
            // Seeded accounts must not be usable until the administrator completes setup.
            if ($request->is('api', 'api/*')) {
                return response()->json([
                    'message' => 'Installation is not complete.',
                ], 503);
            }

            if ($request->is('installer', 'installer/*')) {
                config([
                    'session.driver' => 'file',
                    'session.cookie' => 'forkiva_installer_session',
                    'session.encrypt' => false,
                    'cache.default' => 'array',
                ]);

                if (! config('app.key')) {
                    try {
                        app(InstallerStateInterface::class)->locked(function () {
                            // Bootstrap encryption once, before session/CSRF middleware.
                            // Never rotate an existing key.
                            $path = app()->environmentFilePath();

                            $values = is_file($path)
                                ? Dotenv::parse(file_get_contents($path))
                                : [];

                            $key = $values['APP_KEY'] ?? '';

                            if ($key === '') {
                                $key = 'base64:'.base64_encode(
                                    Encrypter::generateKey(config('app.cipher'))
                                );

                                app(InstallerEnvironmentInterface::class)->write(['APP_KEY' => $key]);
                            }

                            config(['app.key' => $key]);
                        });
                    } catch (Throwable $exception) {
                        $reference = InstallerFailure::record($exception, 'initializing installer encryption');
                        $message = 'The server could not initialize the private installation session.';
                        $steps = [
                            'Ask hosting support to give the website PHP user write access to storage/framework and its sessions folder, and temporary create/rename access to the folder containing .env. Making only the .env file writable is not enough.',
                            'From the folder containing artisan, run php artisan config:clear using the website PHP version, or ask hosting support to run it. Do not delete or replace an existing APP_KEY.',
                            'Ask support to check storage/logs or the hosting PHP error log using the reference below. After fixing the cause, reload this page in the same browser.',
                        ];

                        if ($installerAction) {
                            return response()->json(['message' => $message.' '.implode(' ', $steps).' Support reference: '.$reference], 503);
                        }

                        // Plain PHP avoids depending on writable compiled views or uploaded Vite assets.
                        return response(view()->file(module_path('Installer', 'resources/views/unavailable.php'), [
                            'title' => 'Setup needs a server adjustment',
                            'message' => $message,
                            'steps' => $steps,
                            'reference' => $reference,
                        ])->render(), 503)->header('Cache-Control', 'no-store, private');
                    }
                }
            }
        }

        return $next($request);
    }
}
