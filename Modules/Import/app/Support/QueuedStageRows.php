<?php

namespace Modules\Import\Support;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Row;
use Modules\Import\Contracts\ImportDefinitionInterface;
use Modules\Import\Enums\ImportRowStatus;
use Modules\Import\Enums\ImportStatus;
use Modules\Import\Models\DataImport;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class QueuedStageRows implements OnEachRow, ShouldQueue, WithChunkReading, WithEvents, WithMultipleSheets, WithStartRow
{
    use RegistersEventListeners;

    public int $tries = 1;

    private array $buffer = [];

    private ?DataImport $activeImport = null;

    private ?ImportDefinitionInterface $definition = null;

    private ?string $previousLocale = null;

    private int $dateCalendar = ExcelDate::CALENDAR_WINDOWS_1900;

    /** @var list<int> */
    private array $dateColumns = [];

    public function __construct(private int $importId, private int $revision) {}

    /**
     * Match header detection: only the first worksheet belongs to this import.
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function startRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return max(100, (int) config('import.chunk_size', 500));
    }

    /**
     * Restore actor and locale for each queued Excel chunk's authorization checks.
     */
    public function beforeSheet(BeforeSheet $event): void
    {
        $import = DataImport::query()
            ->with('createdBy')
            ->find($this->importId);
        if (! $import || ! $import->isAtRevision(ImportStatus::Staging, $this->revision) || ! $import->createdBy) {
            return;
        }
        Auth::setUser($import->createdBy);
        $this->previousLocale = locale();
        app()->setLocale($import->locale);
        try {
            $definition = app(ImportDefinitionResolver::class)->resolve($import->resource_key);
            $definition->authorize($import);
            $this->activeImport = $import;
            $this->definition = $definition;
            $this->dateColumns = [];
            $this->dateCalendar = ExcelDate::CALENDAR_WINDOWS_1900;
            foreach ($definition->fields() as $field) {
                $column = $import->mapping[$field['key']] ?? null;
                if ($field['type'] === 'date' && $column !== null) {
                    $this->dateColumns[] = (int) $column;
                }
            }
            if (in_array(strtolower(pathinfo($import->path, PATHINFO_EXTENSION)), ['xls', 'xlsx'], true)) {
                $this->dateCalendar = ExcelDate::getExcelCalendar();
            }
        } catch (Throwable $exception) {
            $this->clearContext();
            throw $exception;
        }
    }

    public function onRow(Row $row): void
    {
        if (! $this->activeImport || ! $this->definition) {
            return;
        }
        $values = array_slice(array_values($row->toArray()), 0, count($this->activeImport->headers));
        if (count(array_filter($values, fn ($value) => $value !== null && trim((string) $value) !== '')) === 0) {
            return;
        }
        $values = array_map(fn ($value) => is_scalar($value) ? (string) $value : null, $values);
        foreach ($this->dateColumns as $column) {
            if (isset($values[$column])) {
                $values[$column] = ImportDate::normalizeSerial($values[$column], $this->dateCalendar);
            }
        }
        $data = [];
        foreach ($this->activeImport->mapping as $field => $column) {
            $data[$field] = $values[$column] ?? null;
        }
        $now = now();
        $this->buffer[] = [
            'import_id' => $this->importId,
            'row_number' => $row->getIndex(),
            'values' => json_encode($values, JSON_THROW_ON_ERROR),
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
            'record_key' => $this->definition->key($data, $this->activeImport),
            'status' => ImportRowStatus::Pending->value,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (count($this->buffer) >= $this->chunkSize()) {
            $this->flush();
        }
    }

    public function afterSheet(AfterSheet $event): void
    {
        try {
            $this->flush();
        } finally {
            $this->activeImport = null;
            $this->definition = null;
            $this->dateColumns = [];
            $this->clearContext();
        }
    }

    public function afterImport(AfterImport $event): void
    {
        $import = DataImport::query()
            ->find($this->importId);
        if ($import?->isAtRevision(ImportStatus::Staging, $this->revision)) {
            $total = $import->rows()->count();
            $import->update(['status' => ImportStatus::Staged, 'total_rows' => $total, 'processed_rows' => 0]);
        }
    }

    public function importFailed(ImportFailed $event): void
    {
        $import = DataImport::query()
            ->find($this->importId);
        if ($import?->isAtRevision(ImportStatus::Staging, $this->revision)) {
            $reason = app()->isProduction()
                ? __('import::imports.messages.failed_staging')
                : $event->getException()->getMessage();
            $import->update([
                'status' => ImportStatus::Failed,
                'failure_reason' => $reason,
                'finished_at' => now(),
            ]);
        }
        $this->clearContext();
    }

    /**
     * Persist a chunk only when its import revision is still active.
     */
    private function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }
        DB::transaction(function (): void {
            $import = DataImport::query()
                ->lockForUpdate()
                ->find($this->importId);
            if ($import?->isAtRevision(ImportStatus::Staging, $this->revision)) {
                DB::table('import_rows')->insertOrIgnore($this->buffer);
                $import->update(['processed_rows' => $import->rows()->count()]);
            }
        });
        $this->buffer = [];
    }

    private function clearContext(): void
    {
        Auth::forgetGuards();
        if ($this->previousLocale !== null) {
            app()->setLocale($this->previousLocale);
            $this->previousLocale = null;
        }
    }
}
