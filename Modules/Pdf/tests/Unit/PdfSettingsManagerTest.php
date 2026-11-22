<?php

namespace Modules\Pdf\Tests\Unit;

use Illuminate\Validation\ValidationException;
use Modules\Pdf\Data\PdfVerificationResult;
use Modules\Pdf\Enums\PdfDriver;
use Modules\Pdf\Services\Driver\PdfDriverVerifierInterface;
use Modules\Pdf\Services\Settings\PdfSettingsManager;
use Modules\Pdf\Services\Settings\PdfSettingsManagerInterface;
use Modules\Setting\Repositories\SettingRepository;
use Tests\TestCase;

class PdfSettingsManagerTest extends TestCase
{
    public function test_dompdf_is_the_default_driver(): void
    {
        $this->setSettings([]);

        $driver = app(PdfSettingsManagerInterface::class)->resolveDriver();

        $this->assertSame(PdfDriver::Dompdf, $driver);
    }

    public function test_saved_driver_is_used_for_every_pdf(): void
    {
        $this->setSettings([
            'pdf_default_driver' => 'weasyprint',
        ]);

        $driver = app(PdfSettingsManagerInterface::class)->resolveDriver();

        $this->assertSame(PdfDriver::Weasyprint, $driver);
    }

    public function test_saved_null_values_override_environment_defaults(): void
    {
        config([
            'pdf.drivers.cloudflare.account_id' => 'environment-account',
            'pdf.drivers.cloudflare.api_token' => 'environment-token',
        ]);
        $this->setSettings([
            'pdf_cloudflare_account_id' => null,
            'pdf_cloudflare_api_token' => null,
        ], ['pdf_cloudflare_api_token']);

        $configuration = app(PdfSettingsManagerInterface::class)->driverConfiguration(PdfDriver::Cloudflare);

        $this->assertNull($configuration['account_id']);
        $this->assertNull($configuration['api_token']);
    }

    public function test_secret_values_are_never_returned_by_settings_response(): void
    {
        $this->setSettings([
            'pdf_cloudflare_api_token' => 'top-secret-token',
            'pdf_gotenberg_password' => 'top-secret-password',
            'pdf_driver_verifications' => [
                'cloudflare' => ['ready' => true, 'fingerprint' => 'internal-fingerprint'],
            ],
        ], ['pdf_cloudflare_api_token', 'pdf_gotenberg_password']);

        $manager = app(PdfSettingsManagerInterface::class);
        $settings = $manager->settings();
        $meta = $manager->meta();

        $this->assertNull($settings['encryptable']['pdf_cloudflare_api_token']);
        $this->assertNull($settings['encryptable']['pdf_gotenberg_password']);
        $this->assertTrue($settings['pdf_secret_status']['cloudflare_api_token']);
        $this->assertTrue($settings['pdf_secret_status']['gotenberg_password']);
        $this->assertStringNotContainsString('top-secret', json_encode($settings));
        $this->assertStringNotContainsString('internal-fingerprint', json_encode($meta));
    }

    public function test_successful_verification_saves_plain_and_encrypted_settings_separately(): void
    {
        $repository = $this->recordingSettings([
            'pdf_cloudflare_api_token' => 'existing-secret',
        ], ['pdf_cloudflare_api_token']);
        $manager = new PdfSettingsManager($this->verifier(true));

        $manager->update([
            'pdf_default_driver' => 'dompdf',
            'encryptable' => [],
            'pdf_clear_secrets' => [],
        ]);

        $this->assertCount(2, $repository->writes);
        $this->assertSame('dompdf', $repository->writes[0]['pdf_default_driver']);
        $this->assertArrayHasKey('pdf_driver_verifications', $repository->writes[0]);
        $this->assertSame('existing-secret', $repository->writes[1]['encryptable']['pdf_cloudflare_api_token']);
    }

    public function test_new_secret_replaces_stored_secret_even_when_clear_flag_is_stale(): void
    {
        $repository = $this->recordingSettings([
            'pdf_cloudflare_api_token' => 'existing-secret',
        ], ['pdf_cloudflare_api_token']);
        $manager = new PdfSettingsManager($this->verifier(true));

        $manager->update([
            'pdf_default_driver' => 'cloudflare',
            'pdf_cloudflare_account_id' => 'account-id',
            'encryptable' => ['pdf_cloudflare_api_token' => 'replacement-token'],
            'pdf_clear_secrets' => ['cloudflare_api_token' => true],
        ]);

        $this->assertSame(
            'replacement-token',
            $repository->writes[1]['encryptable']['pdf_cloudflare_api_token'],
        );
    }

