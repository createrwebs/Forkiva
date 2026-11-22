<?php

namespace Modules\Order\Support;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class CheckoutTelemetry
{
    /**
     * Record only operational measurements, never input, tokens, customer details or exception text.
     */
    public static function run(string $channel, string $operation, callable $callback): mixed
    {
        $started = hrtime(true);
        $status = 200;
        try {
            return $callback();
        } catch (Throwable $error) {
            $status = match (true) {
                $error instanceof ValidationException => 422,
                $error instanceof HttpResponseException => $error->getResponse()->getStatusCode(),
                $error instanceof HttpExceptionInterface => $error->getStatusCode(),
                default => 500,
            };
            throw $error;
        } finally {
            Log::info('order_checkout', [
                'channel' => $channel,
                'operation' => $operation,
                'status' => $status,
                'duration_ms' => round((hrtime(true) - $started) / 1e6, 2),
            ]);
        }
    }
}
