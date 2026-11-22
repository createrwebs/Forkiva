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
use Modules\Import\Support\ImportContinuation;
use Modules\Import\Support\ImportDefinitionResolver;
use RuntimeException;
use Throwable;

class ApplyImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(private readonly int $importId, private int $revision, private int $afterId = 0) {}

    /**
     * Apply validated rows one transaction at a time and retain per-row failures.
     */
    public function handle(ImportDefinitionResolver $resolver): void
    {
        $import = DataImport::query()
            ->with('createdBy')
            ->find($this->importId);
        $expectedStatus = $this->afterId === 0 ? ImportStatus::Queued : ImportStatus::Processing;
        if (! $import || ! $import->isAtRevision($expectedStatus, $this->revision) || ! $import->createdBy) {
            return;
        }
        if ($this->afterId === 0 && DataImport::query()
            ->whereKey($import->id)
            ->where('status', ImportStatus::Queued->value)
            ->where('revision', $this->revision)
            ->update(['status' => ImportStatus::Processing, 'started_at' => now(), 'failure_reason' => null]) !== 1) {
            return;
        }
        Auth::setUser($import->createdBy);
        $previousLocale = locale();
        app()->setLocale($import->locale);
        $import->refresh();

        try {
            DB::transaction(function () use ($import, $resolver): void {
                $import = DataImport::query()->lockForUpdate()->findOrFail($import->id);
                if (! $import->isAtRevision(ImportStatus::Processing, $this->revision)) {
                    return;
                }
                $definition = $resolver->resolve($import->resource_key);
                $definition->authorize($import);
                $import->rows()
                    ->where('status', ImportRowStatus::Valid->value)
                    ->where('operation', ImportOperation::Skip->value)
                    ->update(['status' => ImportRowStatus::Skipped]);
                // A terminated worker may have committed rows before saving the batch counters.
                $counts = $import->rows()
                    ->select('status')
                    ->selectRaw('COUNT(*) AS total')
                    ->groupBy('status')
                    ->pluck('total', 'status');
                $imported = (int) ($counts[ImportRowStatus::Processed->value] ?? 0);
                $failed = (int) ($counts[ImportRowStatus::Failed->value] ?? 0);
                $skipped = (int) ($counts[ImportRowStatus::Skipped->value] ?? 0);

                $rows = $import->rows()
                    ->where('id', '>', $this->afterId)
                    ->whereIn('status', [ImportRowStatus::Valid->value, ImportRowStatus::Failed->value])
                    ->whereIn('operation', [ImportOperation::Create->value, ImportOperation::Update->value])
                    ->orderBy('id')
                    ->limit(max(1, (int) config('import.apply_batch_size', 100)))
                    ->get();
                foreach ($rows as $row) {
                    if ($row->status === ImportRowStatus::Failed) {
                        $failed--;
                    }
                    try {
                        $outcome = DB::transaction(function () use ($row, $import, $definition): ImportRowStatus {
                            $result = $definition->validate($row->data, $import);
                            if ($result['errors'] !== [] || $result['operation'] === ImportOperation::Invalid) {
                                throw new RuntimeException(__('import::imports.messages.changed_row'));
                            }
                            if ($result['operation'] === ImportOperation::Skip) {
                                $row->update(['status' => ImportRowStatus::Skipped, 'errors' => null]);

                                return ImportRowStatus::Skipped;
                            }
                            $definition->apply($result['data'], $import, $result['target_id']);
                            $row->update(['status' => ImportRowStatus::Processed, 'errors' => null]);

                            return ImportRowStatus::Processed;
                        });
                        if ($outcome === ImportRowStatus::Skipped) {
                            $skipped++;
                        } else {
                            $imported++;
                        }
                    } catch (Throwable $exception) {
                        // A row failure is recorded so the remaining valid rows can still be applied.
                        $reason = app()->isProduction()
                            ? __('import::imports.messages.failed_row')
                            : $exception->getMessage();
                        $row->update([
                            'status' => ImportRowStatus::Failed,
                            'errors' => ['_row' => [$reason]],
                        ]);
                        $failed++;
                    }
                }
                $import->update([
                    'processed_rows' => $imported + $failed + $skipped,
                    'imported_rows' => $imported,
                    'failed_rows' => $failed,
                    'skipped_rows' => $skipped,
                ]);
                $lastId = $rows->last()?->id ?? $this->afterId;
                if ($import->rows()
                    ->where('id', '>', $lastId)
                    ->whereIn('status', [ImportRowStatus::Valid->value, ImportRowStatus::Failed->value])
                    ->whereIn('operation', [ImportOperation::Create->value, ImportOperation::Update->value])
                    ->exists()) {
                    ImportContinuation::schedule($import, 'apply', $lastId);

                    return;
                }

                ImportContinuation::schedule($import, 'finalize');
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
                'failure_reason' => __('import::imports.messages.failed_processing'),
                'finished_at' => now(),
            ]);
    }
}
