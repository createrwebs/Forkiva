<?php

namespace Modules\Pdf\Services\Settings;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Pdf\Enums\PdfDriver;
use Modules\Pdf\Services\Driver\PdfDriverVerifierInterface;

class PdfSettingsManager implements PdfSettingsManagerInterface
{
    private const SECRET_KEYS = ['pdf_cloudflare_api_token', 'pdf_gotenberg_password'];

    /**
     * Create the PDF settings manager.
     */
    public function __construct(protected PdfDriverVerifierInterface $verifier) {}

    /** {@inheritDoc} */
    public function settings(): array
    {
        $stored = $this->storedSettings();

        return [
            ...$stored,
            'encryptable' => [
                'pdf_cloudflare_api_token' => null,
                'pdf_gotenberg_password' => null,
            ],
            'pdf_secret_status' => [
                'cloudflare_api_token' => filled($this->secret('pdf_cloudflare_api_token')),
                'gotenberg_password' => filled($this->secret('pdf_gotenberg_password')),
            ],
            'pdf_clear_secrets' => [
                'cloudflare_api_token' => false,
                'gotenberg_password' => false,
            ],
        ];
    }

    /**
     * Get all persisted plain driver settings with configuration defaults.
     *
     * @return array<string, mixed>
     */
    private function storedSettings(): array
    {
        return [
            'pdf_default_driver' => setting('pdf_default_driver', config('pdf.default_driver', 'dompdf')),
            'pdf_dompdf_remote_enabled' => (bool) setting('pdf_dompdf_remote_enabled', config('pdf.drivers.dompdf.is_remote_enabled', false)),
            'pdf_dompdf_chroot' => setting('pdf_dompdf_chroot', config('pdf.drivers.dompdf.chroot')),
            'pdf_browsershot_node_binary' => setting('pdf_browsershot_node_binary', config('pdf.drivers.browsershot.node_binary')),
            'pdf_browsershot_npm_binary' => setting('pdf_browsershot_npm_binary', config('pdf.drivers.browsershot.npm_binary')),
            'pdf_browsershot_chrome_path' => setting('pdf_browsershot_chrome_path', config('pdf.drivers.browsershot.chrome_path')),
            'pdf_browsershot_node_modules_path' => setting('pdf_browsershot_node_modules_path', config('pdf.drivers.browsershot.node_modules_path')),
            'pdf_browsershot_no_sandbox' => (bool) setting('pdf_browsershot_no_sandbox', config('pdf.drivers.browsershot.no_sandbox', false)),
            'pdf_chrome_binary' => setting('pdf_chrome_binary', config('pdf.drivers.chrome.chrome_binary')),
            'pdf_chrome_no_sandbox' => (bool) setting('pdf_chrome_no_sandbox', config('pdf.drivers.chrome.no_sandbox', false)),
            'pdf_chrome_startup_timeout' => (int) setting('pdf_chrome_startup_timeout', config('pdf.drivers.chrome.startup_timeout', 30)),
            'pdf_chrome_timeout' => (int) setting('pdf_chrome_timeout', config('pdf.drivers.chrome.timeout', 30000)),
            'pdf_chrome_operation_timeout' => (int) setting('pdf_chrome_operation_timeout', config('pdf.drivers.chrome.operation_timeout', 5000)),
            'pdf_chrome_user_data_dir' => setting('pdf_chrome_user_data_dir', config('pdf.drivers.chrome.user_data_dir')),
            'pdf_cloudflare_account_id' => setting('pdf_cloudflare_account_id', config('pdf.drivers.cloudflare.account_id')),
            'pdf_gotenberg_url' => setting('pdf_gotenberg_url', config('pdf.drivers.gotenberg.url', 'http://localhost:3000')),
            'pdf_gotenberg_username' => setting('pdf_gotenberg_username', config('pdf.drivers.gotenberg.username')),
            'pdf_weasyprint_binary' => setting('pdf_weasyprint_binary', config('pdf.drivers.weasyprint.binary', 'weasyprint')),
            'pdf_weasyprint_timeout' => (int) setting('pdf_weasyprint_timeout', config('pdf.drivers.weasyprint.timeout', 10)),
        ];
    }

