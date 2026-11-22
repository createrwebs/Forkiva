<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Approval\Enums\ApprovalRequestStatus;
use Modules\Approval\Enums\ApprovalStepStatus;
use Modules\Approval\Models\ApprovalRequest;
use Modules\Branch\Models\Branch;

return new class extends Migration
{
    /**
     * Create versioned requests and their ordered decision levels.
     */
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('subject');
            $table->foreignIdFor(Branch::class)->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('maker_id')->constrained('users')->restrictOnDelete();
            $table->string('workflow');
            $table->string('status')->default(ApprovalRequestStatus::Pending->value)->index();
            $table->decimal('amount', 24, 8)->nullable();
            $table->string('currency', 3)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->char('payload_fingerprint', 64);
            $table->json('payload');
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->foreignId('invalidated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('invalidation_reason')->nullable();
            $table->createdBy();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workflow', 'status']);
            $table->index(['subject_type', 'subject_id', 'status'], 'approval_subject_status_idx');
            $table->unique(
                ['subject_type', 'subject_id', 'workflow', 'version'],
                'approval_subject_workflow_version_unique',
            );
        });

        Schema::create('approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(ApprovalRequest::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('level');
            $table->string('name');
            $table->decimal('minimum_amount', 24, 8)->nullable();
            $table->decimal('maximum_amount', 24, 8)->nullable();
            $table->string('permission')->nullable();
            $table->string('status')->default(ApprovalStepStatus::Pending->value)->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('reason')->nullable();
            $table->createdBy();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['approval_request_id', 'level']);
        });
    }

    /**
     * Remove approval evidence tables in dependency-safe order.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_requests');
    }
};
