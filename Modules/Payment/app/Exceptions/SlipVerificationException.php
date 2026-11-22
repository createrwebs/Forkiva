<?php

namespace Modules\Payment\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class SlipVerificationException extends HttpException
{
    public function __construct(
        int $statusCode,
        public readonly string $errorCode,
        string $message
    ) {
        parent::__construct($statusCode, $message);
    }
}
