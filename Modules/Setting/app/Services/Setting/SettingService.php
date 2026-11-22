<?php

namespace Modules\Setting\Services\Setting;

use App\Forkiva;
use Modules\Core\Support\ModuleFeature;
use Modules\Currency\Currency;
use Modules\Currency\Enums\ExchangeService;
use Modules\Media\Models\Media;
use Modules\Pdf\Services\Settings\PdfSettingsManagerInterface;
use Modules\Pos\Enums\PosSound;
use Modules\Pos\Enums\PosSoundAction;
use Modules\Setting\Enums\AutoRefreshMode;
use Modules\Setting\Enums\SettingSection;
use Modules\Setting\Models\Setting;
use Modules\Setting\Repositories\SettingRepository;
use Modules\Support\Country;
use Modules\Support\DateFormats;
use Modules\Support\Enums\Day;
use Modules\Support\Enums\FilesystemDisk;
use Modules\Support\Enums\Frequency;
use Modules\Support\Locale;
use Modules\Support\RTLDetector;
use Modules\Support\TimeFormats;
use Modules\Support\TimeZone;
use Storage;

class SettingService implements SettingServiceInterface
{
    /** {@inheritDoc} */
    public function label(): string
    {
        return __('setting::settings.settings.setting');
    }

    /** {@inheritDoc} */
    public function getModel(): Setting
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Setting::class;
    }

    /** {@inheritDoc} */
    public function getAppSettings(bool $refresh = false): array
    {
        if ($refresh) {
            $this->refreshSettingBinding();
        }

        return [
            'supported_locales' => supportedLocaleKeys(),
            'supported_languages' => supportedLanguages(),
            'locale' => setting('default_locale'),
            'default_time_format' => setting('default_time_format', 'h:i A'),
            'start_of_week' => setting('start_of_week', Day::Sunday->value),
            'end_of_week' => setting('end_of_week', Day::Saturday->value),
            'fallback_locale' => fallbackLocale(),
            'is_rtl' => RTLDetector::detect(),
            'timezone' => setting('default_timezone'),
            'currency' => setting('default_currency'),
            'app_name' => setting('app_name', env('APP_NAME')),
            'logo' => Media::getCacheMedia(setting('logo'))?->url ?: asset('logo.png'),
            'logo_data_base64' => getLogoBase64(),
            'favicon' => Media::getCacheMedia(setting('favicon'))?->url ?: asset('logo.png'),
            'default_theme_mode' => (string) setting('default_theme_mode', 'light'),
            'theme_primary_color' => (string) setting('theme_primary_color', '#F57C00'),
            'theme_secondary_color' => (string) setting('theme_secondary_color', '#043A63'),
            'theme_success_color' => (string) setting('theme_success_color', '#2ecc71'),
            'theme_info_color' => (string) setting('theme_info_color', '#03C3EC'),
            'theme_warning_color' => (string) setting('theme_warning_color', '#f1c40f'),
            'theme_error_color' => (string) setting('theme_error_color', '#e74c3c'),
            'theme_dark_primary_color' => (string) setting('theme_dark_primary_color', setting('theme_primary_color', '#F6B456')),
            'theme_dark_secondary_color' => (string) setting('theme_dark_secondary_color', setting('theme_secondary_color', '#3F6C92')),
            'theme_dark_success_color' => (string) setting('theme_dark_success_color', setting('theme_success_color', '#58C39C')),
            'theme_dark_info_color' => (string) setting('theme_dark_info_color', setting('theme_info_color', '#7EB9F0')),
            'theme_dark_warning_color' => (string) setting('theme_dark_warning_color', setting('theme_warning_color', '#F2B24D')),
            'theme_dark_error_color' => (string) setting('theme_dark_error_color', setting('theme_error_color', '#FF7B7B')),
            'pwa_enabled' => (bool) setting('pwa_enabled', false),
            'pwa_name' => (string) setting('pwa_name', setting('app_name', 'Forkiva')),
            'pwa_short_name' => (string) setting('pwa_short_name', setting('app_name', 'Forkiva')),
            'pwa_description' => (string) setting('pwa_description', 'Forkiva restaurant POS and management system.'),
            'pwa_background_color' => (string) setting('pwa_background_color', '#ffffff'),
            'pwa_theme_color' => (string) setting('pwa_theme_color', '#ffffff'),
            'pwa_icon' => Media::getCacheMedia(setting('pwa_icon'))?->url ?: (Media::getCacheMedia(setting('logo'))?->url ?: asset('logo.png')),
            'passkeys_enabled' => (bool) setting('passkeys_enabled', config('passkeys.enabled', true)),
            'two_factor_enabled' => (bool) setting('two_factor_enabled', false),
            'features' => [
                'delivery' => [
                    'enabled' => ModuleFeature::enabled('Delivery'),
                ],
                'accounting' => [
                    'enabled' => ModuleFeature::enabled('Accounting'),
                ],
                'hr' => [
                    'enabled' => ModuleFeature::enabled('Hr'),
                ],
            ],
        ];
    }

    /** {@inheritDoc} */
    public function refreshSettingBinding(): void
    {
        app()->forgetInstance('setting');
        app()->singleton('setting', fn () => new SettingRepository(Setting::allCached()));
    }

    /** {@inheritDoc} */
    public function getAppSettingsVersion(): string
    {
        $snapshot = Setting::query()
            ->selectRaw('COUNT(*) as total_settings, MAX(updated_at) as latest_updated_at')
            ->first();

        return sha1(json_encode([
            'total' => (int) ($snapshot?->total_settings ?? 0),
            'updated_at' => $snapshot?->latest_updated_at,
            'app_version' => Forkiva::$version,
            'locale' => locale(),
        ]));
    }

    /** {@inheritDoc} */
    public function getSettings(SettingSection $section): array
    {
        $settings = setting();

        return match ($section) {
            SettingSection::General => [
                'supported_countries' => $settings['supported_countries'],
                'default_country' => $settings['default_country'],
                'supported_locales' => $settings['supported_locales'],
                'default_locale' => $settings['default_locale'],
                'default_timezone' => $settings['default_timezone'],
                'default_date_format' => $settings['default_date_format'],
                'default_time_format' => $settings['default_time_format'],
                'start_of_week' => $settings['start_of_week'],
                'end_of_week' => $settings['end_of_week'],
            ],
            SettingSection::TableQrOrdering => [
                'table_qr_ordering_enabled' => $settings['table_qr_ordering_enabled'] ?? false,
                'table_qr_auto_send_to_kitchen' => $settings['table_qr_auto_send_to_kitchen'] ?? true,
                'table_qr_require_guest_count' => $settings['table_qr_require_guest_count'] ?? false,
                'table_qr_allow_order_notes' => $settings['table_qr_allow_order_notes'] ?? true,
                'table_qr_allow_discount_code' => $settings['table_qr_allow_discount_code'] ?? false,
                'table_qr_register_session_strategy' => $settings['table_qr_register_session_strategy'] ?? 'latest_opened',
                'table_qr_cart_expires_minutes' => $settings['table_qr_cart_expires_minutes'] ?? 120,
            ],
            SettingSection::Application => [
                'app_name' => Setting::decodeStoredPayload(
                    Setting::where('key', 'app_name')->first()?->getRawOriginal('payload'),
                    false
                ),
            ],
            SettingSection::Currency => [
                'supported_currencies' => $settings['supported_currencies'],
                'default_currency' => $settings['default_currency'],
                'currency_rate_exchange_service' => $settings['currency_rate_exchange_service'] ?? null,
                'forge_api_key' => $settings['forge_api_key'] ?? null,
                'fixer_access_key' => $settings['fixer_access_key'] ?? null,
                'currency_data_feed_api_key' => $settings['currency_data_feed_api_key'] ?? null,
                'auto_refresh_currency_rates' => $settings['auto_refresh_currency_rates'] ?? false,
                'auto_refresh_currency_rate_frequency' => $settings['auto_refresh_currency_rate_frequency'] ?? null,
            ],
            SettingSection::Filesystem => [
                'default_filesystem_disk' => $settings['default_filesystem_disk'] ?? config('filesystems.default'),
                'default_private_filesystem_disk' => $settings['default_private_filesystem_disk'] ?? config('filesystems.default_private'),
                'filesystem_s3_public_use_path_style_endpoint' => $settings['filesystem_s3_public_use_path_style_endpoint'] ?? $settings['filesystem_s3_use_path_style_endpoint'] ?? null,
                'filesystem_s3_public_url' => $settings['filesystem_s3_public_url'] ?? $settings['filesystem_s3_url'] ?? null,
                'filesystem_s3_public_endpoint' => $settings['filesystem_s3_public_endpoint'] ?? $settings['filesystem_s3_endpoint'] ?? null,
                'filesystem_s3_public_region' => $settings['filesystem_s3_public_region'] ?? $settings['filesystem_s3_region'] ?? null,
                'filesystem_s3_public_bucket' => $settings['filesystem_s3_public_bucket'] ?? $settings['filesystem_s3_bucket'] ?? null,
                'filesystem_s3_private_use_path_style_endpoint' => $settings['filesystem_s3_private_use_path_style_endpoint'] ?? $settings['filesystem_s3_use_path_style_endpoint'] ?? null,
                'filesystem_s3_private_url' => $settings['filesystem_s3_private_url'] ?? $settings['filesystem_s3_url'] ?? null,
                'filesystem_s3_private_endpoint' => $settings['filesystem_s3_private_endpoint'] ?? $settings['filesystem_s3_endpoint'] ?? null,
                'filesystem_s3_private_region' => $settings['filesystem_s3_private_region'] ?? $settings['filesystem_s3_region'] ?? null,
                'filesystem_s3_private_bucket' => $settings['filesystem_s3_private_bucket'] ?? $settings['filesystem_s3_bucket'] ?? null,
                'encryptable' => [
                    'filesystem_s3_public_key' => $settings['filesystem_s3_public_key'] ?? $settings['filesystem_s3_key'] ?? null,
                    'filesystem_s3_public_secret' => $settings['filesystem_s3_public_secret'] ?? $settings['filesystem_s3_secret'] ?? null,
                    'filesystem_s3_private_key' => $settings['filesystem_s3_private_key'] ?? $settings['filesystem_s3_key'] ?? null,
                    'filesystem_s3_private_secret' => $settings['filesystem_s3_private_secret'] ?? $settings['filesystem_s3_secret'] ?? null,
                ],
            ],
            SettingSection::Pdf => app(PdfSettingsManagerInterface::class)->settings(),
            SettingSection::Logo => [
                'logo' => Media::getCacheMedia($settings['logo'] ?? null, true),
                'email_logo' => Media::getCacheMedia($settings['email_logo'] ?? null, true),
                'favicon' => Media::getCacheMedia($settings['favicon'] ?? null, true),
            ],
            SettingSection::Appearance => [
                'default_theme_mode' => $settings['default_theme_mode'] ?? 'light',
                'theme_primary_color' => $settings['theme_primary_color'] ?? '#F57C00',
                'theme_secondary_color' => $settings['theme_secondary_color'] ?? '#043A63',
                'theme_success_color' => $settings['theme_success_color'] ?? '#2ecc71',
                'theme_info_color' => $settings['theme_info_color'] ?? '#03C3EC',
                'theme_warning_color' => $settings['theme_warning_color'] ?? '#f1c40f',
                'theme_error_color' => $settings['theme_error_color'] ?? '#e74c3c',
                'theme_dark_primary_color' => $settings['theme_dark_primary_color'] ?? ($settings['theme_primary_color'] ?? '#F6B456'),
                'theme_dark_secondary_color' => $settings['theme_dark_secondary_color'] ?? ($settings['theme_secondary_color'] ?? '#3F6C92'),
                'theme_dark_success_color' => $settings['theme_dark_success_color'] ?? ($settings['theme_success_color'] ?? '#58C39C'),
                'theme_dark_info_color' => $settings['theme_dark_info_color'] ?? ($settings['theme_info_color'] ?? '#7EB9F0'),
                'theme_dark_warning_color' => $settings['theme_dark_warning_color'] ?? ($settings['theme_warning_color'] ?? '#F2B24D'),
                'theme_dark_error_color' => $settings['theme_dark_error_color'] ?? ($settings['theme_error_color'] ?? '#FF7B7B'),
            ],
            SettingSection::Pos => [
                'pos_sound_enabled' => $settings['pos_sound_enabled'] ?? true,
                'pos_sound_volume' => $settings['pos_sound_volume'] ?? 65,
                ...collect(PosSoundAction::cases())
                    ->flatMap(fn (PosSoundAction $action) => [
                        "pos_sound_{$action->value}_enabled" => $settings["pos_sound_{$action->value}_enabled"] ?? true,
                        "pos_sound_{$action->value}_sound" => $settings["pos_sound_{$action->value}_sound"] ?? $action->defaultSound()->value,
                    ])->all(),
            ],
            SettingSection::Kitchen => [
                'auto_refresh_enabled' => $settings['auto_refresh_enabled'] ?? false,
                'auto_refresh_mode' => $settings['auto_refresh_mode'] ?? null,
                'auto_refresh_interval' => $settings['auto_refresh_interval'] ?? null,
                'auto_refresh_pause_on_idle' => $settings['auto_refresh_pause_on_idle'] ?? false,
                'auto_refresh_idle_timeout' => $settings['auto_refresh_idle_timeout'] ?? null,
                'kitchen_show_served_orders_enabled' => $settings['kitchen_show_served_orders_enabled'] ?? true,
                'kitchen_show_served_orders_minutes' => $settings['kitchen_show_served_orders_minutes'] ?? 5,
                'kitchen_sound_enabled' => $settings['kitchen_sound_enabled'] ?? true,
                'kitchen_new_order_sound' => $settings['kitchen_new_order_sound'] ?? 'chime',
                'kitchen_order_updated_sound' => $settings['kitchen_order_updated_sound'] ?? 'double_beep',
                'kitchen_sound_volume' => $settings['kitchen_sound_volume'] ?? 70,
            ],
            SettingSection::Pwa => [
                'pwa_enabled' => (bool) ($settings['pwa_enabled'] ?? false),
                'pwa_name' => $settings['pwa_name'] ?? 'Forkiva',
                'pwa_short_name' => $settings['pwa_short_name'] ?? 'Forkiva',
                'pwa_icon' => Media::getCacheMedia($settings['pwa_icon'] ?? null, true),
                'pwa_background_color' => $settings['pwa_background_color'] ?? '#ffffff',
                'pwa_theme_color' => $settings['pwa_theme_color'] ?? '#ffffff',
                'pwa_description' => $settings['pwa_description'] ?? null,
            ],
            SettingSection::Passkeys => [
                'passkeys_enabled' => (bool) ($settings['passkeys_enabled'] ?? config('passkeys.enabled', true)),
                'passkeys_relying_party_id' => $settings['passkeys_relying_party_id'] ?? config('passkeys.relying_party_id'),
                'passkeys_allowed_origins' => $settings['passkeys_allowed_origins'] ?? config('passkeys.allowed_origins', []),
                'passkeys_timeout' => (int) ($settings['passkeys_timeout'] ?? config('passkeys.timeout', 60000)),
            ],
            SettingSection::PasswordRecovery => [
                'password_recovery_enabled' => (bool) ($settings['password_recovery_enabled'] ?? true),
                'password_recovery_email_enabled' => (bool) ($settings['password_recovery_email_enabled'] ?? true),
                'password_recovery_sms_enabled' => (bool) ($settings['password_recovery_sms_enabled'] ?? true),
                'providers' => [
                    'email' => filter_var(setting('services.notifications.email.enabled', false), FILTER_VALIDATE_BOOLEAN),
                    'sms' => filter_var(setting('services.notifications.sms.enabled', false), FILTER_VALIDATE_BOOLEAN),
                ],
            ],
            SettingSection::TwoFactor => [
                'two_factor_enabled' => (bool) ($settings['two_factor_enabled'] ?? false),
                'two_factor_authenticator_enabled' => (bool) ($settings['two_factor_authenticator_enabled'] ?? true),
                'two_factor_email_enabled' => (bool) ($settings['two_factor_email_enabled'] ?? false),
                'two_factor_sms_enabled' => (bool) ($settings['two_factor_sms_enabled'] ?? false),
            ],
        };
    }

    /** {@inheritDoc} */
    public function getMeta(SettingSection $section): array
    {
        return match ($section) {
            SettingSection::General => [
                'countries' => Country::toList(),
                'locales' => Locale::toList(),
                'timezones' => TimeZone::toList(),
                'date_formats' => DateFormats::toList(),
                'time_formats' => TimeFormats::toList(),
                'days' => Day::toArrayTrans(),
            ],
            SettingSection::TableQrOrdering => [
                'table_qr_register_session_strategies' => [
                    [
                        'id' => 'latest_opened',
                        'name' => __('setting::settings.table_qr_session_strategies.latest_opened'),
                    ],
                    [
                        'id' => 'earliest_opened',
                        'name' => __('setting::settings.table_qr_session_strategies.earliest_opened'),
                    ],
                ],
            ],
            SettingSection::Currency => [
                'currencies' => Currency::toList(),
                'frequencies' => Frequency::toArrayTrans(),
                'exchange_services' => ExchangeService::toArrayTrans(),
            ],
            SettingSection::Filesystem => [
                'disks' => FilesystemDisk::toArrayTrans(),
                'private_disks' => [
                    ['id' => 'local', 'name' => __('support::enums.private_filesystem_disks.local')],
                    ['id' => 'public', 'name' => __('support::enums.private_filesystem_disks.public')],
                    ['id' => 's3', 'name' => __('support::enums.private_filesystem_disks.s3')],
                ],
            ],
            SettingSection::Pdf => app(PdfSettingsManagerInterface::class)->meta(),
            SettingSection::Appearance => [
                'theme_modes' => [
                    ['id' => 'light', 'name' => __('admin::navbar.theme_modes.light')],
                    ['id' => 'dark', 'name' => __('admin::navbar.theme_modes.dark')],
                    ['id' => 'system', 'name' => __('admin::navbar.theme_modes.system')],
                ],
            ],
            SettingSection::Pos => [
                'sounds' => collect(PosSound::cases())
                    ->map(fn (PosSound $sound) => [
                        'id' => $sound->value,
                        'name' => __("setting::settings.pos_sounds.{$sound->value}"),
                    ])->all(),
            ],
            SettingSection::Kitchen => [
                'modes' => AutoRefreshMode::toArrayTrans(),
                'sounds' => [
                    ['id' => 'chime', 'name' => __('setting::settings.kitchen_sounds.chime')],
                    ['id' => 'bell', 'name' => __('setting::settings.kitchen_sounds.bell')],
                    ['id' => 'double_beep', 'name' => __('setting::settings.kitchen_sounds.double_beep')],
                    ['id' => 'soft_alert', 'name' => __('setting::settings.kitchen_sounds.soft_alert')],
                ],
            ],
            default => []
        };
    }

    /** {@inheritDoc} */
    public function update(SettingSection $section, array $data): void
    {
        if ($section === SettingSection::Pdf) {
            app(PdfSettingsManagerInterface::class)->update($data);

            return;
        }

        if ($section === SettingSection::Logo) {
            foreach (['logo', 'email_logo'] as $key) {
                if (! array_key_exists($key, $data) || (int) setting($key) === (int) $data[$key]) {
                    continue;
                }

                $logo = Media::getCacheMedia($data[$key]);
                $disk = Storage::disk(config('filesystems.default_private'));
                if ($logo) {
                    $disk->put($key.'.base64', base64_encode($logo->getContent()));
                } else {
                    $disk->delete($key.'.base64');
                }
                $data[$key.'_mime_type'] = $logo?->mime_type;
            }
        }
        setting($data);
    }
}