    /**
     * Resolve a stored secret with its deployment configuration fallback.
     */
    private function secret(string $key): mixed
    {
        return setting($key, config(match ($key) {
            'pdf_cloudflare_api_token' => 'pdf.drivers.cloudflare.api_token',
            'pdf_gotenberg_password' => 'pdf.drivers.gotenberg.password',
        }));
    }

    /** {@inheritDoc} */
    public function meta(): array
    {
        $verifications = setting('pdf_driver_verifications', []);

        return [
            'drivers' => array_map(function (PdfDriver $driver) use ($verifications): array {
                $status = $this->verifier->dependencyStatus($driver, $this->driverConfiguration($driver));
                $verification = $verifications[$driver->value] ?? null;
                if (is_array($verification)) {
                    unset($verification['fingerprint']);
                }

                return [
                    'id' => $driver->value,
                    'name' => __("pdf::settings.drivers.{$driver->value}"),
                    'requirements' => __("pdf::settings.requirements.{$driver->value}"),
                    'available' => $status['available'],
                    'message' => $status['message'],
                    'verification' => $verification,
                ];
            }, PdfDriver::cases()),
        ];
    }

    /** {@inheritDoc} */
    public function driverConfiguration(PdfDriver $driver, ?array $source = null): array
    {
        $source ??= [...$this->storedSettings(), ...$this->storedSecrets()];
        $fallback = config('pdf.drivers.'.$driver->value, []);

        return match ($driver) {
            PdfDriver::Dompdf => [
                'is_remote_enabled' => $this->configured($source, 'pdf_dompdf_remote_enabled', $fallback['is_remote_enabled'] ?? false),
                'chroot' => $this->configured($source, 'pdf_dompdf_chroot', $fallback['chroot'] ?? null),
            ],
            PdfDriver::Browsershot => [
                'node_binary' => $this->configured($source, 'pdf_browsershot_node_binary', $fallback['node_binary'] ?? null),
                'npm_binary' => $this->configured($source, 'pdf_browsershot_npm_binary', $fallback['npm_binary'] ?? null),
                'chrome_path' => $this->configured($source, 'pdf_browsershot_chrome_path', $fallback['chrome_path'] ?? null),
                'node_modules_path' => $this->configured($source, 'pdf_browsershot_node_modules_path', $fallback['node_modules_path'] ?? null),
                'no_sandbox' => $this->configured($source, 'pdf_browsershot_no_sandbox', $fallback['no_sandbox'] ?? false),
                'write_options_to_file' => true,
            ],
            PdfDriver::Chrome => [
                'chrome_binary' => $this->configured($source, 'pdf_chrome_binary', $fallback['chrome_binary'] ?? null),
                'no_sandbox' => $this->configured($source, 'pdf_chrome_no_sandbox', $fallback['no_sandbox'] ?? false),
                'startup_timeout' => $this->configured($source, 'pdf_chrome_startup_timeout', $fallback['startup_timeout'] ?? 30),
                'timeout' => $this->configured($source, 'pdf_chrome_timeout', $fallback['timeout'] ?? 30000),
                'operation_timeout' => $this->configured($source, 'pdf_chrome_operation_timeout', $fallback['operation_timeout'] ?? 5000),
                'user_data_dir' => $this->configured($source, 'pdf_chrome_user_data_dir', $fallback['user_data_dir'] ?? null),
                'custom_flags' => [],
                'env_variables' => [],
            ],
            PdfDriver::Cloudflare => [
                'account_id' => $this->configured($source, 'pdf_cloudflare_account_id', $fallback['account_id'] ?? null),
                'api_token' => $this->configured($source, 'pdf_cloudflare_api_token', $fallback['api_token'] ?? null),
            ],
            PdfDriver::Gotenberg => [
                'url' => $this->configured($source, 'pdf_gotenberg_url', $fallback['url'] ?? 'http://localhost:3000'),
                'username' => $this->configured($source, 'pdf_gotenberg_username', $fallback['username'] ?? null),
                'password' => $this->configured($source, 'pdf_gotenberg_password', $fallback['password'] ?? null),
            ],
            PdfDriver::Weasyprint => [
                'binary' => $this->configured($source, 'pdf_weasyprint_binary', $fallback['binary'] ?? 'weasyprint'),
                'timeout' => $this->configured($source, 'pdf_weasyprint_timeout', $fallback['timeout'] ?? 10),
            ],
        };
    }

