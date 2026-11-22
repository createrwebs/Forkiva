<?php

namespace Modules\Pdf\Console;

use Illuminate\Console\Command;
use Modules\Pdf\Enums\PdfDriver;
use Modules\Pdf\Services\Driver\PdfDriverVerifierInterface;
use Modules\Pdf\Services\Settings\PdfSettingsManagerInterface;

class DiagnosePdfCommand extends Command
{
    protected $signature = 'pdf:diagnose {driver?} {--json}';

    /**
     * Create the localized PDF diagnostics command.
     */
    public function __construct()
    {
        $this->signature = sprintf(
            'pdf:diagnose {driver? : %s} {--json : %s}',
            __('pdf::settings.diagnostics.driver_argument'),
            __('pdf::settings.diagnostics.json_option'),
        );
        $this->description = __('pdf::settings.diagnostics.description');

        parent::__construct();
    }

    /**
     * Run PDF dependency and render diagnostics.
     */
    public function handle(
        PdfSettingsManagerInterface $settings,
        PdfDriverVerifierInterface $verifier,
    ): int {
        $requested = $this->argument('driver');
        $driver = is_string($requested) ? PdfDriver::tryFrom($requested) : null;

        if ($requested !== null && $driver === null) {
            $this->error(__('pdf::settings.diagnostics.unknown_driver'));

            return self::INVALID;
        }

        $drivers = $driver ? [$driver] : PdfDriver::cases();
        $results = [];
        $failed = false;

        foreach ($drivers as $candidate) {
            $result = $verifier->verify($candidate, $settings->driverConfiguration($candidate));
            $results[] = $result->toArray();
            $failed = $failed || ! $result->ready;
        }

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                [
                    __('pdf::settings.diagnostics.columns.driver'),
                    __('pdf::settings.diagnostics.columns.ready'),
                    __('pdf::settings.diagnostics.columns.duration'),
                    __('pdf::settings.diagnostics.columns.message'),
                ],
                array_map(fn (array $result): array => [
                    $result['driver'],
                    $result['ready']
                        ? __('pdf::settings.diagnostics.yes')
                        : __('pdf::settings.diagnostics.no'),
                    __('pdf::settings.diagnostics.duration', ['duration' => $result['duration_ms']]),
                    $result['message'] ?? '',
                ], $results),
            );
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
