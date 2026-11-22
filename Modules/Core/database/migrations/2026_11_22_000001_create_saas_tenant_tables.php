<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug', 64)->unique();
            $table->string('name', 150);
            $table->string('business_type', 20)->default('fnb');
            $table->string('status', 20)->default('trial')->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->json('name');
            $table->decimal('monthly_price', 12, 2)->default(0);
            $table->decimal('yearly_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('THB');
            $table->json('limits')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('billing_cycle', 20)->default('monthly');
            $table->string('status', 20)->default('trialing')->index();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'subscriptions_tenant_status_index');
        });

        if (Schema::hasTable('branches') && ! Schema::hasColumn('branches', 'tenant_id')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->restrictOnDelete();
                $table->index('tenant_id', 'branches_tenant_id_index');
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->restrictOnDelete();
                $table->index('tenant_id', 'users_tenant_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'tenant_id')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('tenants');
    }
};
