<?php

namespace Modules\Export\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Export\Contracts\GeneratesExportFilesInterface;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Export\Enums\ExportFormat;
use Modules\Export\Enums\ExportStatus;
use Modules\Export\Events\ExportCompleted;
use Modules\Export\Events\ExportFailed;
use Modules\Export\Exports\QueuedModelExport;
use Modules\Export\Models\Export;
use Modules\Export\Support\ExportSourceResolver;
use Modules\Export\Support\ExportValueNormalizer;
use Modules\Export\Support\GeneratedExportFile;
use Throwable;

class GenerateExportJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Export $export) {}

    /**
     * Execute the job.
     *
     * @throws Throwable
     */
    public function handle(ExportSourceResolver $resolver, ExportValueNormalizer $normalizer): void
    {
        $export = $this->export->fresh();
        if (! $export || ! $export->user) {
            return;
        }

        Auth::setUser($export->user);
        $export->update([
            'status' => ExportStatus::Processing,
            'started_at' => now(),
            'error_message' => null,
        ]);

        try {
            $source = $resolver->resolve($export->resource_key);
            $generatedFile = $source instanceof GeneratesExportFilesInterface
                ? $this->generateCustomExport($source, $export)
                : $this->generateModelExport($source, $export, $normalizer);

            $export->update([
                'status' => ExportStatus::Completed,
                'disk' => $generatedFile->disk,
                'path' => $generatedFile->path,
                'file_name' => $generatedFile->fileName,
                'finished_at' => now(),
                'expires_at' => now()->addDays((int) config('export.retention_days', 7)),
            ]);

            event(new ExportCompleted($export->fresh()));
        } catch (Throwable $exception) {
            $export->update([
                'status' => ExportStatus::Failed,
                'finished_at' => now(),
                'error_message' => app()->isProduction()
                    ? __('export::exports.messages.failed_generic')
                    : $exception->getMessage(),
            ]);

            event(new ExportFailed($export->fresh()));

            throw $exception;
        } finally {
            Auth::forgetGuards();
        }
    }

    /**
     * Generate an export through a source-owned, memory-bounded writer.
     */
    private function generateCustomExport(GeneratesExportFilesInterface $source, Export $export): GeneratedExportFile
    {
        $source->validateExport(
            filters: $export->filters ?: [],
            sorts: $export->sorts ?: [],
            context: $export->context ?: [],
        );

        return $source->generate($export);
    }

    /**
     * Generate a standard model export in query chunks.
     */
    private function generateModelExport(
        mixed $source,
        Export $export,
        ExportValueNormalizer $normalizer,
    ): GeneratedExportFile {
        abort_unless($source instanceof TableExportSourceInterface, 500, 'Invalid export source definition.');

        $modelClass = $source->exportModelClass();

        abort_unless(is_a($modelClass, ModelExportDefinitionInterface::class, true), 500, 'Invalid export model definition.');

        $query = $source->exportQuery(
            filters: $export->filters ?: [],
            sorts: $export->sorts ?: [],
            context: $export->context ?: [],
        );

        $queryModelClass = $query->getModel()::class;

        if (method_exists($queryModelClass, 'clearBootedModels')) {
            $queryModelClass::clearBootedModels();
        }

        $columns = $modelClass::exportColumns();
        $with = collect($columns)
            ->pluck('with')
            ->flatten(1)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $withCount = collect($columns)
            ->pluck('with_count')
            ->filter()
            ->flatMap(fn ($relations) => is_array($relations) ? $relations : [$relations])
            ->unique()
            ->values()
            ->all();
        $withSum = collect($columns)
            ->pluck('with_sum')
            ->filter()
            ->flatMap(function ($definitions) {
                if (isset($definitions['relation'], $definitions['column'])) {
                    return [$definitions];
                }

                return is_array($definitions) ? $definitions : [];
            })
            ->filter(fn ($definition) => is_array($definition) && isset($definition['relation'], $definition['column']))
            ->unique(fn (array $definition) => $definition['relation'].'|'.$definition['column'])
            ->values()
            ->all();
        $query->with($with);

        if (! empty($withCount)) {
            $query->withCount($withCount);
        }

        foreach ($withSum as $definition) {
            $query->withSum($definition['relation'], $definition['column']);
        }

        $disk = (string) config('export.disk', config('filesystems.default_private', 'local'));
        $directory = trim((string) config('export.path', 'exports'), '/');
        $fileName = $this->buildFileName($export);
        $path = "$directory/$export->user_id/$export->resource_key/$fileName";

        Excel::store(
            new QueuedModelExport($query, $columns, $normalizer),
            $path,
            $disk,
            $this->writerType($export->format),
        );

        return new GeneratedExportFile($disk, $path, $fileName);
    }

    /**
     * Build the stored export filename.
     */
    private function buildFileName(Export $export): string
    {
        return "{$export->resource_key}-".now()->format('Y-m-d_H-i-s').".{$export->format->value}";
    }

    /**
     * Resolve the writer type for maatwebsite/excel.
     */
    private function writerType(ExportFormat $type): string
    {
        return match ($type) {
            ExportFormat::Csv => \Maatwebsite\Excel\Excel::CSV,
            ExportFormat::Xls => \Maatwebsite\Excel\Excel::XLS,
            ExportFormat::Xlsx => \Maatwebsite\Excel\Excel::XLSX,
            ExportFormat::Json => throw new LogicException('JSON exports require a source-owned generator.'),
        };
    }
}
