<?php

namespace Modules\SeatingPlan\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class TableQrOrderException extends Exception
{
    public function __construct(
        protected string $reason,
        string $message,
        protected int $statusCode = Response::HTTP_BAD_REQUEST,
        protected array $errors = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Get stable internal reason code.
     */
    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * Get HTTP status code.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get structured API errors.
     *
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
