<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\User\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('voided_at')
                ->nullable()
                ->after('received_at');
            $table->foreignIdFor(User::class, 'voided_by')
                ->nullable()
                ->after('voided_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('void_reason')
                ->nullable()
                ->after('voided_by');
            $table->index('voided_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['voided_at']);
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['voided_at', 'voided_by', 'void_reason']);
        });
    }
};
