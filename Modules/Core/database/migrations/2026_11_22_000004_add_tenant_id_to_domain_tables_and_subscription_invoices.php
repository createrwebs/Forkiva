<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $domainTables = [
        'orders',
        'products',
        'menus',
        'categories',
        'payments',
        'invoices',
        'ingredients',
        'pos_registers',
        'pos_sessions',
        'tables',
        'loyalty_programs',
        'vouchers',
        'printers',
        'settings',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('subscription_invoices')) {
            Schema::create('subscription_invoices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
                $table->foreignId('subscription_id')->constrained('subscriptions')->restrictOnDelete();
                $table->string('invoice_number', 64)->unique();
                $table->decimal('amount', 12, 2);
                $table->decimal('vat_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2);
                $table->string('status', 20)->default('pending')->index();
                $table->string('gateway_ref', 120)->nullable()->unique();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('branches')) {
            Schema::table('branches', function (Blueprint $table): void {
                if (! Schema::hasColumn('branches', 'payment_verify_driver')) {
                    $table->string('payment_verify_driver', 40)->nullable()->after('promptpay_id');
                }
                if (! Schema::hasColumn('branches', 'payment_verify_config')) {
                    $table->text('payment_verify_config')->nullable()->after('payment_verify_driver');
                }
            });
        }

        foreach ($this->domainTables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
                    $table->index('tenant_id', "{$tableName}_tenant_id_index");
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->domainTables) as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('tenant_id');
                });
            }
        }

        if (Schema::hasTable('branches')) {
            Schema::table('branches', function (Blueprint $table): void {
                if (Schema::hasColumn('branches', 'payment_verify_config')) {
                    $table->dropColumn('payment_verify_config');
                }
                if (Schema::hasColumn('branches', 'payment_verify_driver')) {
                    $table->dropColumn('payment_verify_driver');
                }
            });
        }

        Schema::dropIfExists('subscription_invoices');
    }
};
