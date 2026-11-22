<?php

namespace Modules\Import\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;

class ImportFileReader
{
    /**
     * Read only the header row so upload validation stays bounded for large files.
     *
     * @return list<string>
     */
    public function headers(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row === 1;
            }
        });
        if (method_exists($reader, 'listWorksheetNames')) {
            $names = $reader->listWorksheetNames($path);
            $reader->setLoadSheetsOnly($names[0]);
        }
        $workbook = $reader->load($path);
        $sheet = $workbook->getSheet(0);
        $count = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        if ($count > 100) {
            throw new RuntimeException('Import files may contain at most 100 columns.');
        }
        $headers = [];
        for ($index = 1; $index <= $count; $index++) {
            $value = trim((string) $sheet->getCellByColumnAndRow($index, 1)->getValue());
            $headers[] = $value;
        }

        if ($headers === [] || count(array_filter($headers, fn ($value) => $value !== '')) === 0) {
            throw new RuntimeException('The first row must contain column headers.');
        }

        $workbook->disconnectWorksheets();

        return $headers;
    }
}
