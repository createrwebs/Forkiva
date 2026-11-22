<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the creator audit relationship required for breakage batch lifecycle records.
     */
    public function up(): void
    {
        Schema::table('gift_card_breakage_batches', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('branch_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Remove the creator audit relationship when rolling back this migration.
     */
    public function down(): void
    {
        Schema::table('gift_card_breakage_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
