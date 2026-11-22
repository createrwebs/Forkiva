<?php

namespace Modules\Pdf\Services\Driver;

use Dompdf\Dompdf;
use HeadlessChromium\AutoDiscover;
use HeadlessChromium\BrowserFactory;
use Modules\Pdf\Data\PdfVerificationResult;
use Modules\Pdf\Enums\PdfDriver;
use Modules\Pdf\Services\Rendering\ArabicHtmlProcessor;
use Pontedilana\PhpWeasyPrint\Pdf as WeasyPrint;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

class PdfDriverVerifier implements PdfDriverVerifierInterface
{
    /**
     * Create the driver verifier.
     */
    public function __construct(
        protected PdfDriverConfigurator $configurator,
        protected ArabicHtmlProcessor $arabicHtmlProcessor,
    ) {}

    /** {@inheritDoc} */
    public function verify(PdfDriver $driver, array $configuration, bool $receipt = false): PdfVerificationResult
    {
        $startedAt = hrtime(true);
        $dependency = $this->dependencyStatus($driver, $configuration);

        if (! $dependency['available']) {
            return $this->result($driver, false, ['dependency'], $dependency['message'], $startedAt);
        }

        try {
            [$content, $receiptContent] = $this->configurator->run($driver, $configuration, function () use ($driver, $receipt): array {
                $content = $this->render($driver, false);

                if (! $receipt) {
                    return [$content, null];
                }

                $this->waitForCloudflareQuickActionLimit($driver);

                return [$content, $this->render($driver, true)];
            });

            if (! $this->isPdf($content)) {
                return $this->result(
                    $driver,
                    false,
                    ['dependency', 'render'],
                    __('pdf::settings.messages.invalid_pdf'),
                    $startedAt,
                );
            }

            if (
                $receipt
                && (! is_string($receiptContent)
                    || ! $this->isPdf($receiptContent)
                    || ! $this->hasReceiptSize($receiptContent))
            ) {
                return $this->result(
                    $driver,
                    false,
                    ['dependency', 'render', 'pdf_signature', 'receipt_dimensions'],
                    __('pdf::settings.messages.invalid_receipt_size'),
                    $startedAt,
                );
            }

            $checks = ['dependency', 'render', 'pdf_signature'];
            if ($receipt) {
                $checks[] = 'receipt_dimensions';
            }

            return $this->result($driver, true, $checks, null, $startedAt);
        } catch (Throwable $exception) {
            return $this->result(
                $driver,
                false,
                ['dependency', 'render'],
                $this->safeMessage($driver, $exception),
                $startedAt,
            );
        }
    }

    /** {@inheritDoc} */
    public function dependencyStatus(PdfDriver $driver, array $configuration): array
    {
        $finder = new ExecutableFinder;

        return match ($driver) {
            PdfDriver::Dompdf => $this->status(
                class_exists(Dompdf::class),
                __('pdf::settings.messages.dependencies.dompdf'),
            ),
            PdfDriver::Browsershot => $this->status(
                class_exists(Browsershot::class)
                    && $this->executable($configuration['node_binary'] ?? null, 'node', $finder)
                    && $this->executable($configuration['npm_binary'] ?? null, 'npm', $finder)
                    && $this->puppeteerInstalled($configuration['node_modules_path'] ?? null)
                    && (! filled($configuration['chrome_path'] ?? null) || is_executable($configuration['chrome_path'])),
                __('pdf::settings.messages.dependencies.browsershot'),
            ),
            PdfDriver::Chrome => $this->status(
                class_exists(BrowserFactory::class)
                    && $this->chromeExecutable($configuration['chrome_binary'] ?? null, $finder),
                __('pdf::settings.messages.dependencies.chrome'),
            ),
            PdfDriver::Cloudflare => $this->status(
                filled($configuration['account_id'] ?? null) && filled($configuration['api_token'] ?? null),
                __('pdf::settings.messages.dependencies.cloudflare'),
            ),
            PdfDriver::Gotenberg => $this->status(
                filter_var($configuration['url'] ?? null, FILTER_VALIDATE_URL) !== false,
                __('pdf::settings.messages.dependencies.gotenberg'),
            ),
            PdfDriver::Weasyprint => $this->status(
                class_exists(WeasyPrint::class)
                    && $this->executable($configuration['binary'] ?? null, 'weasyprint', $finder),
                __('pdf::settings.messages.dependencies.weasyprint'),
            ),
        };
    }

    /**
     * Determine whether generated bytes contain a complete PDF document.
     */
    private function isPdf(string $content): bool
    {
        return strlen($content) > 100
            && str_starts_with($content, '%PDF-')
            && str_contains(substr($content, -1024), '%%EOF');
    }

    /**
     * Render an A4 or receipt-sized verification document.
     */
    private function render(PdfDriver $driver, bool $receipt): string
    {
        $data = [
            'driver' => $driver->value,
            'checkedAt' => now(),
        ];
        $builder = ($driver === PdfDriver::Dompdf
            ? Pdf::html($this->arabicHtmlProcessor->process(
                view('pdf::health-check', $data)->render(),
            ))
            : Pdf::view('pdf::health-check', $data))
            ->driver($driver->value)
            ->margins(4, 4, 4, 4);

        $receipt
            ? $builder->paperSize(80, 200, 'mm')
            : $builder->format('a4');

        return $builder->generatePdfContent();
    }

