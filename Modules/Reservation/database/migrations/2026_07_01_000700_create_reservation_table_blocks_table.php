<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\SeatingPlan\Models\Table;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservation_table_blocks', function (Blueprint $table) {
            $table->id();
            $table->branch(false);
            $table->foreignIdFor(Table::class)->nullable()->constrained('tables')->nullOnDelete();
            $table->json('title');
            $table->text('reason')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->createdBy(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index('branch_id');
            $table->index('table_id');
            $table->index(['start_at', 'end_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_table_blocks');
    }
};
