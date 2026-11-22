<?php

namespace Modules\Setting\Http\Requests\Api\V1;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Core\Http\Requests\Request;
use Modules\Currency\Currency;
use Modules\Currency\Enums\ExchangeService;
use Modules\Pdf\Enums\PdfDriver;
use Modules\Pos\Enums\PosSound;
use Modules\Pos\Enums\PosSoundAction;
use Modules\Setting\Enums\AutoRefreshMode;
use Modules\Setting\Enums\SettingSection;
use Modules\Support\Country;
use Modules\Support\DateFormats;
use Modules\Support\Enums\Day;
use Modules\Support\Enums\FilesystemDisk;
use Modules\Support\Enums\Frequency;
use Modules\Support\Locale;
use Modules\Support\Security\PasskeyConfigurationValidator;
use Modules\Support\TimeFormats;
use Modules\Support\TimeZone;

/**
 * @property SettingSection $section
 */
class SaveSettingRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return match ($this->section) {
            SettingSection::General => [
                'supported_countries' => 'required|array',
                'supported_countries.*' => ['required', Rule::in(Country::codes())],
                'default_country' => 'required|in_array:supported_countries.*',
                'supported_locales' => 'required|array',
                'supported_locales.*' => ['required', Rule::in(Locale::codes())],
                'default_locale' => 'required|in_array:supported_locales.*',
                'default_timezone' => ['required', Rule::in(TimeZone::keys())],
                'default_date_format' => ['required', Rule::in(DateFormats::keys())],
                'default_time_format' => ['required', Rule::in(TimeFormats::keys())],
                'start_of_week' => ['required', Rule::enum(Day::class)],
                'end_of_week' => ['required', Rule::enum(Day::class)],
            ],
            SettingSection::TableQrOrdering => [
                'table_qr_ordering_enabled' => 'required|boolean',
                'table_qr_auto_send_to_kitchen' => 'required|boolean',
                'table_qr_require_guest_count' => 'required|boolean',
                'table_qr_allow_order_notes' => 'required|boolean',
                'table_qr_allow_discount_code' => 'required|boolean',
                'table_qr_register_session_strategy' => ['required', Rule::in(['latest_opened', 'earliest_opened'])],
                'table_qr_cart_expires_minutes' => 'required|integer|min:5|max:1440',
            ],
            SettingSection::Application => [
                ...$this->getTranslationRules(['app_name' => 'required|min:1|max:200']),
            ],
            SettingSection::Currency => [
                'supported_currencies' => 'required|array',
                'supported_currencies.*' => ['required', Rule::in(Currency::codes())],
                'default_currency' => 'required|in_array:supported_currencies.*',
                'currency_rate_exchange_service' => ['nullable', Rule::enum(ExchangeService::class)],
                'fixer_access_key' => 'required_if:currency_rate_exchange_service,fixer',
                'forge_api_key' => 'required_if:currency_rate_exchange_service,forge',
                'currency_data_feed_api_key' => 'required_if:currency_rate_exchange_service,currency_data_feed',
                'auto_refresh_currency_rates' => 'required|boolean',
                'auto_refresh_currency_rate_frequency' => ['required_if:auto_refresh_currency_rates,1', 'nullable', Rule::enum(Frequency::class)],
            ],
            SettingSection::Filesystem => [
                'default_filesystem_disk' => ['required', Rule::enum(FilesystemDisk::class)],
                'default_private_filesystem_disk' => ['required', Rule::in(['local', 'public', 's3'])],
                'filesystem_s3_public_use_path_style_endpoint' => 'required_if:default_filesystem_disk,s3|nullable|boolean',
                'filesystem_s3_public_url' => 'required_if:default_filesystem_disk,s3|nullable|string|url',
                'filesystem_s3_public_endpoint' => 'required_if:default_filesystem_disk,s3|nullable|string',
                'filesystem_s3_public_region' => 'required_if:default_filesystem_disk,s3|nullable|string',
                'filesystem_s3_public_bucket' => 'required_if:default_filesystem_disk,s3|nullable|string',
                'encryptable.filesystem_s3_public_key' => 'required_if:default_filesystem_disk,s3|nullable|string',
                'encryptable.filesystem_s3_public_secret' => 'required_if:default_filesystem_disk,s3|nullable|string',
                'filesystem_s3_private_use_path_style_endpoint' => 'required_if:default_private_filesystem_disk,s3|nullable|boolean',
                'filesystem_s3_private_url' => 'required_if:default_private_filesystem_disk,s3|nullable|string|url',
                'filesystem_s3_private_endpoint' => 'required_if:default_private_filesystem_disk,s3|nullable|string',
                'filesystem_s3_private_region' => 'required_if:default_private_filesystem_disk,s3|nullable|string',
                'filesystem_s3_private_bucket' => 'required_if:default_private_filesystem_disk,s3|nullable|string',
                'encryptable.filesystem_s3_private_key' => 'required_if:default_private_filesystem_disk,s3|nullable|string',
                'encryptable.filesystem_s3_private_secret' => 'required_if:default_private_filesystem_disk,s3|nullable|string',
            ],
            SettingSection::Pdf => $this->pdfRules(),
            SettingSection::Logo => [
                'logo' => 'bail|present|nullable|integer|exists:media,id',
                'email_logo' => [
                    'bail', 'sometimes', 'nullable', 'integer',
                    Rule::exists('media', 'id')->whereIn('mime_type', ['image/png', 'image/jpeg', 'image/gif']),
                ],
                'favicon' => 'bail|present|nullable|integer|exists:media,id',
            ],
            SettingSection::Appearance => [
                'default_theme_mode' => ['required', Rule::in(['light', 'dark', 'system'])],
                'theme_primary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                'theme_secondary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                'theme_success_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                'theme_info_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                'theme_warning_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                'theme_error_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                'theme_dark_primary_color' => ['sometimes', 'required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                'theme_dark_secondary_color' => ['sometimes', 'required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                'theme_dark_success_color' => ['sometimes', 'required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                'theme_dark_info_color' => ['sometimes', 'required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                'theme_dark_warning_color' => ['sometimes', 'required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                'theme_dark_error_color' => ['sometimes', 'required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            ],
            SettingSection::Pos => [
                'pos_sound_enabled' => 'required|boolean',
                'pos_sound_volume' => 'required|integer|min:0|max:100',
                ...collect(PosSoundAction::cases())
                    ->flatMap(fn (PosSoundAction $action) => [
                        "pos_sound_{$action->value}_enabled" => 'required|boolean',
                        "pos_sound_{$action->value}_sound" => [
                            "required_if:pos_sound_{$action->value}_enabled,true",
                            'nullable',
                            Rule::enum(PosSound::class),
                        ],
                    ])->all(),
            ],
            SettingSection::Kitchen => [
                'auto_refresh_enabled' => 'required|boolean',
                'auto_refresh_mode' => ['required_if:auto_refresh_enabled,true', 'nullable', Rule::enum(AutoRefreshMode::class)],
                'auto_refresh_interval' => 'required_if:auto_refresh_mode,smart_polling|nullable|integer|min:1000|max:600000',
                'auto_refresh_pause_on_idle' => 'required_if:auto_refresh_mode,smart_polling|boolean',
                'auto_refresh_idle_timeout' => 'required_if:auto_refresh_pause_on_idle,true|nullable|integer|min:10|max:3600',
                'kitchen_show_served_orders_enabled' => 'required|boolean',
                'kitchen_show_served_orders_minutes' => 'required_if:kitchen_show_served_orders_enabled,true|nullable|integer|min:1|max:20',
                'kitchen_sound_enabled' => 'required|boolean',
                'kitchen_new_order_sound' => ['required_if:kitchen_sound_enabled,true', 'nullable', Rule::in(['chime', 'bell', 'double_beep', 'soft_alert'])],
                'kitchen_order_updated_sound' => ['required_if:kitchen_sound_enabled,true', 'nullable', Rule::in(['chime', 'bell', 'double_beep', 'soft_alert'])],
                'kitchen_sound_volume' => 'required_if:kitchen_sound_enabled,true|nullable|integer|min:0|max:100',
            ],
            SettingSection::Pwa => [
                'pwa_enabled' => 'required|boolean',
                'pwa_name' => 'required_if:pwa_enabled,1|nullable|string|min:1|max:100',
                'pwa_short_name' => 'required_if:pwa_enabled,1|nullable|string|min:1|max:30',
                'pwa_icon' => 'required_if:pwa_enabled,1|nullable|integer|exists:media,id',
                'pwa_background_color' => ['required_if:pwa_enabled,1', 'nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                'pwa_theme_color' => ['required_if:pwa_enabled,1', 'nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                'pwa_description' => 'nullable|string|max:500',
            ],
            SettingSection::Passkeys => [
                'passkeys_enabled' => 'required|boolean',
                'passkeys_relying_party_id' => [
                    'required_if:passkeys_enabled,1',
                    'nullable',
                    'string',
                    'max:253',
                    'regex:/^(?:localhost|(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?|(?:\d{1,3}\.){3}\d{1,3})$/i',
                ],
                'passkeys_allowed_origins' => 'required_if:passkeys_enabled,1|array|min:1|max:10',
                'passkeys_allowed_origins.*' => [
                    'required',
                    'string',
                    'url:http,https',
                    'max:2048',
                    'distinct',
                    'regex:#^https?://[a-z0-9.-]+(?::[0-9]{1,5})?$#i',
                ],
                'passkeys_timeout' => 'required|integer|min:15000|max:300000',
            ],
            SettingSection::PasswordRecovery => [
                'password_recovery_enabled' => 'required|boolean',
                'password_recovery_email_enabled' => 'required|boolean',
                'password_recovery_sms_enabled' => 'required|boolean',
            ],
            SettingSection::TwoFactor => [
                'two_factor_enabled' => 'required|boolean',
                'two_factor_authenticator_enabled' => 'required|boolean',
                'two_factor_email_enabled' => 'required|boolean',
                'two_factor_sms_enabled' => 'required|boolean',
            ],
        };
    }

    /**
     * Validate only configuration belonging to the selected PDF driver.
     */
    private function pdfRules(): array
    {
        $rules = [
            'pdf_default_driver' => ['bail', 'required', Rule::enum(PdfDriver::class)],
        ];

        return [
            ...$rules,
            ...match (PdfDriver::tryFrom((string) $this->input('pdf_default_driver'))) {
                PdfDriver::Dompdf => [
                    'pdf_dompdf_remote_enabled' => ['required', 'boolean'],
                    'pdf_dompdf_chroot' => ['nullable', 'string', 'max:2048'],
                ],
                PdfDriver::Browsershot => [
                    'pdf_browsershot_node_binary' => ['nullable', 'string', 'max:2048'],
                    'pdf_browsershot_npm_binary' => ['nullable', 'string', 'max:2048'],
                    'pdf_browsershot_chrome_path' => ['nullable', 'string', 'max:2048'],
                    'pdf_browsershot_node_modules_path' => ['nullable', 'string', 'max:2048'],
                    'pdf_browsershot_no_sandbox' => ['required', 'boolean'],
                ],
                PdfDriver::Chrome => [
                    'pdf_chrome_binary' => ['nullable', 'string', 'max:2048'],
                    'pdf_chrome_no_sandbox' => ['required', 'boolean'],
                    'pdf_chrome_startup_timeout' => ['required', 'integer', 'min:1', 'max:300'],
                    'pdf_chrome_timeout' => ['required', 'integer', 'min:1000', 'max:300000'],
                    'pdf_chrome_operation_timeout' => ['required', 'integer', 'min:1000', 'max:300000'],
                    'pdf_chrome_user_data_dir' => ['nullable', 'string', 'max:2048'],
                ],
                PdfDriver::Cloudflare => [
                    'pdf_cloudflare_account_id' => ['required', 'string', 'max:100'],
                    'encryptable.pdf_cloudflare_api_token' => [
                        Rule::requiredIf(fn (): bool => ! filled(setting(
                            'pdf_cloudflare_api_token',
                            config('pdf.drivers.cloudflare.api_token'),
                        ))),
                        'nullable',
                        'string',
                        'max:4096',
                    ],
                    'pdf_clear_secrets.cloudflare_api_token' => [
                        'required',
                        'boolean',
                        Rule::in($this->filled('encryptable.pdf_cloudflare_api_token') ? [false, true] : [false]),
                    ],
                ],
                PdfDriver::Gotenberg => [
                    'pdf_gotenberg_url' => ['required', 'string', 'url:http,https', 'max:2048'],
                    'pdf_gotenberg_username' => ['nullable', 'string', 'max:255'],
                    'encryptable.pdf_gotenberg_password' => ['nullable', 'string', 'max:4096'],
                    'pdf_clear_secrets.gotenberg_password' => ['required', 'boolean'],
                ],
                PdfDriver::Weasyprint => [
                    'pdf_weasyprint_binary' => ['required', 'string', 'max:2048'],
                    'pdf_weasyprint_timeout' => ['required', 'integer', 'min:1', 'max:300'],
                ],
                null => [],
            },
        ];
    }

    /** {@inheritDoc} */
    public function attributes(): array
    {
        return Arr::dot(parent::attributes());
    }

    /** {@inheritDoc} */
    public function messages(): array
    {
        return [
            'pdf_clear_secrets.cloudflare_api_token.in' => __('pdf::settings.messages.cannot_clear_active_cloudflare_token'),
        ];
    }

    /**
     * Configure section-specific validation after the base rules run.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->section === SettingSection::Passkeys
                    && $this->boolean('passkeys_enabled')
                ) {
                    $relyingPartyId = strtolower((string) $this->input('passkeys_relying_party_id'));
                    $origins = $this->input('passkeys_allowed_origins');

                    if (is_array($origins)) {
                        foreach ($origins as $index => $origin) {
                            if (! is_string($origin)) {
                                continue;
                            }
                            if (! PasskeyConfigurationValidator::secureOrigin($origin)) {
                                $validator->errors()->add(
                                    "passkeys_allowed_origins.$index",
                                    __('setting::settings.passkeys_secure_origin_required'),
                                );
                            } elseif (! PasskeyConfigurationValidator::originMatchesRelyingParty($origin, $relyingPartyId)) {
                                $validator->errors()->add(
                                    "passkeys_allowed_origins.$index",
                                    __('setting::settings.passkeys_origin_rp_mismatch'),
                                );
                            }
                        }
                    }
                }

                if ($this->section === SettingSection::PasswordRecovery && $this->boolean('password_recovery_enabled')
                    && ! $this->boolean('password_recovery_email_enabled') && ! $this->boolean('password_recovery_sms_enabled')) {
                    $validator->errors()->add('password_recovery_enabled', __('setting::settings.recovery_method_required'));
                }

                if (
                    $this->section === SettingSection::TwoFactor
                    && $this->boolean('two_factor_enabled')
                    && ! $this->boolean('two_factor_authenticator_enabled')
                    && ! $this->boolean('two_factor_email_enabled')
                    && ! $this->boolean('two_factor_sms_enabled')
                ) {
                    $validator->errors()->add(
                        'two_factor_enabled',
                        __('setting::settings.two_factor_method_required'),
                    );
                }
            },
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'setting::attributes.settings';
    }
}
