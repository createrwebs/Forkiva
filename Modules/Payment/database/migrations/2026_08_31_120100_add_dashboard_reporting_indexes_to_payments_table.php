<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->index(
                ['created_at', 'branch_id', 'voided_at', 'method'],
                'payments_dashboard_created_branch_voided_method_idx',
            );
            $table->index(
                ['branch_id', 'created_at', 'voided_at', 'method'],
                'payments_dashboard_branch_created_voided_method_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_dashboard_created_branch_voided_method_idx');
            $table->dropIndex('payments_dashboard_branch_created_voided_method_idx');
        });
    }
};
