<?php

namespace Modules\Report\Export;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use JsonException;
use LogicException;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Export\Contracts\GeneratesExportFilesInterface;
use Modules\Export\Enums\ExportFormat;
use Modules\Export\Models\Export;
use Modules\Export\Support\ExportValueNormalizer;
use Modules\Export\Support\GeneratedExportFile;
use Modules\Report\Contracts\ReportInterface;
use Modules\Report\ReportManager;
use RuntimeException;

class ReportExportSource implements GeneratesExportFilesInterface
{
    /**
     * Create a report export source.
     */
    public function __construct(protected ExportValueNormalizer $normalizer) {}

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'reports';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.reports.index';
    }

    /**
     * Display label used by export notifications.
     */
    public function label(): string
    {
        return __('report::reports.reports');
    }

    /** {@inheritDoc} */
    public function validateExport(array $filters = [], ?array $sorts = [], array $context = []): void
    {
        $report = $this->resolveReport($context);

        abort_unless(
            auth()->user()?->can($report->permission()),
            403,
            __('admin::messages.action_unauthorized')
        );
    }

    /** {@inheritDoc}
     *
     * @throws JsonException
     */
    public function generate(Export $export): GeneratedExportFile
    {
        $context = $export->context ?: [];
        $report = $this->resolveReport($context);
        $request = new Request([
            'filters' => $export->filters ?: [],
            'sorts' => $export->sorts ?: [],
        ]);
        $query = $report->query($request);
        $disk = (string) config('export.disk', config('filesystems.default_private', 'local'));
        $directory = trim((string) config('export.path', 'exports'), '/');
        $fileName = $report->key().'-'.now()->format('Y-m-d_H-i-s').'.'.$export->format->value;
        $path = "$directory/$export->user_id/{$this->exportResourceKey()}/{$report->key()}/$fileName";

        if ($export->format === ExportFormat::Json) {
            $this->storeJson($report, $query, $disk, $path);
        } else {
            Excel::store(
                new QueuedReportExport($report, $query, $this->normalizer),
                $path,
                $disk,
                $this->writerType($export->format),
            );
        }

        return new GeneratedExportFile($disk, $path, $fileName);
    }

    /**
     * Resolve and validate the requested report definition.
     */
    protected function resolveReport(array $context): ReportInterface
    {
        $key = (string) ($context['report'] ?? '');
        $manager = ReportManager::getInstance();

        abort_unless(
            $key !== '' && $manager->reportExists($key),
            404,
            __('report::messages.report_not_exists', ['report' => $key])
        );

        return $manager->registeredReports($key);
    }

    /**
     * Store JSON one row at a time so memory use stays bounded.
     *
     * @throws JsonException
     */
    protected function storeJson(ReportInterface $report, Builder $query, string $disk, string $path): void
    {
        $stream = tmpfile();
        if ($stream === false) {
            throw new RuntimeException('Unable to create a temporary report export stream.');
        }

        try {
            fwrite($stream, '[');
            $first = true;
            $chunkSize = max(1, (int) config('excel.exports.chunk_size', 1000));

            foreach ($query->lazy($chunkSize) as $row) {
                fwrite($stream, $first ? '' : ',');
                fwrite($stream, json_encode($report->resource($row), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $first = false;
            }

            fwrite($stream, ']');
            rewind($stream);

            if (! Storage::disk($disk)->writeStream($path, $stream)) {
                throw new RuntimeException('Unable to store the generated JSON report.');
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * Resolve the spreadsheet writer type.
     */
    protected function writerType(ExportFormat $format): string
    {
        return match ($format) {
            ExportFormat::Csv => \Maatwebsite\Excel\Excel::CSV,
            ExportFormat::Xls => \Maatwebsite\Excel\Excel::XLS,
            ExportFormat::Xlsx => \Maatwebsite\Excel\Excel::XLSX,
            ExportFormat::Json => throw new LogicException('JSON reports use the streaming writer.'),
        };
    }
}
