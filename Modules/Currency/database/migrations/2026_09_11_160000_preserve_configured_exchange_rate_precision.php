<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preserve the eight decimal places used by frozen financial exchange rates.
     */
    public function up(): void
    {
        Schema::table('currency_rates', fn (Blueprint $table) => $table->decimal('rate', 20, 8)->nullable()->change());
    }

    /**
     * Keep precision on rollback so existing exchange rates are never rounded.
     */
    public function down(): void {}
};
