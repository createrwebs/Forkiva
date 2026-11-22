<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Enums\InventoryCountStatus;
use Modules\User\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->branch();
            $table->createdBy();
            $table->string('reference_number')->nullable();
            $table->date('count_date');
            $table->enum('status', InventoryCountStatus::values())->default(InventoryCountStatus::Draft->value);
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'approved_by')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignIdFor(User::class, 'cancelled_by')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'reference_number'], 'inv_counts_branch_ref_unique');
            $table->index(['branch_id', 'status', 'count_date'], 'inv_counts_branch_status_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_counts');
    }
};
