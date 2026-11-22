<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Installer\Http\Middleware\PrepareInstaller;
use Modules\Support\ApiResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(PrepareInstaller::class);
        $middleware->trimStrings(except: ['db_pass']);
        $middleware->web(['checkInstalled']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (app()->isProduction() && $request->wantsJson() && $exception->getPrevious() instanceof ModelNotFoundException) {
                return ApiResponse::errors(
                    errors: null,
                    message: __('core::exceptions.model_not_found'),
                    code: Response::HTTP_NOT_FOUND
                );
            }

            return null;
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->wantsJson()) {
                return ApiResponse::errors(
                    errors: $exception->errors(),
                    message: __('core::exceptions.unprocessable'),
                    code: Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (
                ! config('app.debug')
                && ($request->is('api/*') || $request->wantsJson())
                && ! $exception instanceof HttpExceptionInterface
                && ! $exception instanceof AuthenticationException
                && ! $exception instanceof AuthorizationException
                && ! $exception instanceof ValidationException
            ) {
                return ApiResponse::errors(
                    errors: null,
                    message: 'Internal server error.',
                    code: Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }

            return null;
        });
    })
    ->create();
