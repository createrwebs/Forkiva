<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Reservation\Enums\ReservationWaitlistStatus;
use Modules\Reservation\Models\Reservation;
use Modules\User\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservation_waitlists', function (Blueprint $table) {
            $table->id();
            $table->branch(false);
            $table->createdBy(false);
            $table->foreignIdFor(User::class, 'customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone', 50);
            $table->string('phone_country_iso_code', 5);
            $table->string('customer_email')->nullable();
            $table->date('preferred_date');
            $table->time('preferred_start_time')->nullable();
            $table->time('preferred_end_time')->nullable();
            $table->unsignedInteger('guest_count');
            $table->unsignedInteger('estimated_wait_minutes')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->string('status', 50)->default(ReservationWaitlistStatus::Waiting->value);
            $table->foreignIdFor(Reservation::class, 'converted_reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->string('occasion')->nullable();
            $table->text('special_requests')->nullable();
            $table->text('internal_notes')->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('converted_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'preferred_date']);
            $table->index('status');
            $table->index('customer_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_waitlists');
    }
};
