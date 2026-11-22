<?php

namespace Modules\Import\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Import\Enums\ImportStatus;
use Modules\Import\Models\DataImport;
use Modules\Import\Support\ImportContinuation;
use Modules\Import\Support\ImportDefinitionResolver;
use Throwable;

class FinalizeImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(private readonly int $importId, private readonly int $revision, private readonly int $afterId = 0) {}

    /**
     * Finish resource-specific work only after every applicable row is processed.
     */
    public function handle(ImportDefinitionResolver $resolver): void
    {
        $import = DataImport::query()
            ->with('createdBy')
            ->find($this->importId);
        if (! $import || ! $import->isAtRevision(ImportStatus::Processing, $this->revision) || ! $import->createdBy) {
            return;
        }
        Auth::setUser($import->createdBy);
        $previousLocale = locale();
        app()->setLocale($import->locale);

        try {
            DB::transaction(function () use ($import, $resolver): void {
                $import = DataImport::query()->lockForUpdate()->findOrFail($import->id);
                if (! $import->isAtRevision(ImportStatus::Processing, $this->revision)) {
                    return;
                }
                $definition = $resolver->resolve($import->resource_key);
                $definition->authorize($import);
                $nextId = $definition->afterImport($import, $this->afterId);
                if ($nextId !== null) {
                    ImportContinuation::schedule($import, 'finalize', $nextId);

                    return;
                }
                $import->update([
                    'status' => $import->failed_rows > 0 ? ImportStatus::PartiallyCompleted : ImportStatus::Completed,
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
     * Persist queue timeout failures without changing a newer import attempt.
     */
    public function failed(?Throwable $exception): void
    {
        DataImport::query()
            ->whereKey($this->importId)
            ->where('revision', $this->revision)
            ->whereIn('status', [ImportStatus::Queued->value, ImportStatus::Processing->value])
            ->update([
                'status' => ImportStatus::Failed,
                'failure_reason' => __('import::imports.messages.failed_finalization'),
                'finished_at' => now(),
            ]);
    }
}
