<?php

namespace Modules\Pdf\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Setting\Models\Setting;

class PdfDatabaseSeeder extends Seeder
{
    /**
     * Seed default PDF driver settings.
     */
    public function run(): void
    {
        Setting::setMany([
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
        ]);
    }
}
