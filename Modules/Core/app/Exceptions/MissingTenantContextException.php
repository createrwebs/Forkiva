<?php

namespace Modules\Core\Exceptions;

use RuntimeException;

class MissingTenantContextException extends RuntimeException
{
    public static function create(): self
    {
        return new self('Tenant context is required for this operation.');
    }
}
