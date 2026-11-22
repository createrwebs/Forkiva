<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Branch\Models\Branch;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Branch::class)->unique()->constrained()->cascadeOnDelete();
            $table->createdBy(false);
            $table->boolean('reservations_enabled')->default(true);
            $table->boolean('auto_confirm_enabled')->default(false);
            $table->unsignedInteger('default_duration_minutes')->default(90);
            $table->unsignedInteger('min_duration_minutes')->default(30);
            $table->unsignedInteger('max_duration_minutes')->default(240);
            $table->unsignedInteger('buffer_minutes')->default(15);
            $table->unsignedInteger('min_advance_minutes')->default(60);
            $table->unsignedInteger('max_advance_days')->default(30);
            $table->unsignedInteger('slot_interval_minutes')->default(15);
            $table->unsignedInteger('min_guests')->default(1);
            $table->unsignedInteger('max_guests')->default(30);
            $table->unsignedInteger('large_party_threshold')->default(8);
            $table->boolean('large_party_requires_approval')->default(true);
            $table->boolean('allow_walk_in')->default(true);
            $table->boolean('pending_blocks_capacity')->default(true);
            $table->boolean('require_table_on_create')->default(false);
            $table->boolean('require_table_on_confirm')->default(false);
            $table->boolean('require_table_on_arrival')->default(false);
            $table->boolean('require_table_on_seating')->default(true);
            $table->boolean('cancellation_allowed')->default(true);
            $table->boolean('require_cancellation_reason')->default(true);
            $table->unsignedInteger('cancellation_deadline_minutes')->nullable();
            $table->unsignedInteger('grace_period_minutes')->default(15);
            $table->boolean('auto_no_show_enabled')->default(false);
            $table->unsignedInteger('pending_expiry_minutes')->default(0);
            $table->boolean('deposit_enabled')->default(false);
            $table->boolean('deposit_required_for_large_party')->default(false);
            $table->string('deposit_type')->default('fixed');
            $table->decimal('deposit_amount', 18, 4)->default(0);
            $table->boolean('deposit_refundable')->default(true);
            $table->unsignedInteger('deposit_payment_deadline_minutes')->nullable();
            $table->boolean('reminder_enabled')->default(true);
            $table->unsignedInteger('reminder_before_minutes')->default(120);
            $table->boolean('waitlist_enabled')->default(true);
            $table->unsignedInteger('waitlist_expiry_minutes')->nullable();
            $table->json('working_hours')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_settings');
    }
};
