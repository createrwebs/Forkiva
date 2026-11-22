<?php

namespace Modules\Report\Export;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Modules\Export\Support\ExportValueNormalizer;
use Modules\Report\Contracts\ReportInterface;

class QueuedReportExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * Create a chunked report export.
     */
    public function __construct(
        protected ReportInterface $report,
        protected Builder $query,
        protected ExportValueNormalizer $normalizer,
    ) {}

    /** {@inheritDoc} */
    public function query(): Builder
    {
        return $this->query;
    }

    /** {@inheritDoc} */
    public function headings(): array
    {
        return array_values($this->report->transAttributes());
    }

    /** {@inheritDoc} */
    public function map($row): array
    {
        $resource = $this->report->resource($row);

        return $this->report->attributes()
            ->map(fn (string $attribute) => $this->normalizer->normalize(data_get($resource, $attribute)))
            ->values()
            ->all();
    }
}
