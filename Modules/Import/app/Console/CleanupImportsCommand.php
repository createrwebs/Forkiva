<?php

namespace Modules\Import\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Import\Enums\ImportStatus;
use Modules\Import\Models\DataImport;

class CleanupImportsCommand extends Command
{
    protected $signature = 'imports:cleanup';

    protected $description = 'Remove expired import files and preview rows while retaining history';

    /**
     * Remove files only after terminal imports exceed the retention window.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('import.retention_days', 30));
        DataImport::query()
            ->whereIn('status', [
                ImportStatus::ValidationFailed->value,
                ImportStatus::Completed->value,
                ImportStatus::PartiallyCompleted->value,
                ImportStatus::Failed->value,
                ImportStatus::Cancelled->value,
            ])
            ->where('finished_at', '<', $cutoff)
            ->where(function ($query): void {
                $query->where('path', '!=', '')
                    ->orWhereNotNull('error_path');
            })
            ->orderBy('id')
            ->chunkById(100, function ($imports): void {
                foreach ($imports as $import) {
                    $disk = Storage::disk($import->disk);

                    if ($import->path) {
                        $disk->delete($import->path);
                    }

                    if ($import->error_path) {
                        $disk->delete($import->error_path);
                    }

                    $import->rows()->delete();
                    $import->update(['path' => '', 'error_path' => null]);
                }
            });

        return self::SUCCESS;
    }
}
