<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Branch\Models\Branch;
use Modules\User\Models\User;

return new class extends Migration
{
    /**
     * Retain one immutable managerial review per branch calendar day.
     */
    public function up(): void
    {
        Schema::create('daily_finance_day_closings', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_no', 32)->nullable()->unique('df_dc_reference_unique');
            $table->foreignIdFor(Branch::class)->constrained()->restrictOnDelete();
            $table->createdBy(false)->nullable(false)->constrained((new User)->getTable())->restrictOnDelete();
            $table->date('date');
            $table->string('timezone');
            $table->string('currency', 3);
            $table->uuid('request_key');
            $table->char('review_hash', 64);
            $table->json('snapshot');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'date'], 'day_closings_branch_date_unique');
            $table->unique(['branch_id', 'request_key'], 'day_closings_branch_request_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_finance_day_closings');
    }
};
