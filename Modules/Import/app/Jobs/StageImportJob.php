<?php

namespace Modules\Import\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Import\Enums\ImportStatus;
use Modules\Import\Models\DataImport;
use Modules\Import\Support\ImportDefinitionResolver;
use Modules\Import\Support\QueuedStageRows;
use Modules\Import\Support\StageRows;
use Throwable;

class StageImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(private int $importId, private int $revision) {}

    /**
     * Replace staged rows only for the current revision before queuing Excel chunks.
     */
    public function handle(ImportDefinitionResolver $resolver): void
    {
        $import = DataImport::query()
            ->with('createdBy')
            ->find($this->importId);
        if (! $import || ! $import->isAtRevision(ImportStatus::Staging, $this->revision) || ! $import->createdBy) {
            return;
        }
        Auth::setUser($import->createdBy);
        $previousLocale = locale();
        app()->setLocale($import->locale);

        try {
            $definition = $resolver->resolve($import->resource_key);
            $definition->authorize($import);
            $prepared = DB::transaction(function () use ($import): bool {
                $locked = DataImport::query()
                    ->lockForUpdate()
                    ->findOrFail($import->id);
                if (! $locked->isAtRevision(ImportStatus::Staging, $this->revision)) {
                    return false;
                }
                $locked->rows()->delete();
                $locked->update(['processed_rows' => 0, 'total_rows' => 0]);

                return true;
            });
            if (! $prepared) {
                return;
            }
            // The XLS reader contains binary state and cannot be serialized into JSON queue payloads.
            if (strtolower(pathinfo($import->path, PATHINFO_EXTENSION)) === 'xls') {
                Excel::import(new StageRows($import->id, $import->revision), $import->path, $import->disk);
            } else {
                Excel::queueImport(new QueuedStageRows($import->id, $import->revision), $import->path, $import->disk);
            }
        } catch (Throwable $exception) {
            $reason = app()->isProduction()
                ? __('import::imports.messages.failed_staging')
                : $exception->getMessage();
            DataImport::query()
                ->whereKey($import->id)
                ->where('revision', $this->revision)
                ->where('status', ImportStatus::Staging->value)
                ->update([
                    'status' => ImportStatus::Failed,
                    'failure_reason' => $reason,
                    'finished_at' => now(),
                ]);
            throw $exception;
        } finally {
            Auth::forgetGuards();
            app()->setLocale($previousLocale);
        }
    }
}
