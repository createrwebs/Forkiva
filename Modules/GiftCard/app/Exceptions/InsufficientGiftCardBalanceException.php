<?php

namespace Modules\GiftCard\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Support\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class InsufficientGiftCardBalanceException extends GiftCardException implements ShouldntReport
{
    /**
     * Return a validation response for an amount above the locked card balance.
     */
    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        return ApiResponse::errors(
            errors: ['amount' => [$this->getMessage()]],
            message: $this->getMessage(),
            code: Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
