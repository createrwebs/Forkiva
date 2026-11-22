<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WebApplicationController extends Controller
{
    /**
     * Serve a public shell; bearer-authenticated API requests still authorize all data.
     */
    public function index(): Response
    {
        return response()
            ->view('app')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Serve the worker at the origin root without exposing other build files through PHP.
     */
    public function serviceWorker(): BinaryFileResponse
    {
        $path = public_path('build/sw.js');

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'no-cache, max-age=0',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
