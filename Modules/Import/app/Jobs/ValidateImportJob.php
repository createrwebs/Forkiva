<?php

namespace Modules\Import\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Enums\ImportRowStatus;
use Modules\Import\Enums\ImportStatus;
use Modules\Import\Models\DataImport;
use Modules\Import\Support\ErrorWorkbookWriter;
use Modules\Import\Support\ImportContinuation;
use Modules\Import\Support\ImportDefinitionResolver;
use Throwable;

class ValidateImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        private int $importId,
        private int $revision,
        private int $afterId = 0,
        private bool $dependencies = false
    ) {}

    /**
     * Validate rows and their cross-row dependencies in bounded queue jobs.
     */
    public function handle(ImportDefinitionResolver $resolver, ErrorWorkbookWriter $writer): void
    {
        $import = DataImport::query()
            ->with('createdBy')
            ->find($this->importId);
        if (! $import || ! $import->isAtRevision(ImportStatus::Validating, $this->revision) || ! $import->createdBy) {
            return;
        }
        Auth::setUser($import->createdBy);
        $previousLocale = locale();
        app()->setLocale($import->locale);

        try {
            DB::transaction(function () use ($import, $resolver, $writer): void {
                $import = DataImport::query()->lockForUpdate()->findOrFail($import->id);
                if (! $import->isAtRevision(ImportStatus::Validating, $this->revision)) {
                    return;
                }
                // The row cursor and counters commit together; a replay cannot count this batch twice.
                $expected = $this->dependencies
                    ? $import->total_rows
                    : $import->rows()->where('id', '<=', $this->afterId)->count();
                if ($import->processed_rows !== $expected) {
                    return;
                }
                $definition = $resolver->resolve($import->resource_key);
                $definition->authorize($import);
                $counts = [
                    'valid_rows' => (int) $import->valid_rows,
                    'invalid_rows' => (int) $import->invalid_rows,
                    'new_rows' => (int) $import->new_rows,
                    'update_rows' => (int) $import->update_rows,
                    'skipped_rows' => (int) $import->skipped_rows,
                ];
                if ($this->dependencies) {
                    $nextId = $definition->validateDependencies($import, $this->afterId);
                    if ($nextId !== null) {
                        ImportContinuation::schedule($import, 'validate', $nextId, true);

                        return;
                    }
                } else {
                    $rows = $import->rows()
                        ->where('id', '>', $this->afterId)
                        ->orderBy('id')
                        ->limit(max(1, (int) config('import.validation_batch_size', 200)))
                        ->get();
                    foreach ($rows as $row) {
                        $result = $definition->validate($row->data, $import);
                        $key = $definition->key($result['data'], $import);
                        if ($key !== null && $import->rows()
                            ->where('record_key', $key)
                            ->where('id', '<', $row->id)
                            ->exists()) {
                            $result['errors']['_row'] = [__('import::imports.messages.duplicate_row')];
                        }
                        if ($result['target_id'] !== null && $import->rows()
                            ->where('id', '<', $row->id)
                            ->where('target_id', $result['target_id'])
                            ->exists()) {
                            $result['errors']['_row'] = [__('import::imports.messages.duplicate_target')];
                        }
                        $invalid = $result['errors'] !== [];
                        $row->update([
                            'data' => $result['data'],
                            'record_key' => $key,
                            'errors' => $result['errors'],
                            'operation' => $invalid ? ImportOperation::Invalid : $result['operation'],
                            'target_id' => $result['target_id'],
                            'status' => $invalid ? ImportRowStatus::Invalid : ImportRowStatus::Valid,
                        ]);
                        $counts[$invalid ? 'invalid_rows' : 'valid_rows']++;
                        if (! $invalid) {
                            $counter = match ($result['operation']) {
                                ImportOperation::Create => 'new_rows',
                                ImportOperation::Update => 'update_rows',
                                default => 'skipped_rows',
                            };
                            $counts[$counter]++;
                        }
                    }
                    $processed = (int) $import->processed_rows + $rows->count();
                    $import->update($counts + ['processed_rows' => $processed]);
                    $lastId = $rows->last()?->id ?? $this->afterId;
                    if ($import->rows()
                        ->where('id', '>', $lastId)
                        ->exists()) {
                        ImportContinuation::schedule($import, 'validate', $lastId);

                        return;
                    }
                    $nextId = $definition->validateDependencies($import);
                    if ($nextId !== null) {
                        ImportContinuation::schedule($import, 'validate', $nextId, true);

                        return;
                    }
                }

                $import->refresh();
                $errorPath = $import->invalid_rows > 0 ? $writer->write($import) : null;
                $import->update([
                    'status' => $import->invalid_rows > 0 ? ImportStatus::ValidationFailed : ImportStatus::Ready,
                    'error_path' => $errorPath,
                    'finished_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $this->failed($exception);
            throw $exception;
        } finally {
            Auth::forgetGuards();
            app()->setLocale($previousLocale);
        }
    }

    /**
     * Queue timeouts must leave a retryable state without overwriting a newer validation run.
     */
    public function failed(?Throwable $exception): void
    {
        DataImport::query()
            ->whereKey($this->importId)
            ->where('revision', $this->revision)
            ->where('status', ImportStatus::Validating->value)
            ->update([
                'status' => ImportStatus::Failed,
                'failure_reason' => __('import::imports.messages.failed_validation'),
                'finished_at' => now(),
            ]);
    }
}