    /**
     * Get decrypted driver secrets without exposing them to API responses.
     *
     * @return array<string, mixed>
     */
    private function storedSecrets(): array
    {
        return array_combine(self::SECRET_KEYS, array_map(fn (string $key): mixed => $this->secret($key), self::SECRET_KEYS));
    }

    /**
     * Resolve a submitted configuration value before its deployment fallback.
     *
     * @param  array<string, mixed>  $source
     */
    private function configured(array $source, string $key, mixed $fallback): mixed
    {
        return array_key_exists($key, $source) ? $source[$key] : $fallback;
    }

    /** {@inheritDoc} */
    public function update(array $data): void
    {
        $candidate = array_replace($this->storedSettings(), $data);
        $candidateSecrets = $this->candidateSecrets($data);
        $driver = PdfDriver::from($candidate['pdf_default_driver']);

        $verifications = setting('pdf_driver_verifications', []);
        $source = [...$candidate, ...$candidateSecrets];
        $configuration = $this->driverConfiguration($driver, $source);
        $fingerprint = $this->fingerprint($configuration);

        $result = $this->verifier->verify($driver, $configuration, true);

        if (! $result->ready) {
            throw ValidationException::withMessages([
                'pdf_default_driver' => $result->message,
            ]);
        }

        $verifications[$driver->value] = [
            ...$result->toArray(),
            'fingerprint' => $fingerprint,
        ];

        $plain = $this->onlyPersistable($candidate);
        $plain['pdf_driver_verifications'] = $verifications;

        DB::transaction(function () use ($plain, $candidateSecrets): void {
            setting($plain);
            setting(['encryptable' => $candidateSecrets]);
        });
    }

    /**
     * Merge submitted secret changes with stored secret values.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function candidateSecrets(array $data): array
    {
        $stored = $this->storedSecrets();
        $submitted = $data['encryptable'] ?? [];
        $clear = $data['pdf_clear_secrets'] ?? [];

        return [
            'pdf_cloudflare_api_token' => $this->candidateSecret(
                $submitted['pdf_cloudflare_api_token'] ?? null,
                (bool) ($clear['cloudflare_api_token'] ?? false),
                $stored['pdf_cloudflare_api_token'],
            ),
            'pdf_gotenberg_password' => $this->candidateSecret(
                $submitted['pdf_gotenberg_password'] ?? null,
                (bool) ($clear['gotenberg_password'] ?? false),
                $stored['pdf_gotenberg_password'],
            ),
        ];
    }

    /**
     * Prefer a newly entered secret, keep the stored value for an empty input, or clear it explicitly.
     */
    private function candidateSecret(mixed $submitted, bool $clear, mixed $stored): mixed
    {
        if (filled($submitted)) {
            return $submitted;
        }

        return $clear ? null : $stored;
    }

    /**
     * Create a non-reversible fingerprint for verification metadata.
     *
     * @param  array<string, mixed>  $configuration
     *
     * @throws JsonException
     */
    private function fingerprint(array $configuration): string
    {
        return hash('sha256', json_encode($configuration, JSON_THROW_ON_ERROR));
    }

    /**
     * Limit a candidate payload to supported plain setting keys.
     *
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function onlyPersistable(array $candidate): array
    {
        return array_intersect_key($candidate, $this->storedSettings());
    }

    /** {@inheritDoc} */
    public function resolveDriver(): PdfDriver
    {
        return PdfDriver::tryFrom(setting('pdf_default_driver', config('pdf.default_driver', 'dompdf')))
            ?? PdfDriver::Dompdf;
    }
}
