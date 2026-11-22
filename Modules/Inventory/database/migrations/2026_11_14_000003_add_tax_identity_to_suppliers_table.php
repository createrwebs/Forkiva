<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thai purchase VAT reports (รายงานภาษีซื้อ) require the supplier tax identity,
     * so keep the seller tax ID and branch code on the supplier record.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('vat_tin', 120)->nullable()->after('address');
            $table->string('tax_branch_code', 5)->nullable()->after('vat_tin');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['vat_tin', 'tax_branch_code']);
        });
    }
};
