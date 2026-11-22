<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Reservation\Enums\ReservationPaymentStatus;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Enums\ReservationType;
use Modules\Reservation\Enums\TableAssignmentMode;
use Modules\Reservation\Enums\TableAssignmentStatus;
use Modules\User\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->branch(false);
            $table->createdBy(false);
            $table->foreignIdFor(User::class, 'customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reservation_number', 50)->unique();
            $table->string('reservation_type', 50)->default(ReservationType::Standard->value);
            $table->string('status', 50)->default(ReservationStatus::Pending->value);
            $table->string('table_assignment_status', 50)->default(TableAssignmentStatus::Unassigned->value);
            $table->string('table_assignment_mode', 50)->default(TableAssignmentMode::AssignLater->value);
            $table->date('reservation_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->unsignedInteger('duration_minutes')->default(90);
            $table->unsignedInteger('guest_count');
            $table->unsignedInteger('adult_count')->nullable();
            $table->unsignedInteger('child_count')->nullable();
            $table->string('customer_name');
            $table->string('customer_phone', 50);
            $table->string('phone_country_iso_code', 5)->nullable();
            $table->string('customer_email')->nullable();
            $table->string('occasion', 100)->nullable();
            $table->text('special_requests')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('no_show_reason')->nullable();
            $table->string('payment_status', 50)->default(ReservationPaymentStatus::NotRequired->value);
            $table->boolean('deposit_required')->default(false);
            $table->decimal('deposit_amount', 18, 4)->default(0);
            $table->decimal('deposit_paid_amount', 18, 4)->default(0);
            $table->string('deposit_currency', 3);
            $table->decimal('deposit_currency_rate', 18, 4)->default(1);
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('seated_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('no_show_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->foreignIdFor(User::class, 'updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'seated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_reference')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'reservation_date']);
            $table->index('status');
            $table->index(['start_at', 'end_at']);
            $table->index('customer_phone');
            $table->index('table_assignment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
