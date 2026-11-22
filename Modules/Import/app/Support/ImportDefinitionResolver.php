<?php

namespace Modules\Import\Support;

use Modules\Import\Contracts\ImportDefinitionInterface;

class ImportDefinitionResolver
{
    /**
     * Resolve only definitions registered by enabled resource owners.
     */
    public function resolve(string $resource): ImportDefinitionInterface
    {
        $class = config("import.resources.$resource");
        abort_unless(is_string($class), 404);

        $definition = app($class);
        abort_unless($definition instanceof ImportDefinitionInterface, 500);

        return $definition;
    }
}
