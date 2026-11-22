<?php

namespace Modules\Import\Support;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ImportTemplate implements FromArray, WithHeadings
{
    /**
     * Preserve definition order in the workbook's header row.
     *
     * @param  array<int, array{key: string, label: string, type: 'text'|'integer'|'boolean'|'date', required: bool, options?: array<int, array{value: string, label: string, matches?: array<string, string>}>, multiple?: bool}>  $fields
     */
    public function __construct(private array $fields) {}

    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return [array_column($this->fields, 'key')];
    }
}