    public function test_empty_secret_input_keeps_the_stored_secret(): void
    {
        $repository = $this->recordingSettings([
            'pdf_cloudflare_api_token' => 'existing-secret',
        ], ['pdf_cloudflare_api_token']);
        $manager = new PdfSettingsManager($this->verifier(true));

        $manager->update([
            'pdf_default_driver' => 'cloudflare',
            'pdf_cloudflare_account_id' => 'account-id',
            'encryptable' => ['pdf_cloudflare_api_token' => ''],
            'pdf_clear_secrets' => ['cloudflare_api_token' => false],
        ]);

        $this->assertSame(
            'existing-secret',
            $repository->writes[1]['encryptable']['pdf_cloudflare_api_token'],
        );
    }

    public function test_failed_verification_does_not_save_any_setting(): void
    {
        $repository = $this->recordingSettings([]);
        $manager = new PdfSettingsManager($this->verifier(false));

        try {
            $manager->update([
                'pdf_default_driver' => 'dompdf',
            ]);
            $this->fail('A failed driver verification must reject the update.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pdf_default_driver', $exception->errors());
        }

        $this->assertSame([], $repository->writes);
    }

    public function test_every_save_runs_a_fresh_verification_for_unchanged_settings(): void
    {
        $this->setSettings(['pdf_default_driver' => 'dompdf']);
        $configuration = app(PdfSettingsManagerInterface::class)->driverConfiguration(PdfDriver::Dompdf);
        $fingerprint = hash('sha256', json_encode($configuration, JSON_THROW_ON_ERROR));
        $repository = $this->recordingSettings([
            'pdf_default_driver' => 'dompdf',
            'pdf_driver_verifications' => [
                'dompdf' => [
                    'ready' => true,
                    'fingerprint' => $fingerprint,
                ],
            ],
        ]);
        $verifier = new class implements PdfDriverVerifierInterface
        {
            public int $calls = 0;

            public function verify(PdfDriver $driver, array $configuration, bool $receipt = false): PdfVerificationResult
            {
                $this->calls++;

                return new PdfVerificationResult(
                    driver: $driver,
                    ready: true,
                    checks: ['render'],
                    message: null,
                    durationMs: 1,
                    checkedAt: '2026-09-05T00:00:00+00:00',
                );
            }

            public function dependencyStatus(PdfDriver $driver, array $configuration): array
            {
                return ['available' => true, 'message' => null];
            }
        };

        (new PdfSettingsManager($verifier))->update(['pdf_default_driver' => 'dompdf']);

        $this->assertSame(1, $verifier->calls);
        $this->assertCount(2, $repository->writes);
    }

    private function setSettings(array $values, array $encrypted = []): void
    {
        $cached = collect($values)->mapWithKeys(static fn (mixed $payload, string $key): array => [
            $key => [
                'payload' => in_array($key, $encrypted, true) ? encrypt($payload) : serialize($payload),
                'is_encryptable' => in_array($key, $encrypted, true),
                'is_translatable' => false,
            ],
        ]);

        app()->instance('setting', new SettingRepository($cached));
    }

    private function recordingSettings(array $values, array $encrypted = []): RecordingSettingRepository
    {
        $cached = collect($values)->mapWithKeys(static fn (mixed $payload, string $key): array => [
            $key => [
                'payload' => in_array($key, $encrypted, true) ? encrypt($payload) : serialize($payload),
                'is_encryptable' => in_array($key, $encrypted, true),
                'is_translatable' => false,
            ],
        ]);
        $repository = new RecordingSettingRepository($cached);
        app()->instance('setting', $repository);

        return $repository;
    }

    private function verifier(bool $ready): PdfDriverVerifierInterface
    {
        return new class($ready) implements PdfDriverVerifierInterface
        {
            public function __construct(private readonly bool $ready) {}

            public function verify(PdfDriver $driver, array $configuration, bool $receipt = false): PdfVerificationResult
            {
                return new PdfVerificationResult(
                    driver: $driver,
                    ready: $this->ready,
                    checks: ['render'],
                    message: $this->ready ? null : 'Verification failed.',
                    durationMs: 1,
                    checkedAt: '2026-09-05T00:00:00+00:00',
                );
            }

            public function dependencyStatus(PdfDriver $driver, array $configuration): array
            {
                return ['available' => true, 'message' => null];
            }
        };
    }
}

class RecordingSettingRepository extends SettingRepository
{
    /** @var list<array<string, mixed>> */
    public array $writes = [];

    public function set(array $settings = []): void
    {
        $this->writes[] = $settings;
    }
}
