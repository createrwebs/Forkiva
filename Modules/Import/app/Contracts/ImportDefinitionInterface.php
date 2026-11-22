<?php

namespace Modules\Import\Contracts;

use Modules\Import\Enums\ImportOperation;
use Modules\Import\Models\DataImport;

interface ImportDefinitionInterface
{
    /**
     * Describe the importable fields in template order.
     *
     * @return array<int, array{key: string, label: string, type: 'text'|'integer'|'boolean'|'date', required: bool, options?: array<int, array{value: string, label: string, matches?: array<string, string>}>, multiple?: bool}>
     */
    public function fields(): array;

    /**
     * Verify that the current actor may access the resource and its stored context.
     */
    public function authorize(?DataImport $import = null): void;

    /**
     * Resolve and authorize context before it is persisted with an import.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, int|null>
     */
    public function resolveContext(array $input): array;

    /**
     * Return a stable key for duplicate detection, or null when none can be formed.
     *
     * @param  array<string, mixed>  $data
     */
    public function key(array $data, DataImport $import): ?string;

    /**
     * Normalize a row and determine its errors, target, and intended operation.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     data: array<string, mixed>,
     *     errors: array<string, array<int, string>>,
     *     operation: ImportOperation,
     *     target_id: ?int
     * }
     */
    public function validate(array $data, DataImport $import): array;

    /**
     * Check cross-row dependencies in batches, returning a cursor when more remain.
     */
    public function validateDependencies(DataImport $import, int $afterId = 0): ?int;

    /**
     * Persist one validated row within the caller's transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function apply(array $data, DataImport $import, ?int $targetId): void;

    /**
     * Complete resource-specific work after application, returning a cursor when more remains.
     */
    public function afterImport(DataImport $import, int $afterId = 0): ?int;
}
