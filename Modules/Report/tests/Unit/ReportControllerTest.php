<?php

namespace Modules\Report\tests\Unit;

use Illuminate\Http\Request;
use Mockery;
use Modules\Export\Enums\ExportFormat;
use Modules\Export\Enums\ExportStatus;
use Modules\Export\Models\Export;
use Modules\Report\Enums\ExportMethod;
use Modules\Report\Http\Controllers\Api\V1\ReportController;
use Modules\Report\Services\Report\ReportServiceInterface;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    public function test_legacy_export_endpoint_returns_a_queued_export_id(): void
    {
        $export = new Export([
            'user_id' => 7,
            'resource_key' => 'reports',
            'context_key' => 'report:sales',
            'format' => ExportFormat::Csv,
            'status' => ExportStatus::Pending,
            'filters' => [],
            'sorts' => [],
            'context' => ['report' => 'sales'],
        ]);
        $export->id = 123;
        $service = Mockery::mock(ReportServiceInterface::class);
        $request = new Request(['filters' => ['branch_id' => 7]]);
        $service->shouldReceive('export')
            ->once()
            ->with($request, 'sales', ExportMethod::Csv)
            ->andReturn($export);

        $response = (new ReportController($service))->export($request, 'sales', ExportMethod::Csv);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(123, $response->getData(true)['body']['id']);
        $this->assertSame('pending', $response->getData(true)['body']['status']['id']);
    }
}
