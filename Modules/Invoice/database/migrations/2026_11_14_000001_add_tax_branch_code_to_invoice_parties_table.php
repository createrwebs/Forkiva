<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot the seller tax branch code so issued invoices keep Thai
     * head-office/branch identification even after branch edits.
     */
    public function up(): void
    {
        Schema::table('invoice_parties', function (Blueprint $table): void {
            $table->string('tax_branch_code', 5)->nullable()->after('vat_tin');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_parties', function (Blueprint $table): void {
            $table->dropColumn('tax_branch_code');
        });
    }
};
