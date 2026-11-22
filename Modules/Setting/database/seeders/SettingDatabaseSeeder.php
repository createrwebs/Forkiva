<?php

namespace Modules\Setting\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Pos\Enums\PosSoundAction;
use Modules\Setting\Models\Setting;

class SettingDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::setMany([
            'supported_countries' => ['JO'],
            'default_country' => 'JO',
            'supported_currencies' => ['JOD'],
            'default_currency' => 'JOD',
            'supported_locales' => ['en', 'ar'],
            'default_locale' => 'en',
            'default_timezone' => 'Asia/Amman',
            'translatable' => [
                'app_name' => 'Forkiva',
            ],
            'encryptable' => [
            ],
            'default_date_format' => 'Y-m-d',
            'default_time_format' => 'h:i A',
            'start_of_week' => 'sunday',
            'end_of_week' => 'saturday',
            'table_qr_ordering_enabled' => false,
            'table_qr_auto_send_to_kitchen' => true,
            'table_qr_require_guest_count' => false,
            'table_qr_allow_order_notes' => true,
            'table_qr_allow_discount_code' => false,
            'table_qr_register_session_strategy' => 'latest_opened',
            'table_qr_cart_expires_minutes' => 120,
            'default_filesystem_disk' => 'public',
            'default_private_filesystem_disk' => 'local',
            'pdf_default_driver' => 'dompdf',
            'pdf_dompdf_remote_enabled' => false,
            'pdf_dompdf_chroot' => null,
            'pdf_browsershot_node_binary' => null,
            'pdf_browsershot_npm_binary' => null,
            'pdf_browsershot_chrome_path' => null,
            'pdf_browsershot_node_modules_path' => null,
            'pdf_browsershot_no_sandbox' => false,
            'pdf_chrome_binary' => null,
            'pdf_chrome_no_sandbox' => false,
            'pdf_chrome_startup_timeout' => 30,
            'pdf_chrome_timeout' => 30000,
            'pdf_chrome_operation_timeout' => 5000,
            'pdf_chrome_user_data_dir' => null,
            'pdf_cloudflare_account_id' => null,
            'pdf_gotenberg_url' => 'http://localhost:3000',
            'pdf_gotenberg_username' => null,
            'pdf_weasyprint_binary' => 'weasyprint',
            'pdf_weasyprint_timeout' => 10,
            'pdf_driver_verifications' => [],
            'default_theme_mode' => 'light',
            'theme_primary_color' => '#F57C00',
            'theme_secondary_color' => '#043A63',
            'theme_success_color' => '#2ecc71',
            'theme_info_color' => '#03C3EC',
            'theme_warning_color' => '#f1c40f',
            'theme_error_color' => '#e74c3c',
            'theme_dark_primary_color' => '#F6B456',
            'theme_dark_secondary_color' => '#3F6C92',
            'theme_dark_success_color' => '#58C39C',
            'theme_dark_info_color' => '#7EB9F0',
            'theme_dark_warning_color' => '#F2B24D',
            'theme_dark_error_color' => '#FF7B7B',
            'pwa_enabled' => false,
            'pwa_name' => 'Forkiva',
            'pwa_short_name' => 'Forkiva',
            'pwa_icon' => null,
            'pwa_background_color' => '#ffffff',
            'pwa_theme_color' => '#ffffff',
            'pwa_description' => 'Forkiva restaurant POS and management system.',
            'two_factor_enabled' => true,
            'two_factor_authenticator_enabled' => true,
            'two_factor_email_enabled' => false,
            'two_factor_sms_enabled' => false,
            'pos_sound_enabled' => true,
            'pos_sound_volume' => 65,
            ...collect(PosSoundAction::cases())->flatMap(fn (PosSoundAction $action) => [
                "pos_sound_{$action->value}_enabled" => true,
                "pos_sound_{$action->value}_sound" => $action->defaultSound()->value,
            ])->all(),
            'kitchen_show_served_orders_enabled' => true,
            'kitchen_show_served_orders_minutes' => 5,
            'kitchen_sound_enabled' => true,
            'kitchen_new_order_sound' => 'chime',
            'kitchen_order_updated_sound' => 'double_beep',
            'kitchen_sound_volume' => 70,
        ]);
    }
}
