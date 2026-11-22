<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thai full tax invoices require the buyer's registered address, so keep
     * customer address details available for invoice party snapshots.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('address_line1')->nullable()->after('vat_tin');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city', 150)->nullable()->after('address_line2');
            $table->string('state', 150)->nullable()->after('city');
            $table->string('postal_code', 50)->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['address_line1', 'address_line2', 'city', 'state', 'postal_code']);
        });
    }
};
