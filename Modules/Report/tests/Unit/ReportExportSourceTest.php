<?php

namespace Modules\Report\tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Mockery;
use Modules\Export\Enums\ExportFormat;
use Modules\Export\Models\Export;
use Modules\Export\Support\ExportValueNormalizer;
use Modules\Report\Contracts\ReportInterface;
use Modules\Report\Export\QueuedReportExport;
use Modules\Report\Export\ReportExportSource;
use Modules\Report\Report;
use Tests\TestCase;

class ReportExportSourceTest extends TestCase
{
    public function test_report_payload_selects_the_queued_export_workflow(): void
    {
        $payload = (new RenderableQueuedReport)->render(new Request);

        $this->assertSame('queued', $payload['export_mode']);
        $this->assertSame('/v1/exports/reports', $payload['export_api_uri']);
        $this->assertSame('reports', $payload['export_resource']);
        $this->assertSame(['report' => 'test_report'], $payload['export_context']);
        $this->assertSame('admin.reports.test_report', $payload['export_permission']);
    }

    public function test_spreadsheet_reports_are_stored_with_a_chunked_query_export(): void
    {
        Excel::fake();
        config()->set('export.disk', 'local');
        $query = Mockery::mock(Builder::class);
        $report = $this->report($query);
        $source = $this->source($report);

        $file = $source->generate($this->export(ExportFormat::Csv));

        Excel::assertStored(
            $file->path,
            'local',
            fn ($export) => $export instanceof QueuedReportExport
                && $export->query() === $query
                && $export->headings() === ['Identifier', 'Name']
        );
    }

    public function test_json_reports_are_written_incrementally_from_a_lazy_query(): void
    {
        Storage::fake('report-exports');
        config()->set('export.disk', 'report-exports');
        config()->set('excel.exports.chunk_size', 2);
        $rows = [
            new ReportExportRow(['id' => 1, 'name' => 'First']),
            new ReportExportRow(['id' => 2, 'name' => 'Second']),
        ];
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('lazy')
            ->once()
            ->with(2)
            ->andReturn(collect($rows)->lazy());
        $report = $this->report($query);
        $report->shouldReceive('resource')
            ->twice()
            ->andReturnUsing(fn (Model $row) => $row->only(['id', 'name']));
        $source = $this->source($report);

        $file = $source->generate($this->export(ExportFormat::Json));

        Storage::disk('report-exports')->assertExists($file->path);
        $this->assertSame(
            [
                ['id' => 1, 'name' => 'First'],
                ['id' => 2, 'name' => 'Second'],
            ],
            json_decode(Storage::disk('report-exports')->get($file->path), true, flags: JSON_THROW_ON_ERROR)
        );
    }

    private function report(Builder $query): ReportInterface
    {
        $report = Mockery::mock(ReportInterface::class);
        $report->shouldReceive('key')->andReturn('test_report');
        $report->shouldReceive('query')->with(Mockery::type(Request::class))->andReturn($query);
        $report->shouldReceive('transAttributes')->andReturn(['id' => 'Identifier', 'name' => 'Name']);
        $report->shouldReceive('attributes')->andReturn(new Collection(['id', 'name']));

        return $report;
    }

    private function source(ReportInterface $report): ReportExportSource
    {
        return new class(app(ExportValueNormalizer::class), $report) extends ReportExportSource
        {
            public function __construct(ExportValueNormalizer $normalizer, private ReportInterface $report)
            {
                parent::__construct($normalizer);
            }

            public function validateExport(array $filters = [], ?array $sorts = [], array $context = []): void {}

            protected function resolveReport(array $context): ReportInterface
            {
                return $this->report;
            }
        };
    }

    private function export(ExportFormat $format): Export
    {
        return new Export([
            'user_id' => 42,
            'resource_key' => 'reports',
            'context_key' => 'test-context',
            'format' => $format,
            'filters' => ['branch_id' => 7],
            'sorts' => [],
            'context' => ['report' => 'test_report'],
        ]);
    }
}

class ReportExportRow extends Model
{
    protected $guarded = [];
}

class RenderableQueuedReport extends Report
{
    public function __construct() {}

    public function key(): string
    {
        return 'test_report';
    }

    public function attributes(): Collection
    {
        return collect(['id']);
    }

    public function columns(): array
    {
        return ['id'];
    }

    public function model(): string
    {
        return ReportExportRow::class;
    }

    public function resource(Model $model): array
    {
        return ['id' => $model->getKey()];
    }

    public function through(Request $request): array
    {
        return [];
    }

    public function globalFilters(): array
    {
        return [];
    }

    public function data(Request $request): array
    {
        return ['data' => [], 'pagination' => []];
    }
}
