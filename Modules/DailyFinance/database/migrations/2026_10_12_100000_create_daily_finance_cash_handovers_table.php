<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Branch\Models\Branch;
use Modules\DailyFinance\Enums\CashHandoverStatus;
use Modules\Pos\Models\PosSession;
use Modules\User\Models\User;

return new class extends Migration
{
    /**
     * Add custody records without changing existing POS closing snapshots.
     */
    public function up(): void
    {
        Schema::create('daily_finance_cash_handovers', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_no', 32)->nullable()->unique('df_ch_reference_unique');
            // Financial custody evidence must survive deletion attempts on its source and actors.
            $table->foreignIdFor(PosSession::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Branch::class)->constrained()->restrictOnDelete();
            $table->createdBy(false)->nullable(false)->constrained((new User)->getTable())->restrictOnDelete();
            $table->foreignIdFor(User::class, 'cashier_id')->constrained()->restrictOnDelete();
            $table->foreignIdFor(User::class, 'receiver_id')->constrained()->restrictOnDelete();
            $table->uuid('request_key');
            $table->string('currency', 3);
            $table->decimal('counted_cash', 18, 4);
            $table->decimal('amount', 18, 4);
            $table->decimal('received_amount', 18, 4)->nullable();
            $table->string('status', 20)->default(CashHandoverStatus::Pending->value);
            $table->text('notes')->nullable();
            $table->text('variance_reason')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->foreignIdFor(User::class, 'cancelled_by')->nullable()->constrained()->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['pos_session_id', 'request_key'], 'df_handover_session_request_unique');
            $table->index(['branch_id', 'receiver_id', 'status'], 'df_handover_receiver_status_index');
        });
    }

    /**
     * Drop the custody table only when its history can be discarded safely.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_finance_cash_handovers');
    }
};
