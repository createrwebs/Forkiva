<?php

namespace Modules\Import\Support;

use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;

/**
 * Read legacy XLS chunks without serializing the binary reader into a queue payload.
 */
class StageRows implements OnEachRow, WithChunkReading, WithEvents, WithMultipleSheets, WithStartRow
{
    private QueuedStageRows $reader;

    public function __construct(int $importId, int $revision)
    {
        $this->reader = new QueuedStageRows($importId, $revision);
    }

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function startRow(): int
    {
        return $this->reader->startRow();
    }

    public function chunkSize(): int
    {
        return $this->reader->chunkSize();
    }

    public function onRow(Row $row): void
    {
        $this->reader->onRow($row);
    }

    public function registerEvents(): array
    {
        return $this->reader->registerEvents();
    }
}
