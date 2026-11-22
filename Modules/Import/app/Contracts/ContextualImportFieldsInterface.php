<?php

namespace Modules\Import\Contracts;

interface ContextualImportFieldsInterface
{
    /**
     * Add field choices for an authorized import context.
     *
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    public function fieldsForContext(array $context): array;
}
