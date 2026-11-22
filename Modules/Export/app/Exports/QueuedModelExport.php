<?php

namespace Modules\Export\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Modules\Export\Support\ExportValueNormalizer;

class QueuedModelExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  array<int,array<string,mixed>>  $columns
     */
    public function __construct(
        protected Builder $query,
        protected array $columns,
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
        return array_map(fn (array $column) => __($column['label']), $this->columns);
    }

    /** {@inheritDoc} */
    public function map($row): array
    {
        return array_map(
            fn (array $column) => $this->normalizer->normalize(
                data_get($row, $column['path']),
                $column,
            ),
            $this->columns,
        );
    }
}
