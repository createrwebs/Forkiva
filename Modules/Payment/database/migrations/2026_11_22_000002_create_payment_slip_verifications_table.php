<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_slip_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->string('provider', 40);
            $table->string('sending_bank_code', 10);
            $table->string('trans_ref', 100);
            $table->string('qr_payload_hash', 64)->index();
            $table->decimal('amount', 12, 2);
            $table->string('sender_name', 150)->nullable();
            $table->string('receiver_account', 100)->nullable();
            $table->string('status', 20)->default('verified')->index();
            $table->json('raw_response')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'trans_ref'], 'slip_verifications_branch_trans_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_slip_verifications');
    }
};
