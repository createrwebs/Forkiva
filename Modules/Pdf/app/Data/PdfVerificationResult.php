<?php

namespace Modules\Pdf\Data;

use Modules\Pdf\Enums\PdfDriver;

readonly class PdfVerificationResult
{
    /**
     * Create an immutable driver verification result.
     *
     * @param  list<string>  $checks
     */
    public function __construct(
        public PdfDriver $driver,
        public bool $ready,
        public array $checks,
        public ?string $message,
        public int $durationMs,
        public string $checkedAt,
    ) {}

    /**
     * Convert the result to its settings-safe representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver->value,
            'ready' => $this->ready,
            'checks' => $this->checks,
            'message' => $this->message,
            'duration_ms' => $this->durationMs,
            'checked_at' => $this->checkedAt,
        ];
    }
}
