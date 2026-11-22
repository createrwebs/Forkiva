<?php

namespace Modules\Approval\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Modules\Support\ApiResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ApprovalException extends RuntimeException implements ShouldntReport
{
    /**
     * Create an approval-domain validation exception.
     */
    public function __construct(string $message, int $code = Response::HTTP_UNPROCESSABLE_ENTITY)
    {
        parent::__construct($message, $code);
    }

    /**
     * Return approval rule failures through the standard API error envelope.
     */
    public function render(): JsonResponse
    {
        return ApiResponse::errors(
            errors: null,
            message: $this->getMessage(),
            code: $this->getCode(),
        );
    }
}
