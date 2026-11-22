<?php

namespace Modules\Invoice\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Core\Services\Accounting\NullFinancialPostingBridge;
use Modules\Core\Services\Delivery\NullDeliveryFeatureService;
use Modules\Invoice\Enums\InvoicePurpose;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Services\CreateCreditNote\CreateCreditNoteServiceInterface;
use Modules\Invoice\Services\CreateInvoice\CreateInvoiceServiceInterface;
use Modules\Menu\Models\Menu;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use Tests\TestCase;
use Throwable;

class CreateCreditNoteMySqlConcurrencyTest extends TestCase
{
    use RefreshDatabase {
        migrateDatabases as migrateConventionalDatabase;
    }

    private bool $committedForConcurrency = false;

    public function test_concurrent_requests_create_only_one_credit_note_for_the_same_product(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped('A real MySQL or PostgreSQL connection is required to verify SELECT ... FOR UPDATE.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required for the real concurrency test.');
        }

        $this->app->instance(
            FinancialPostingBridgeInterface::class,
            app(NullFinancialPostingBridge::class),
        );

        // This fixture migrates invoice dependencies only; delivery has its own integration tests.
        $this->app->instance(DeliveryFeatureInterface::class, new NullDeliveryFeatureService);

        $order = $this->createInvoicedOrder();
        $order->products()->update(['status' => OrderProductStatus::Refunded]);
        DB::commit();
        $this->committedForConcurrency = true;

        $token = sys_get_temp_dir().'/credit-note-concurrency-'.uniqid('', true);
        $gate = $token.'.go';
        $resultFiles = [$token.'.1', $token.'.2'];
        $children = [];

        DB::disconnect();

        foreach ($resultFiles as $resultFile) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('Unable to fork a credit-note concurrency worker.');
            }

            if ($pid === 0) {
                DB::purge();
                DB::reconnect();

                while (! file_exists($gate)) {
                    usleep(1_000);
                }

                try {
                    $creditNote = app(CreateCreditNoteServiceInterface::class)
                        ->create(Order::query()->findOrFail($order->id), InvoicePurpose::Return);
                    file_put_contents($resultFile, $creditNote ? (string) $creditNote->id : 'null');
                } catch (Throwable $exception) {
                    file_put_contents($resultFile, 'error:'.$exception->getMessage());
                }

                exit(0);
            }

            $children[] = $pid;
        }

        file_put_contents($gate, 'go');

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));
        }

        DB::purge();
        DB::reconnect();

        $results = collect($resultFiles)->map(fn (string $file) => trim((string) file_get_contents($file)));

        foreach ([$gate, ...$resultFiles] as $file) {
            @unlink($file);
        }

        $this->assertFalse(
            $results->contains(fn (string $result) => str_starts_with($result, 'error:')),
            'Concurrency workers returned: '.$results->implode(' | '),
        );
        $this->assertSame(1, $results->filter(fn (string $result) => $result === 'null')->count());
        $this->assertSame(1, $results->filter(fn (string $result) => ctype_digit($result))->count());
        $this->assertSame(
            1,
            Invoice::query()
                ->whereNotNull('reference_invoice_id')
                ->where('order_id', $order->id)
                ->count(),
        );
        $this->assertSame(
            1,
            Invoice::query()
                ->whereNotNull('reference_invoice_id')
                ->where('order_id', $order->id)
                ->firstOrFail()
                ->lines()
                ->whereNotNull('order_product_id')
                ->count(),
        );
    }

    protected function tearDown(): void
    {
        if ($this->committedForConcurrency) {
            DB::purge();
            DB::reconnect();
            $this->migrateDatabases();
            DB::beginTransaction();
        }

        parent::tearDown();
    }

    /**
     * Build an empty MySQL schema that contains forward cross-module foreign keys.
     */
    protected function migrateDatabases(): void
    {
        if (DB::getDriverName() !== 'sqlite' && ! str_ends_with(DB::getDatabaseName(), '_test')) {
            $this->fail('Credit-note concurrency tests require a disposable database ending in _test.');
        }

        if (DB::getDriverName() !== 'mysql') {
            $this->migrateConventionalDatabase();

            return;
        }

        $this->artisan('db:wipe', ['--force' => true])->assertExitCode(0);
        Schema::disableForeignKeyConstraints();

        try {
            $this->artisan('migrate:install')->assertExitCode(0);
            $modulePaths = collect([
                'Branch',
                'User',
                'Menu',
                'Product',
                'Pos',
                'Order',
                'Inventory',
                'Payment',
                'Invoice',
            ])->map(fn (string $module) => module_path($module, 'database/migrations'))->all();

            $this->artisan('migrate', [
                '--path' => $modulePaths,
                '--realpath' => true,
                '--force' => true,
            ])->assertExitCode(0);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function createInvoicedOrder(): Order
    {
        $branch = Branch::factory()->create([
            'currency' => 'JOD',
            'order_types' => [OrderType::Delivery->value],
        ]);
        $customer = User::factory()->create();
        $order = Order::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'type' => OrderType::Delivery,
            'payment_status' => OrderPaymentStatus::Paid,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'subtotal' => 10,
            'total' => 10,
            'due_amount' => 0,
            'guest_count' => 1,
            'order_date' => now(),
        ]);
        $menu = Menu::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $product = Product::factory()->create([
            'menu_id' => $menu->id,
            'is_active' => true,
            'sku' => 'CONCURRENT-CREDIT',
        ]);

        OrderProduct::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'created_by' => $customer->id,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'unit_price' => 10,
            'quantity' => 1,
            'subtotal' => 10,
            'tax_total' => 0,
            'total' => 10,
            'cost_price' => 0,
            'gross_profit' => 10,
            'status' => OrderProductStatus::Served,
        ]);

        app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        return $order->fresh(['products']);
    }
}
