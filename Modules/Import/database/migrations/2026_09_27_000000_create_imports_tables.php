<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->createdBy();
            $table->string('resource_key', 80);
            $table->string('locale', 12);
            $table->string('status', 32)->index();
            $table->string('mode', 32)->default('create_only');
            $table->string('original_name');
            $table->string('disk');
            $table->string('path');
            $table->string('error_path')->nullable();
            $table->json('context')->nullable();
            $table->json('headers');
            $table->json('mapping')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('new_rows')->default(0);
            $table->unsignedInteger('update_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['created_by', 'resource_key', 'id']);
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('values');
            $table->json('data');
            $table->string('record_key')->nullable();
            $table->json('errors')->nullable();
            $table->string('operation', 16)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamps();
            $table->unique(['import_id', 'row_number']);
            $table->index(['import_id', 'status', 'id']);
            $table->index(['import_id', 'record_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('imports');
    }
};
