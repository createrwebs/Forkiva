<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'barcode')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->string('barcode', 64)->nullable()->after('sku')->index('products_barcode_index');
            });
        }

        Schema::create('stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('product_variant_id')->nullable()->index();
            $table->string('lot_number', 64);
            $table->timestamp('expires_at')->nullable()->index();
            $table->decimal('quantity_remaining', 14, 4)->default(0);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->timestamps();

            $table->index(['branch_id', 'product_id', 'expires_at'], 'stock_lots_fefo_index');
        });

        Schema::create('pos_held_carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('label', 150);
            $table->json('items');
            $table->timestamp('resumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_offline_sync_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->uuid('client_uuid');
            $table->string('offline_reference', 80);
            $table->string('payment_method', 30);
            $table->decimal('total', 12, 2);
            $table->boolean('needs_stock_reconciliation')->default(false);
            $table->json('payload');
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['branch_id', 'client_uuid'], 'pos_offline_sync_branch_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_offline_sync_receipts');
        Schema::dropIfExists('pos_held_carts');
        Schema::dropIfExists('stock_lots');

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'barcode')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropIndex('products_barcode_index');
                $table->dropColumn('barcode');
            });
        }
    }
};