    /**
     * Workers Free permits one Browser Run Quick Action every ten seconds.
     */
    private function waitForCloudflareQuickActionLimit(PdfDriver $driver): void
    {
        if ($driver === PdfDriver::Cloudflare) {
            usleep(10_250_000);
        }
    }

    /**
     * Determine whether a configured or discoverable executable is available.
     */
    private function executable(?string $configured, string $fallback, ExecutableFinder $finder): bool
    {
        if (filled($configured)) {
            return is_executable($configured) || $finder->find($configured) !== null;
        }

        return $finder->find($fallback) !== null;
    }

    /**
     * Determine whether generated PDF dimensions match the receipt contract.
     */
    private function hasReceiptSize(string $content): bool
    {
        $searchableContent = $this->searchablePdfContent($content);

        if (! preg_match('/\/MediaBox\s*\[\s*(?:0(?:\.0+)?\s+){2}([0-9.]+)\s+([0-9.]+)\s*\]/', $searchableContent, $matches)) {
            return false;
        }

        $expectedWidth = 80 * 2.83465;
        $expectedHeight = 200 * 2.83465;

        return abs((float) $matches[1] - $expectedWidth) < 2
            && abs((float) $matches[2] - $expectedHeight) < 2;
    }

    /**
     * Include page dictionaries stored in compressed PDF object streams.
     */
    private function searchablePdfContent(string $content): string
    {
        if (str_contains($content, '/MediaBox')) {
            return $content;
        }

        preg_match_all(
            '#/Type\s*/ObjStm.*?stream\r?\n(.*?)\r?\nendstream#s',
            $content,
            $streams,
        );

        foreach ($streams[1] ?? [] as $stream) {
            $decoded = @gzuncompress($stream, 1_000_000);
            if (is_string($decoded)) {
                $content .= "\n".$decoded;
            }
        }

        return $content;
    }

    /**
     * Determine whether Chrome can be discovered or run from the configured path.
     */
    private function chromeExecutable(?string $configured, ExecutableFinder $finder): bool
    {
        if (filled($configured)) {
            return is_executable($configured);
        }

        foreach (['google-chrome', 'chromium', 'chromium-browser', 'chrome'] as $binary) {
            if ($finder->find($binary) !== null) {
                return true;
            }
        }

        return is_executable((new AutoDiscover)->guessChromeBinaryPath());
    }

    /**
     * Determine whether Puppeteer is installed in the configured Node modules directory.
     */
    private function puppeteerInstalled(?string $nodeModulesPath): bool
    {
        $root = filled($nodeModulesPath) ? $nodeModulesPath : base_path('node_modules');

        return is_dir($root.'/puppeteer') || is_dir($root.'/puppeteer-core');
    }

    /**
     * Build a normalized dependency status response.
     *
     * @return array{available: bool, message: ?string}
     */
    private function status(bool $available, string $message): array
    {
        return ['available' => $available, 'message' => $available ? null : $message];
    }

    /**
     * Build a driver-safe verification failure message.
     */
    private function safeMessage(PdfDriver $driver, Throwable $exception): string
    {
        if ($driver === PdfDriver::Cloudflare) {
            $cloudflareMessage = $this->cloudflareErrorMessage($exception);
            if ($cloudflareMessage !== null) {
                return $cloudflareMessage;
            }
        }

        return __('pdf::settings.messages.render_failed', [
            'driver' => __("pdf::settings.drivers.{$driver->value}"),
        ]);
    }

    /**
     * Extract only structured Cloudflare API errors without exposing credentials or response HTML.
     */
    private function cloudflareErrorMessage(Throwable $exception): ?string
    {
        $prefix = 'Cloudflare PDF generation failed: ';
        if (! str_starts_with($exception->getMessage(), $prefix)) {
            return null;
        }

        $payload = json_decode(substr($exception->getMessage(), strlen($prefix)), true);
        if (! is_array($payload) || ! is_array($payload['errors'] ?? null)) {
            return null;
        }

        $messages = array_values(array_filter(array_map(
            static function (mixed $error): ?string {
                if (! is_array($error) || ! is_string($error['message'] ?? null)) {
                    return null;
                }

                $code = isset($error['code']) && is_scalar($error['code'])
                    ? " [{$error['code']}]"
                    : '';

                return trim($error['message']).$code;
            },
            $payload['errors'],
        )));

        if ($messages === []) {
            return null;
        }

        return __('pdf::settings.messages.cloudflare_api_failed', [
            'message' => implode(' ', $messages),
        ]);
    }

    /**
     * Build a timestamped verification result.
     *
     * @param  list<string>  $checks
     */
    private function result(
        PdfDriver $driver,
        bool $ready,
        array $checks,
        ?string $message,
        int $startedAt,
    ): PdfVerificationResult {
        return new PdfVerificationResult(
            driver: $driver,
            ready: $ready,
            checks: $checks,
            message: $message,
            durationMs: (int) round((hrtime(true) - $startedAt) / 1_000_000),
            checkedAt: now()->toIso8601String(),
        );
    }
}
