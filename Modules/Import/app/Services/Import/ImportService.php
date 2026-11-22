<?php

namespace Modules\Import\Services\Import;

use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Import\Contracts\ContextualImportFieldsInterface;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportRowStatus;
use Modules\Import\Enums\ImportStatus;
use Modules\Import\Jobs\StageImportJob;
use Modules\Import\Models\DataImport;
use Modules\Import\Models\ImportRow;
use Modules\Import\Support\ImportContinuation;
use Modules\Import\Support\ImportDefinitionResolver;
use Modules\Import\Support\ImportFileReader;
use Modules\Import\Support\ImportTemplate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

readonly class ImportService implements ImportServiceInterface
{
    public function __construct(
        private ImportDefinitionResolver $resolver,
        private ImportFileReader $reader
    ) {}

    /** {@inheritDoc} */
    public function template(string $resource): BinaryFileResponse
    {
        return Excel::download(new ImportTemplate($this->fields($resource)), "$resource-template.xlsx");
    }

    /** {@inheritDoc} */
    public function download(DataImport $import, bool $errors = false): StreamedResponse
    {
        $path = $errors ? $import->error_path : $import->path;
        abort_unless($path && Storage::disk($import->disk)->exists($path), 404);

        $filename = $errors
            ? 'validation-errors-'.pathinfo($import->original_name, PATHINFO_FILENAME).'.xlsx'
            : $import->original_name;

        return Storage::disk($import->disk)->download($path, $filename);
    }

    /** {@inheritDoc} */
    public function fields(string $resource, array $context = []): array
    {
        $definition = $this->resolver->resolve($resource);
        $definition->authorize();

        return $context !== [] && $definition instanceof ContextualImportFieldsInterface
            ? $definition->fieldsForContext($context)
            : $definition->fields();
    }

    /** {@inheritDoc} */
    public function upload(string $resource, UploadedFile $file, array $context): DataImport
    {
        $definition = $this->resolver->resolve($resource);
        $definition->authorize();
        $context = $definition->resolveContext($context);
        $draft = new DataImport(['resource_key' => $resource, 'context' => $context]);
        $definition->authorize($draft);

        try {
            $headers = $this->reader->headers($file->getRealPath());
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => __('import::imports.messages.unreadable_file'),
            ]);
        }

        $disk = (string) config('import.disk', 'local');
        $path = trim((string) config('import.path', 'imports'), '/')
            .'/'.auth()->id().'/'.Str::uuid().'.'.$file->getClientOriginalExtension();
        abort_unless(
            Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path)),
            500,
        );

        $mapping = [];
        foreach ($definition->fields() as $field) {
            foreach ($headers as $index => $header) {
                if ($this->normalizeHeader($header) === $this->normalizeHeader($field['key'])) {
                    $mapping[$field['key']] = $index;
                    break;
                }
            }
        }

        try {
            return DataImport::query()
                ->create([
                    'resource_key' => $resource,
                    'locale' => locale(),
                    'status' => ImportStatus::Uploaded,
                    'mode' => ImportMode::CreateOnly,
                    'original_name' => basename($file->getClientOriginalName()),
                    'disk' => $disk,
                    'path' => $path,
                    'headers' => $headers,
                    'mapping' => $mapping,
                    'context' => $context,
                ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    private function normalizeHeader(string $header): string
    {
        return Str::snake(mb_strtolower(trim($header)));
    }

    /** {@inheritDoc} */
    public function editRow(DataImport $import, int $rowId, array $data): ImportRow
    {
        return DB::transaction(function () use ($import, $rowId, $data): ImportRow {
            $locked = DataImport::query()
                ->lockForUpdate()
                ->findOrFail($import->id);
            abort_unless($locked->status->allowsValidation(), 409);
            $row = $locked->rows()
                ->findOrFail($rowId);
            $allowed = array_keys($locked->mapping);
            abort_unless(array_diff(array_keys($data), $allowed) === [], 422);
            $updated = array_replace($row->data, $data);
            $values = $row->values;
            foreach ($data as $field => $value) {
                $values[$locked->mapping[$field]] = $value;
            }
            $row->update([
                'values' => $values,
                'data' => $updated,
                'record_key' => $this->resolver->resolve($locked->resource_key)->key($updated, $locked),
                'errors' => null,
                'operation' => null,
                'target_id' => null,
                'status' => ImportRowStatus::Pending,
            ]);
            $locked->update([
                'status' => ImportStatus::Staged,
                'error_path' => null,
                'processed_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'new_rows' => 0,
                'update_rows' => 0,
                'skipped_rows' => 0,
            ]);

            return $row->refresh();
        });
    }

    /** {@inheritDoc} */
    public function rows(int $id, int $perPage): LengthAwarePaginator
    {
        $import = $this->find($id);

        return $import->rows()
            ->orderBy('id')
            ->paginate($perPage);
    }

    /** {@inheritDoc} */
    public function find(int $id): DataImport
    {
        $import = DataImport::query()
            ->whereCreatedBy(auth()->id())
            ->findOrFail($id);
        $this->resolver->resolve($import->resource_key)->authorize($import);

        return $import;
    }

    /** {@inheritDoc} */
    public function paginate(string $resource, array $context): LengthAwarePaginator
    {
        $definition = $this->resolver->resolve($resource);
        $definition->authorize();
        $context = $definition->resolveContext($context);

        $query = DataImport::query()
            ->whereCreatedBy(auth()->id())
            ->where('resource_key', $resource);

        foreach ($context as $key => $value) {
            if ($value !== null) {
                $query->where("context->$key", $value);
            }
        }

        return $query->latest('id')
            ->paginate(15);
    }

    /** {@inheritDoc} */
    public function queue(DataImport $import): DataImport
    {
        DB::transaction(function () use ($import): void {
            $locked = DataImport::query()
                ->lockForUpdate()
                ->findOrFail($import->id);
            abort_unless($locked->status === ImportStatus::Ready && $locked->invalid_rows === 0, 409);
            $locked->update([
                'status' => ImportStatus::Queued,
                'processed_rows' => 0,
                'client_processing' => ImportContinuation::requested(),
                'continuation' => null,
            ]);
            ImportContinuation::schedule($locked, 'apply');
        });

        return $import->refresh();
    }

    /** {@inheritDoc} */
    public function retry(DataImport $import): DataImport
    {
        abort_unless($import->status->allowsRetry() && $import->path !== '', 409);
        if ($import->total_rows === 0) {
            return $this->map($import, $import->mapping);
        }
        if ($import->started_at === null || ($import->imported_rows === 0 &&
            ($import->valid_rows + $import->invalid_rows !== $import->total_rows || $import->invalid_rows > 0))) {
            $changed = DataImport::query()
                ->whereKey($import->id)
                ->whereIn('status', [ImportStatus::Failed->value, ImportStatus::PartiallyCompleted->value])
                ->update(['status' => ImportStatus::Staged]);
            abort_unless($changed === 1, 409);

            return $this->validate($import, $import->mode);
        }
        DB::transaction(function () use ($import): void {
            $locked = DataImport::query()->lockForUpdate()->findOrFail($import->id);
            abort_unless($locked->status->allowsRetry(), 409);
            $locked->update([
                'revision' => $locked->revision + 1,
                'status' => ImportStatus::Queued,
                'failed_rows' => 0,
                'failure_reason' => null,
                'client_processing' => ImportContinuation::requested(),
                'continuation' => null,
            ]);
            ImportContinuation::schedule($locked, 'apply');
        });

        return $import->refresh();
    }

    /** {@inheritDoc} */
    public function map(DataImport $import, array $mapping): DataImport
    {
        abort_unless($import->status->allowsMapping(), 409);

        $fields = collect($this->fields($import->resource_key))
            ->keyBy('key');
        $usedColumns = [];
        foreach ($mapping as $field => $column) {
            abort_unless(
                $fields->has($field) && isset($import->headers[$column]) && ! in_array($column, $usedColumns, true),
                422,
            );
            $usedColumns[] = $column;
        }
        foreach ($fields as $field) {
            abort_if(
                $field['required'] && ! array_key_exists($field['key'], $mapping),
                422,
                __('import::imports.messages.mapping_required', ['field' => $field['label']]),
            );
        }

        DB::transaction(function () use ($import, $mapping): void {
            $locked = DataImport::query()
                ->lockForUpdate()
                ->findOrFail($import->id);
            abort_unless($locked->status->allowsMapping(), 409);
            abort_unless((int) $locked->imported_rows === 0, 409);
            $locked->update([
                'client_processing' => false,
                'continuation' => null,
                'started_at' => null,
                'finished_at' => null,
                'mapping' => $mapping,
                'revision' => $locked->revision + 1,
                'status' => ImportStatus::Staging,
                'total_rows' => 0,
                'processed_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'new_rows' => 0,
                'update_rows' => 0,
                'skipped_rows' => 0,
                'imported_rows' => 0,
                'failed_rows' => 0,
                'error_path' => null,
                'failure_reason' => null,
            ]);
            StageImportJob::dispatch($locked->id, $locked->revision)->afterCommit();
        });

        return $import->refresh();
    }

    /** {@inheritDoc} */
    public function validate(DataImport $import, ImportMode $mode): DataImport
    {
        DB::transaction(function () use ($import, $mode): void {
            $locked = DataImport::query()
                ->lockForUpdate()
                ->findOrFail($import->id);
            abort_unless($locked->status->allowsValidation(), 409);
            abort_unless($locked->total_rows > 0, 422, __('import::imports.messages.empty_file'));
            $locked->update([
                'client_processing' => ImportContinuation::requested(),
                'continuation' => null,
                'mode' => $mode,
                'revision' => $locked->revision + 1,
                'status' => ImportStatus::Validating,
                'processed_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'new_rows' => 0,
                'update_rows' => 0,
                'skipped_rows' => 0,
                'error_path' => null,
                'failure_reason' => null,
            ]);
            ImportContinuation::schedule($locked, 'validate');
        });

        return $import->refresh();
    }

    /** {@inheritDoc} */
    public function advance(DataImport $import): DataImport
    {
        $job = null;
        try {
            DB::transaction(function () use ($import, &$job): void {
                $locked = DataImport::query()->lockForUpdate()->findOrFail($import->id);
                if (! $locked->client_processing || ! $locked->continuation || ! in_array($locked->status, [
                    ImportStatus::Validating, ImportStatus::Queued, ImportStatus::Processing,
                ], true)) {
                    return;
                }
                $job = ImportContinuation::job($locked, $locked->continuation);
                $locked->update(['continuation' => null]);
                // A disconnect or terminated request rolls back this batch and preserves its cursor.
                Bus::dispatchSync($job);
            });
        } catch (Throwable $exception) {
            $job?->failed($exception);
            throw $exception;
        }

        return $import->refresh();
    }

    /** {@inheritDoc} */
    public function cancel(DataImport $import): DataImport
    {
        $changed = DataImport::query()
            ->whereKey($import->id)
            ->whereIn('status', [
                ImportStatus::Uploaded->value,
                ImportStatus::Staged->value,
                ImportStatus::ValidationFailed->value,
                ImportStatus::Ready->value,
                ImportStatus::Queued->value,
            ])
            ->update(['status' => ImportStatus::Cancelled, 'continuation' => null, 'finished_at' => now()]);
        abort_unless($changed === 1, 409);

        return $import->refresh();
    }
}
