<?php

namespace Modules\Setting\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Setting\Enums\SettingSection;
use Modules\Setting\Models\Setting;
use Modules\Setting\Repositories\SettingRepository;
use Modules\Setting\Services\Setting\SettingService;
use Tests\TestCase;

class SettingModelTest extends TestCase
{
    public function test_encrypted_payload_is_cached_as_raw_ciphertext_until_read(): void
    {
        $createdSettingsTable = ! Schema::hasTable('settings');
        if ($createdSettingsTable) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->boolean('is_translatable')->default(false);
                $table->boolean('is_encryptable')->default(false);
                $table->text('payload');
                $table->timestamps();
            });
        }

        $setting = null;

        try {
            $plaintext = 'security-check-value';
            $setting = new Setting([
                'key' => 'api_secret',
                'is_encryptable' => true,
                'payload' => $plaintext,
            ]);
            $setting->saveQuietly();
            Cache::tags('settings')->flush();

            $cachedSettings = Setting::allCached();
            $cachedPayload = $cachedSettings->get('api_secret')['payload'];

            $repository = new SettingRepository($cachedSettings);

            $this->assertNotSame($plaintext, $cachedPayload);
            $this->assertSame($setting->getRawOriginal('payload'), $cachedPayload);
            $this->assertSame($plaintext, $repository->get('api_secret'));
        } finally {
            $setting?->delete();
            Cache::tags('settings')->flush();

            if ($createdSettingsTable) {
                Schema::drop('settings');
            }
        }
    }

    public function test_encryptable_payload_returns_null_when_app_key_is_missing(): void
    {
        $key = random_bytes(32);
        $encryptedPayload = (new Encrypter($key, 'AES-256-CBC'))->encrypt('secret-value');

        config(['app.key' => null]);
        app()->forgetInstance('encrypter');

        $setting = new Setting;
        $setting->setRawAttributes([
            'is_encryptable' => true,
            'payload' => $encryptedPayload,
        ]);

        $this->assertNull($setting->payload);
    }

    public function test_setting_service_reads_requested_section_from_raw_cached_records(): void
    {
        $values = [
            'supported_countries' => ['JO'],
            'default_country' => 'JO',
            'supported_locales' => ['en', 'ar'],
            'default_locale' => 'en',
            'default_timezone' => 'Asia/Amman',
            'default_date_format' => 'Y-m-d',
            'default_time_format' => 'h:i A',
            'start_of_week' => 'sunday',
            'end_of_week' => 'saturday',
        ];
        $cachedSettings = collect($values)->map(static fn (mixed $payload): array => [
            'payload' => serialize($payload),
            'is_encryptable' => false,
            'is_translatable' => false,
        ]);
        app()->instance('setting', new SettingRepository($cachedSettings));

        $settings = app(SettingService::class)->getSettings(SettingSection::General);

        $this->assertSame($values, $settings);
    }

    public function test_setting_service_reads_passkey_configuration(): void
    {
        $values = [
            'passkeys_enabled' => true,
            'passkeys_relying_party_id' => 'app.example.com',
            'passkeys_allowed_origins' => ['https://app.example.com'],
            'passkeys_timeout' => 90000,
        ];
        $cachedSettings = collect($values)->map(static fn (mixed $payload): array => [
            'payload' => serialize($payload),
            'is_encryptable' => false,
            'is_translatable' => false,
        ]);
        app()->instance('setting', new SettingRepository($cachedSettings));

        $settings = app(SettingService::class)->getSettings(SettingSection::Passkeys);

        $this->assertSame($values, $settings);
    }

    public function test_setting_service_reads_pos_sound_configuration_with_action_defaults(): void
    {
        $values = [
            'pos_sound_enabled' => false,
            'pos_sound_volume' => 40,
            'pos_sound_item_added_enabled' => false,
            'pos_sound_item_added_sound' => 'cash',
        ];
        $cachedSettings = collect($values)->map(static fn (mixed $payload): array => [
            'payload' => serialize($payload),
            'is_encryptable' => false,
            'is_translatable' => false,
        ]);
        app()->instance('setting', new SettingRepository($cachedSettings));

        $settings = app(SettingService::class)->getSettings(SettingSection::Pos);

        $this->assertFalse($settings['pos_sound_enabled']);
        $this->assertSame(40, $settings['pos_sound_volume']);
        $this->assertFalse($settings['pos_sound_item_added_enabled']);
        $this->assertSame('cash', $settings['pos_sound_item_added_sound']);
        $this->assertTrue($settings['pos_sound_payment_completed_enabled']);
        $this->assertSame('cash', $settings['pos_sound_payment_completed_sound']);
    }

    public function test_kitchen_finished_order_visibility_defaults_to_disabled(): void
    {
        app()->instance('setting', new SettingRepository(collect()));

        $settings = app(SettingService::class)->getSettings(SettingSection::Kitchen);

        $this->assertTrue($settings['kitchen_show_served_orders_enabled']);
    }
}
