<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Thai tax-invoice branch coding and PromptPay collection identity.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('tax_branch_code', 5)->nullable()->after('vat_tin');
            $table->string('promptpay_id', 20)->nullable()->after('tax_branch_code');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['tax_branch_code', 'promptpay_id']);
        });
    }
};
