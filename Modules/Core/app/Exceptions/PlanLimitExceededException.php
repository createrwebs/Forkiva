<?php

namespace Modules\Core\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class PlanLimitExceededException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(403, $message);
    }
}
