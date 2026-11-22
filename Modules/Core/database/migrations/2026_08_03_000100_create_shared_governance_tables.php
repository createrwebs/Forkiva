<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\IdempotencyRecordStatus;

return new class extends Migration
{
    /**
     * Create immutable audit evidence and idempotent replay protection tables.
     */
    public function up(): void
    {
        Schema::create('immutable_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('subject');
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('action')->index();
            $table->text('reason');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('event_hash', 64)->unique();
            $table->timestamp('occurred_at')->index();
        });

        Schema::create('idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->string('scope');
            $table->string('key');
            $table->char('payload_fingerprint', 64);
            $table->string('status')->default(IdempotencyRecordStatus::Processing->value)->index();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['scope', 'key']);
        });
    }

    /**
     * Remove shared governance tables in dependency-safe order.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
        Schema::dropIfExists('immutable_audit_events');
    }
};
