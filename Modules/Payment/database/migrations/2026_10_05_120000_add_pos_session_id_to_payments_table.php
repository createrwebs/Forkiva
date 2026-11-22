<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Pos\Models\PosSession;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Historical payments remain unassigned unless independent custody evidence exists.
            $table->foreignIdFor(PosSession::class)->nullable()->constrained()->restrictOnDelete();
            $table->index(['branch_id', 'pos_session_id', 'received_at'], 'payments_branch_session_received_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_branch_session_received_index');
            $table->dropConstrainedForeignId('pos_session_id');
        });
    }
};
