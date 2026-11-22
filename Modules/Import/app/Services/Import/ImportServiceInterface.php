<?php

namespace Modules\Import\Services\Import;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Models\DataImport;
use Modules\Import\Models\ImportRow;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

interface ImportServiceInterface
{
    /**
     * List fields available to the authorized actor for a resource.
     *
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    public function fields(string $resource, array $context = []): array;

    /**
     * Download a template containing the resource's ordered field keys.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    public function template(string $resource): BinaryFileResponse;

    /**
     * Store an upload after resolving its authorized resource context.
     *
     * @param  array<string, mixed>  $context
     */
    public function upload(string $resource, UploadedFile $file, array $context): DataImport;

    /**
     * Find an import owned by the current actor and recheck resource access.
     */
    public function find(int $id): DataImport;

    /**
     * Paginate the current actor's imports within an authorized resource context.
     *
     * @param  array<string, mixed>  $context
     */
    public function paginate(string $resource, array $context): LengthAwarePaginator;

    /**
     * Paginate rows only after confirming the actor still owns the import.
     */
    public function rows(int $id, int $perPage): LengthAwarePaginator;

    /**
     * Replace the mapping under lock and invalidate jobs from earlier revisions.
     *
     * @param  array<string, int>  $mapping
     */
    public function map(DataImport $import, array $mapping): DataImport;

    /**
     * Correct a staged row and clear validation results tied to its old values.
     *
     * @param  array<string, string|null>  $data
     *
     * @throws Throwable
     */
    public function editRow(DataImport $import, int $rowId, array $data): ImportRow;

    /**
     * Queue validation for the staged revision using the selected import mode.
     */
    public function validate(DataImport $import, ImportMode $mode): DataImport;

    /**
     * Queue application only after every staged row has passed validation.
     *
     * @throws Throwable
     */
    public function queue(DataImport $import): DataImport;

    /**
     * Resume at staging, validation, or application according to persisted progress.
     */
    public function retry(DataImport $import): DataImport;

    /**
     * Cancel an import that has not begun applying its rows.
     */
    public function cancel(DataImport $import): DataImport;

    /**
     * Execute one persisted synchronous batch after ownership and resource authorization.
     */
    public function advance(DataImport $import): DataImport;

    /**
     * Download the actor's original file or annotated validation workbook.
     */
    public function download(DataImport $import, bool $errors = false): StreamedResponse;
}
