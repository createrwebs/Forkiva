<?php

namespace Modules\Order\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Invoice\Services\CreateInvoice\CreateInvoiceServiceInterface;
use Modules\Menu\Models\Menu;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Facades\Pricing;
use Modules\Order\Models\Order;
use Modules\Printer\Factories\OrderResourceFactory;
use Modules\Product\Models\Product;
use Modules\Tax\Enums\TaxType;
use Modules\Tax\Models\Tax;
use Modules\Tax\Support\TaxSummary;
use Modules\User\Enums\CustomerType;
use Modules\User\Models\User;
use Tests\TestCase;

class PricingCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_inclusive_tax_is_extracted_without_increasing_the_displayed_price(): void
    {
        [$branch, $product] = $this->createProduct(115);
        $tax = $this->createTax(TaxType::Inclusive, 15, true);

        $this->startPricing($branch);
        $item = Pricing::store($product->id, 1);

        $this->assertNotNull($item);
        $this->assertEqualsWithDelta(100, $item->subtotal()->amount(), 0.0001);
        $this->assertEqualsWithDelta(100, $item->netUnitPrice()->amount(), 0.0001);
        $this->assertEqualsWithDelta(100, Pricing::subTotal()->amount(), 0.0001);
        $this->assertEqualsWithDelta(15, Pricing::taxes()->firstWhere(fn ($cartTax) => $cartTax->id() === $tax->id)->amount()->amount(), 0.0001);
        $this->assertEqualsWithDelta(115, Pricing::total()->amount(), 0.0001);
        $this->assertSame([
            'id' => $tax->id,
            'name' => $tax->name,
            'rate' => 15.0,
            'type' => TaxType::Inclusive->value,
            'scope' => 'global',
        ], collect(Pricing::taxes()->first()->toArray())->except('amount')->all());
    }

    public function test_product_inclusive_tax_is_extracted_and_kept_on_the_order_line(): void
    {
        [$branch, $product] = $this->createProduct(115);
        $tax = $this->createTax(TaxType::Inclusive, 15, false, $branch->id);
        $product->taxes()->attach($tax);

        $this->startPricing($branch);
        $item = Pricing::store($product->id, 1);

        $this->assertNotNull($item);
        $this->assertEqualsWithDelta(100, $item->subtotal()->amount(), 0.0001);
        $this->assertEqualsWithDelta(15, $item->taxTotal()->amount(), 0.0001);
        $this->assertEqualsWithDelta(115, $item->total()->amount(), 0.0001);
        $this->assertEqualsWithDelta(100, Pricing::subTotal()->amount(), 0.0001);
        $this->assertEqualsWithDelta(115, Pricing::total()->amount(), 0.0001);
        $this->assertSame([
            'id' => $tax->id,
            'name' => $tax->name,
            'rate' => 15.0,
            'type' => TaxType::Inclusive->value,
            'scope' => 'product',
        ], collect($item->taxes()->first()->toArray())->except('amount')->all());
    }

    public function test_multiple_inclusive_taxes_share_the_same_net_base(): void
    {
        [$branch, $product] = $this->createProduct(125);
        $productTax = $this->createTax(TaxType::Inclusive, 10, false, $branch->id);
        $this->createTax(TaxType::Inclusive, 15, true);
        $product->taxes()->attach($productTax);

        $this->startPricing($branch);
        $item = Pricing::store($product->id, 1);

        $this->assertNotNull($item);
        $this->assertEqualsWithDelta(100, $item->subtotal()->amount(), 0.0001);
        $this->assertEqualsWithDelta(10, $item->taxTotal()->amount(), 0.0001);
        $this->assertEqualsWithDelta(15, Pricing::tax()->amount(), 0.0001);
        $this->assertEqualsWithDelta(125, Pricing::total()->amount(), 0.0001);
    }

    public function test_exclusive_tax_still_adds_tax_on_top_of_the_catalog_price(): void
    {
        [$branch, $product] = $this->createProduct(100);
        $this->createTax(TaxType::Exclusive, 15, true);

        $this->startPricing($branch);
        $item = Pricing::store($product->id, 1);

        $this->assertNotNull($item);
        $this->assertEqualsWithDelta(100, Pricing::subTotal()->amount(), 0.0001);
        $this->assertEqualsWithDelta(15, Pricing::tax()->amount(), 0.0001);
        $this->assertEqualsWithDelta(115, Pricing::total()->amount(), 0.0001);
    }

    public function test_compound_exclusive_tax_uses_the_subtotal_and_preceding_taxes(): void
    {
        [$branch, $product] = $this->createProduct(100);
        $this->createTax(TaxType::Exclusive, 10, true);
        $this->createTax(TaxType::Exclusive, 5, true, null, true);

        $this->startPricing($branch);
        Pricing::store($product->id, 1);

        $taxes = Pricing::taxes()->values();

        $this->assertEqualsWithDelta(10, $taxes[0]->amount()->amount(), 0.0001);
        $this->assertEqualsWithDelta(5.5, $taxes[1]->amount()->amount(), 0.0001);
        $this->assertEqualsWithDelta(115.5, Pricing::total()->amount(), 0.0001);
    }

    public function test_inclusive_product_and_global_taxes_are_preserved_across_order_and_invoice_outputs(): void
    {
        [$branch, $product] = $this->createProduct(130);
        $product->update(['prep_time_minutes' => 18]);
        $globalTax = $this->createTax(TaxType::Inclusive, 15, true);
        $productTax = $this->createTax(TaxType::Inclusive, 15, false, $branch->id);
        $product->taxes()->attach($productTax);

        $this->startPricing($branch);
        $item = Pricing::store($product->id, 1);
        $customer = User::factory()->create([
            'branch_id' => $branch->id,
            'customer_type' => CustomerType::Regular,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::Pending,
            'type' => OrderType::DineIn,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'subtotal' => Pricing::subTotal()->amount(),
            'total' => Pricing::total()->amount(),
            'due_amount' => Pricing::total()->amount(),
            'guest_count' => 1,
            'order_date' => today(),
        ]);

        $order->updateOrCreateProduct($item);
        $order->updateOrCreateTaxes(Pricing::taxes());

        $orderProduct = $order->products()->firstOrFail();
        $orderTax = $order->taxes()->where('tax_id', $globalTax->id)->firstOrFail();
        $orderProductTax = $orderProduct->taxes()->where('tax_id', $productTax->id)->firstOrFail();

        $this->assertEqualsWithDelta(100, $order->subtotal->amount(), 0.0001);
        $this->assertEqualsWithDelta(130, $order->total->amount(), 0.0001);
        $this->assertEqualsWithDelta(100, $orderProduct->unit_price->amount(), 0.0001);
        $this->assertEqualsWithDelta(100, $orderProduct->subtotal->amount(), 0.0001);
        $this->assertSame(18, $orderProduct->prep_time_minutes);
        $this->assertEqualsWithDelta(15, $orderTax->amount->amount(), 0.0001);
        $this->assertEqualsWithDelta(15, $orderProductTax->amount->amount(), 0.0001);
        $this->assertSame(TaxType::Inclusive, $orderTax->type);
        $this->assertEqualsWithDelta(30, $order->totalTax()->amount(), 0.0001);

        $order->recalculate();
        $order->refresh();

        $this->assertEqualsWithDelta(100, $order->subtotal->amount(), 0.0001);
        $this->assertEqualsWithDelta(30, $order->totalTax()->amount(), 0.0001);
        $this->assertEqualsWithDelta(130, $order->total->amount(), 0.0001);
        $this->assertEqualsWithDelta(15, $order->taxes()->where('tax_id', $globalTax->id)->firstOrFail()->amount->amount(), 0.0001);

        $invoice = app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $this->assertEqualsWithDelta(100, $invoice->fresh()->subtotal->amount(), 0.0001);
        $this->assertEqualsWithDelta(30, $invoice->fresh()->tax_total->amount(), 0.0001);
        $this->assertEqualsWithDelta(130, $invoice->fresh()->total->amount(), 0.0001);
        $this->assertDatabaseHas('invoice_taxes', [
            'invoice_id' => $invoice->id,
            'tax_id' => $globalTax->id,
            'type' => TaxType::Inclusive->value,
            'amount' => 15,
        ]);
        $this->assertDatabaseHas('invoice_taxes', [
            'invoice_id' => $invoice->id,
            'tax_id' => $productTax->id,
            'type' => TaxType::Inclusive->value,
            'amount' => 15,
        ]);

        $orderTaxSummary = TaxSummary::summarize($order->allTaxes()->get(), 'order_product_id');
        $invoiceTaxSummary = TaxSummary::summarize($invoice->allTaxes()->get(), 'invoice_line_id');

        $this->assertSame([
            ['scope' => 'global', 'amount' => 15.0],
            ['scope' => 'product', 'amount' => 15.0],
        ], $orderTaxSummary->map(fn (array $tax) => collect($tax)->only(['scope', 'amount'])->all())->sortBy('scope')->values()->all());
        $this->assertSame([
            ['scope' => 'global', 'amount' => 15.0],
            ['scope' => 'product', 'amount' => 15.0],
        ], $invoiceTaxSummary->map(fn (array $tax) => collect($tax)->only(['scope', 'amount'])->all())->sortBy('scope')->values()->all());
        $this->assertSame(
            [15.0, 15.0],
            OrderResourceFactory::orderTaxSummary($order->allTaxes()->get())->pluck('amount')->sort()->values()->all()
        );
        $this->assertSame(
            [15.0, 15.0],
            OrderResourceFactory::invoiceTaxSummary($invoice->allTaxes()->get())->pluck('amount')->sort()->values()->all()
        );
    }

    public function test_fixed_percentage_and_text_options_match_local_jod_reference(): void
    {
        [$branch, $product] = $this->createProduct(10);
        $selected = [];
        foreach ([[\Modules\Option\Enums\OptionType::Select, .125, \Modules\Support\Enums\PriceType::Fixed], [\Modules\Option\Enums\OptionType::Select, 10, \Modules\Support\Enums\PriceType::Percent], [\Modules\Option\Enums\OptionType::Text, .250, \Modules\Support\Enums\PriceType::Fixed]] as [$type, $price, $priceType]) {
            $option = \Modules\Option\Models\Option::factory()->create(['branch_id' => $branch->id, 'type' => $type, 'name' => ['en' => 'Option'], 'is_required' => true]);
            $value = \Modules\Option\Models\OptionValue::factory()->create(['branch_id' => $branch->id, 'option_id' => $option->id, 'label' => ['en' => 'Choice'], 'price' => $price, 'price_type' => $priceType]);
            $product->options()->attach($option);
            $selected[$option->id] = $type->isFieldType() ? '0' : $value->id;
        }
        $this->startPricing($branch);
        $line = Pricing::store($product->id, 1, $selected);
        $this->assertEqualsWithDelta(11.375, Pricing::total()->amount(), .0000001);
        $this->assertSame(3, Pricing::total()->precision());
        $this->assertSame('0', $line->options->firstWhere('type', \Modules\Option\Enums\OptionType::Text)->values->first()->label);
    }

    private function createProduct(float $price): array
    {
        $branch = Branch::factory()->create([
            'currency' => 'JOD',
            'order_types' => [OrderType::DineIn->value],
        ]);
        $menu = Menu::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $product = Product::factory()->create([
            'menu_id' => $menu->id,
            'name' => ['en' => 'Taxed product'],
            'price' => $price,
            'special_price' => null,
            'is_active' => true,
        ]);

        return [$branch, $product];
    }

    private function createTax(
        TaxType $type,
        float $rate,
        bool $global,
        ?int $branchId = null,
        bool $compound = false
    ): Tax {
        return Tax::query()->create([
            'name' => ['en' => "Tax {$rate}"],
            'code' => 'TAX_'.str_replace('.', '_', (string) $rate).'_'.($global ? 'GLOBAL' : 'PRODUCT'),
            'rate' => $rate,
            'type' => $type,
            'compound' => $compound,
            'is_global' => $global,
            'branch_id' => $branchId,
            'order_types' => [],
            'is_active' => true,
        ]);
    }

    private function startPricing(Branch $branch): void
    {
        Pricing::clear();
        Pricing::addBranch($branch);
        Pricing::addOrderType(OrderType::DineIn);
    }
}
