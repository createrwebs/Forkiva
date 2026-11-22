<?php

namespace Modules\Invoice\tests\Feature;

use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Mockery;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Core\Contracts\FinancialAccountLookupInterface;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Core\Services\Accounting\NullFinancialAccountLookup;
use Modules\Core\Services\Delivery\NullDeliveryFeatureService;
use Modules\Discount\Models\Discount;
use Modules\Invoice\Enums\InvoicePurpose;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceCounter;
use Modules\Invoice\Models\InvoiceDiscount;
use Modules\Invoice\Models\InvoiceParty;
use Modules\Invoice\Models\InvoiceTax;
use Modules\Invoice\Services\CreateCreditNote\CreateCreditNoteServiceInterface;
use Modules\Invoice\Services\CreateInvoice\CreateInvoiceServiceInterface;
use Modules\Invoice\Services\FullOrderReversal\FullOrderReversalResolverServiceInterface;
use Modules\Invoice\Services\Invoice\InvoiceServiceInterface;
use Modules\Invoice\Services\OrderChargeInvoicePolicy\OrderChargeInvoicePolicyResolverServiceInterface;
use Modules\Menu\Models\Menu;
use Modules\Order\Enums\DiscountType;
use Modules\Order\Enums\OrderChargeCode;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Events\OrderVoided;
use Modules\Order\Listeners\CreateOrderCreditNote;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderCharge;
use Modules\Order\Models\OrderDiscount;
use Modules\Order\Models\OrderProduct;
use Modules\Product\Models\Product;
use Modules\Reservation\Enums\ReservationDepositTransactionType;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\ReservationDeposit\ReservationDepositServiceInterface;
use Modules\SeatingPlan\Enums\TableMergeType;
use Modules\SeatingPlan\Models\Floor;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Models\TableMerge;
use Modules\SeatingPlan\Models\Zone;
use Modules\Tax\Enums\TaxType;
use Modules\Tax\Models\Tax;
use Modules\User\Models\User;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InvoiceChargeHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_lookup_accepts_numbers_starting_with_digits(): void
    {
        $invoice = app(CreateInvoiceServiceInterface::class)->create($this->createOrderWithProductsAndCharges()->fresh());
        $invoice->forceFill(['invoice_number' => '2026-INV-LOOKUP'])->save();
        foreach ([$invoice->id, (string) $invoice->id, $invoice->uuid, $invoice->invoice_number] as $identifier) {
            $this->assertSame($invoice->id, app(InvoiceServiceInterface::class)->show($identifier)->id);
        }
    }

    public function test_normal_invoice_keeps_delivery_related_charges_as_invoice_lines(): void
    {
        $order = $this->createOrderWithProductsAndCharges();

        $invoice = app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $this->assertSame(5, $invoice->lines()->count());
        $this->assertSame(2, $invoice->lines()->whereNotNull('order_product_id')->count());
        $this->assertSame(3, $invoice->lines()->whereNotNull('order_charge_id')->count());
        $this->assertEquals(25.5, $invoice->fresh()->subtotal->amount());
        $this->assertEquals(25.5, $invoice->fresh()->total->amount());
    }

    public function test_invoice_with_tip_does_not_query_placeholder_delivery_relation(): void
    {
        $delivery = Mockery::mock(NullDeliveryFeatureService::class)->makePartial();
        $delivery->shouldReceive('enabled')->andReturn(true);
        $this->app->instance(DeliveryFeatureInterface::class, $delivery);

        $order = $this->createOrderWithProductsAndCharges();
        $order->charges()->create([
            'code' => OrderChargeCode::Tip->value,
            'name' => ['en' => 'Tip', 'ar' => 'إكرامية'],
            'currency' => 'JOD',
            'currency_rate' => 1,
            'unit_amount' => 3,
            'quantity' => 1,
            'amount' => 3,
            'taxable' => false,
            'tax_metadata' => null,
        ]);
        $order->update(['total' => 28.5, 'due_amount' => 28.5]);

        $invoice = app(CreateInvoiceServiceInterface::class)->create($order->fresh());
        $tipLine = $invoice->lines()->whereHas('orderCharge', fn ($query) => $query->where('code', OrderChargeCode::Tip->value))->firstOrFail();

        $this->assertDatabaseHas('gratuity_collections', [
            'source_type' => 'invoice_line',
            'source_id' => $tipLine->id,
            'collection_type' => 'tip',
        ]);
    }

    public function test_tip_charge_creates_and_credit_note_reverses_the_gratuity_subledger_collection(): void
    {
        $order = $this->createOrderWithProductsAndCharges();
        $tip = $order->charges()->create([
            'code' => OrderChargeCode::Tip->value,
            'name' => ['en' => 'Tip', 'ar' => 'إكرامية'],
            'currency' => 'JOD',
            'currency_rate' => 1,
            'unit_amount' => 3,
            'quantity' => 1,
            'amount' => 3,
            'taxable' => false,
            'tax_metadata' => null,
        ]);
        $order->update(['total' => 28.5, 'due_amount' => 28.5]);

        $invoice = app(CreateInvoiceServiceInterface::class)->create($order->fresh());
        $tipLine = $invoice->lines()->where('order_charge_id', $tip->id)->firstOrFail();

        $this->assertDatabaseHas('gratuity_collections', [
            'source_type' => 'invoice_line',
            'source_id' => $tipLine->id,
            'collection_type' => 'tip',
            'amount' => 3,
            'currency' => 'JOD',
            'currency_rate' => 1,
        ]);

        $order->products()->update(['status' => OrderProductStatus::Refunded]);
        $creditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);
        $creditTipLine = $creditNote->lines()->where('order_charge_id', $tip->id)->firstOrFail();
        $originalCollectionId = DB::table('gratuity_collections')
            ->where('source_type', 'invoice_line')
            ->where('source_id', $tipLine->id)
            ->value('id');

        $this->assertNotNull(DB::table('gratuity_collections')->where('id', $originalCollectionId)->value('reversed_at'));
        $this->assertDatabaseHas('gratuity_collections', [
            'source_type' => 'invoice_credit_line',
            'source_id' => $creditTipLine->id,
            'reversal_of_id' => $originalCollectionId,
            'amount' => 3,
        ]);
    }

    public function test_reservation_deposit_application_updates_and_reversal_restores_order_and_invoice_balances(): void
    {
        $this->app->instance(FinancialAccountLookupInterface::class, new NullFinancialAccountLookup);
        $order = $this->createOrderWithProductsAndCharges();
        $reservation = Reservation::factory()->create([
            'branch_id' => $order->branch_id,
            'customer_id' => $order->customer_id,
            'deposit_required' => true,
            'deposit_amount' => 20,
            'deposit_paid_amount' => 0,
            'deposit_currency' => 'JOD',
            'deposit_currency_rate' => 1,
        ]);
        $service = app(ReservationDepositServiceInterface::class);

        $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Collection,
            'amount' => '20.0000',
            'tender_method' => 'cash',
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'reservation-deposit-collection-test',
            'note' => 'Collected for test.',
        ]);
        $application = $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Application,
            'amount' => '15.0000',
            'order_id' => $order->id,
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'reservation-deposit-application-test',
            'note' => 'Applied for test.',
        ]);

        $this->assertNotNull($application->payment_id);
        $this->assertDatabaseHas('payments', [
            'id' => $application->payment_id,
            'method' => 'reservation_deposit',
            'amount' => 15,
        ]);
        $this->assertEquals(10.5, $order->fresh()->getRawOriginal('due_amount'));
        $this->assertSame(0, $application->payment->allocations()->count());

        $invoice = app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $this->assertEquals(15, $invoice->fresh()->getRawOriginal('net_paid'));
        $this->assertEquals(5, $reservation->fresh()->getRawOriginal('deposit_paid_amount'));

        $service->reverse($application->id, [
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'reservation-deposit-application-reversal-test',
            'reason' => 'Reversed for test.',
        ]);

        $this->assertNotNull(DB::table('payments')->where('id', $application->payment_id)->value('voided_at'));
        $this->assertEquals(25.5, $order->fresh()->getRawOriginal('due_amount'));
        $this->assertEquals(0, $invoice->fresh()->getRawOriginal('net_paid'));
        $this->assertEquals(20, $reservation->fresh()->getRawOriginal('deposit_paid_amount'));
    }

    public function test_full_order_deposit_automatically_creates_a_fully_allocated_invoice(): void
    {
        $this->app->instance(FinancialAccountLookupInterface::class, new NullFinancialAccountLookup);
        $order = $this->createOrderWithProductsAndCharges();
        $reservation = Reservation::factory()->create([
            'branch_id' => $order->branch_id,
            'customer_id' => null,
            'deposit_required' => true,
            'deposit_amount' => 25.5,
            'deposit_paid_amount' => 0,
            'deposit_currency' => 'JOD',
            'deposit_currency_rate' => 1,
        ]);
        $service = app(ReservationDepositServiceInterface::class);

        $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Collection,
            'amount' => '25.5000',
            'tender_method' => 'cash',
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'full-order-deposit-collection',
            'note' => 'Collected for a fully deposit-paid order.',
        ]);
        $application = $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Application,
            'amount' => '25.5000',
            'order_id' => $order->id,
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'full-order-deposit-application',
            'note' => 'Apply the full order amount.',
        ]);

        $invoice = $order->fresh()->getInvoice();

        $this->assertNotNull($invoice);
        $this->assertEquals(0, $order->fresh()->getRawOriginal('due_amount'));
        $this->assertEquals(25.5, $invoice->getRawOriginal('net_paid'));
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $application->payment_id,
            'invoice_id' => $invoice->id,
            'amount' => 25.5,
        ]);
    }

    public function test_anonymous_reservation_deposit_can_be_applied_across_linked_orders_for_different_customers(): void
    {
        $this->app->instance(FinancialAccountLookupInterface::class, new NullFinancialAccountLookup);
        $firstOrder = $this->createOrderWithProductsAndCharges();
        $secondOrder = $this->createOrderWithProductsAndCharges([
            'branch_id' => $firstOrder->branch_id,
        ]);
        $reservation = Reservation::factory()->create([
            'branch_id' => $firstOrder->branch_id,
            'customer_id' => null,
            'deposit_required' => true,
            'deposit_amount' => 40,
            'deposit_paid_amount' => 0,
            'deposit_currency' => 'JOD',
            'deposit_currency_rate' => 1,
        ]);
        $service = app(ReservationDepositServiceInterface::class);

        $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Collection,
            'amount' => '40.0000',
            'tender_method' => 'cash',
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'anonymous-reservation-deposit-collection',
            'note' => 'Anonymous group reservation deposit.',
        ]);

        $eligibleOrderIds = collect($service->getFormMeta($reservation->id)['eligible_orders'])
            ->pluck('order_id');
        $this->assertTrue($eligibleOrderIds->contains($firstOrder->id));
        $this->assertTrue($eligibleOrderIds->contains($secondOrder->id));
        $this->assertSame(
            [$firstOrder->id],
            collect($service->searchEligibleOrders($reservation->id, $firstOrder->reference_no))
                ->pluck('order_id')
                ->all(),
        );
        $this->assertSame(
            [$secondOrder->id],
            collect($service->searchEligibleOrders($reservation->id, $secondOrder->reference_no))
                ->pluck('order_id')
                ->all(),
        );
        $this->assertCount(1, $service->searchEligibleOrders($reservation->id, limit: 1));

        $firstApplication = $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Application,
            'amount' => '10.0000',
            'order_id' => $firstOrder->id,
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'anonymous-reservation-first-application',
            'note' => 'Applied to the first group order.',
        ]);
        $secondApplication = $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Application,
            'amount' => '5.0000',
            'order_id' => $secondOrder->id,
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'anonymous-reservation-second-application',
            'note' => 'Applied to the second group order.',
        ]);

        $this->assertSame($reservation->id, $firstOrder->fresh()->reservation_id);
        $this->assertSame($reservation->id, $secondOrder->fresh()->reservation_id);
        $this->assertSame($firstOrder->customer_id, $firstApplication->customer_id);
        $this->assertSame($secondOrder->customer_id, $secondApplication->customer_id);
        $this->assertEquals(25, $reservation->fresh()->getRawOriginal('deposit_paid_amount'));
    }

    public function test_reservation_deposit_rejects_an_order_owned_by_another_reservation(): void
    {
        $this->app->instance(FinancialAccountLookupInterface::class, new NullFinancialAccountLookup);
        $owner = Reservation::factory()->create();
        $order = $this->createOrderWithProductsAndCharges([
            'branch_id' => $owner->branch_id,
            'reservation_id' => $owner->id,
        ]);
        $reservation = Reservation::factory()->create([
            'branch_id' => $order->branch_id,
            'customer_id' => null,
            'deposit_required' => true,
            'deposit_amount' => 20,
            'deposit_paid_amount' => 0,
            'deposit_currency' => 'JOD',
            'deposit_currency_rate' => 1,
        ]);
        $service = app(ReservationDepositServiceInterface::class);
        $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Collection,
            'amount' => '20.0000',
            'tender_method' => 'cash',
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'other-reservation-deposit-collection',
            'note' => 'Collected for another reservation.',
        ]);

        $eligibleOrderIds = collect($service->getFormMeta($reservation->id)['eligible_orders'])
            ->pluck('order_id');
        $this->assertFalse($eligibleOrderIds->contains($order->id));

        try {
            $service->record($reservation->id, [
                'type' => ReservationDepositTransactionType::Application,
                'amount' => '5.0000',
                'order_id' => $order->id,
                'economic_date' => '2026-07-22',
                'idempotency_key' => 'other-reservation-invalid-application',
                'note' => 'Must not be applied.',
            ]);
            $this->fail('An order linked to another reservation must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertEquals(20, $reservation->fresh()->getRawOriginal('deposit_paid_amount'));
    }

    public function test_reservation_deposit_order_limit_is_returned_as_an_amount_field_error(): void
    {
        $this->app->instance(FinancialAccountLookupInterface::class, new NullFinancialAccountLookup);
        $order = $this->createOrderWithProductsAndCharges();
        $reservation = Reservation::factory()->create([
            'branch_id' => $order->branch_id,
            'customer_id' => null,
            'deposit_required' => true,
            'deposit_amount' => 40,
            'deposit_paid_amount' => 0,
            'deposit_currency' => 'JOD',
            'deposit_currency_rate' => 1,
        ]);
        $service = app(ReservationDepositServiceInterface::class);
        $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Collection,
            'amount' => '40.0000',
            'tender_method' => 'cash',
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'order-limit-deposit-collection',
            'note' => 'Collected for order limit validation.',
        ]);

        try {
            $service->record($reservation->id, [
                'type' => ReservationDepositTransactionType::Application,
                'amount' => '30.0000',
                'order_id' => $order->id,
                'economic_date' => '2026-07-22',
                'idempotency_key' => 'order-limit-invalid-application',
                'note' => 'Exceeds the outstanding order balance.',
            ]);
            $this->fail('An excessive application amount must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertNull($order->fresh()->reservation_id);
        $this->assertEquals(40, $reservation->fresh()->getRawOriginal('deposit_paid_amount'));
    }

    public function test_partial_refund_credit_note_excludes_delivery_related_charge_lines(): void
    {
        $order = $this->createOrderWithProductsAndCharges();
        app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $order->products()->whereKey($order->products->first()->id)->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $creditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($creditNote);
        $this->assertSame(1, $creditNote->lines()->count());
        $this->assertSame(1, $creditNote->lines()->whereNotNull('order_product_id')->count());
        $this->assertSame(0, $creditNote->lines()->whereNotNull('order_charge_id')->count());
        $this->assertEquals(10.0, $creditNote->fresh()->total->amount());
    }

    public function test_partial_refund_credit_note_returns_only_tax_for_reversed_products(): void
    {
        $order = $this->createOrderWithProductsAndCharges();

        $firstProduct = $order->products->first();
        $secondProduct = $order->products->last();
        $tax = Tax::query()->create([
            'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
            'code' => 'vat_15',
            'rate' => 15,
            'type' => TaxType::Exclusive,
            'compound' => false,
            'is_global' => false,
            'order_types' => [OrderType::Delivery->value],
            'is_active' => true,
            'branch_id' => $order->branch_id,
        ]);

        $order->taxes()->createMany([
            [
                'order_product_id' => $firstProduct->id,
                'tax_id' => $tax->id,
                'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
                'rate' => 15,
                'currency' => 'JOD',
                'currency_rate' => 1,
                'amount' => 1.5,
                'type' => TaxType::Exclusive,
                'compound' => false,
            ],
            [
                'order_product_id' => $secondProduct->id,
                'tax_id' => $tax->id,
                'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
                'rate' => 15,
                'currency' => 'JOD',
                'currency_rate' => 1,
                'amount' => 1.2,
                'type' => TaxType::Exclusive,
                'compound' => false,
            ],
        ]);

        $firstProduct->update([
            'tax_total' => 1.5,
            'total' => 11.5,
        ]);

        $secondProduct->update([
            'tax_total' => 1.2,
            'total' => 9.2,
        ]);

        $order->update([
            'total' => 28.2,
            'due_amount' => 28.2,
        ]);

        app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $order->products()->whereKey($firstProduct->id)->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $creditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($creditNote);
        $this->assertEquals(10.0, $creditNote->fresh()->subtotal->amount());
        $this->assertEquals(1.5, $creditNote->fresh()->tax_total->amount());
        $this->assertEquals(11.5, $creditNote->fresh()->total->amount());
        $this->assertSame(1, InvoiceTax::query()->where('invoice_id', $creditNote->id)->count());
        $this->assertDatabaseHas('invoice_taxes', [
            'invoice_id' => $creditNote->id,
            'amount' => 1.5,
        ]);
    }

    public function test_partial_refund_credit_note_includes_proportional_order_level_tax(): void
    {
        $order = $this->createOrderWithProductsAndCharges();

        $firstProduct = $order->products->first();
        $tax = $this->createVatTax($order);

        $order->taxes()->create([
            'order_product_id' => $firstProduct->id,
            'tax_id' => $tax->id,
            'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
            'rate' => 15,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'amount' => 1.5,
            'type' => TaxType::Exclusive,
            'compound' => false,
        ]);

        $order->taxes()->create([
            'order_product_id' => null,
            'tax_id' => $tax->id,
            'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
            'rate' => 15,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'amount' => 2.7,
            'type' => TaxType::Exclusive,
            'compound' => false,
        ]);

        $firstProduct->update([
            'tax_total' => 1.5,
            'total' => 11.5,
        ]);

        $order->update([
            'total' => 27.5,
            'due_amount' => 27.5,
        ]);

        app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $order->products()->whereKey($firstProduct->id)->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $creditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($creditNote);
        $this->assertEquals(10.0, $creditNote->fresh()->subtotal->amount());
        $this->assertEquals(3.0, $creditNote->fresh()->tax_total->amount());
        $this->assertEquals(13.0, $creditNote->fresh()->total->amount());
        $this->assertSame(2, InvoiceTax::query()->where('invoice_id', $creditNote->id)->count());
        $this->assertSame(1, InvoiceTax::query()->where('invoice_id', $creditNote->id)->whereNull('invoice_line_id')->count());
        $this->assertDatabaseHas('invoice_taxes', [
            'invoice_id' => $creditNote->id,
            'invoice_line_id' => null,
            'amount' => 1.5,
        ]);
    }

    public function test_partial_cancellation_credit_note_excludes_delivery_related_charge_lines_and_includes_proportional_order_level_tax(): void
    {
        $order = $this->createOrderWithProductsAndCharges([
            'status' => OrderStatus::Cancelled,
        ]);

        $firstProduct = $order->products->first();
        $tax = $this->createVatTax($order);

        $order->taxes()->create([
            'order_product_id' => $firstProduct->id,
            'tax_id' => $tax->id,
            'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
            'rate' => 15,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'amount' => 1.5,
            'type' => TaxType::Exclusive,
            'compound' => false,
        ]);

        $order->taxes()->create([
            'order_product_id' => null,
            'tax_id' => $tax->id,
            'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
            'rate' => 15,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'amount' => 2.7,
            'type' => TaxType::Exclusive,
            'compound' => false,
        ]);

        $firstProduct->update([
            'tax_total' => 1.5,
            'total' => 11.5,
        ]);

        $order->update([
            'total' => 27.5,
            'due_amount' => 27.5,
        ]);

        app(CreateInvoiceServiceInterface::class)->create($order->fresh());
        $firstProduct->update(['status' => OrderProductStatus::Cancelled]);

        $creditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Cancellation);

        $this->assertNotNull($creditNote);
        $this->assertSame(1, $creditNote->lines()->count());
        $this->assertSame(1, $creditNote->lines()->whereNotNull('order_product_id')->count());
        $this->assertSame(0, $creditNote->lines()->whereNotNull('order_charge_id')->count());
        $this->assertEquals(10.0, $creditNote->fresh()->subtotal->amount());
        $this->assertEquals(3.0, $creditNote->fresh()->tax_total->amount());
        $this->assertEquals(13.0, $creditNote->fresh()->total->amount());
        $this->assertSame(2, InvoiceTax::query()->where('invoice_id', $creditNote->id)->count());
        $this->assertSame(1, InvoiceTax::query()->where('invoice_id', $creditNote->id)->whereNull('invoice_line_id')->count());
        $this->assertDatabaseHas('invoice_taxes', [
            'invoice_id' => $creditNote->id,
            'invoice_line_id' => null,
            'amount' => 1.5,
        ]);
    }

    public function test_full_refund_credit_note_includes_all_refundable_delivery_related_charges(): void
    {
        $order = $this->createOrderWithProductsAndCharges();
        app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $order->products()->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $creditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($creditNote);
        $this->assertSame(5, $creditNote->lines()->count());
        $this->assertSame(3, $creditNote->lines()->whereNotNull('order_charge_id')->count());
        $this->assertEquals(25.5, $creditNote->fresh()->total->amount());
    }

    public function test_full_refund_credit_note_includes_order_level_tax(): void
    {
        $order = $this->createOrderWithProductsAndCharges();

        $firstProduct = $order->products->first();
        $secondProduct = $order->products->last();
        $tax = $this->createVatTax($order);

        $order->taxes()->createMany([
            [
                'order_product_id' => $firstProduct->id,
                'tax_id' => $tax->id,
                'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
                'rate' => 15,
                'currency' => 'JOD',
                'currency_rate' => 1,
                'amount' => 1.5,
                'type' => TaxType::Exclusive,
                'compound' => false,
            ],
            [
                'order_product_id' => $secondProduct->id,
                'tax_id' => $tax->id,
                'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
                'rate' => 15,
                'currency' => 'JOD',
                'currency_rate' => 1,
                'amount' => 1.2,
                'type' => TaxType::Exclusive,
                'compound' => false,
            ],
            [
                'order_product_id' => null,
                'tax_id' => $tax->id,
                'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
                'rate' => 15,
                'currency' => 'JOD',
                'currency_rate' => 1,
                'amount' => 2.7,
                'type' => TaxType::Exclusive,
                'compound' => false,
            ],
        ]);

        $firstProduct->update([
            'tax_total' => 1.5,
            'total' => 11.5,
        ]);

        $secondProduct->update([
            'tax_total' => 1.2,
            'total' => 9.2,
        ]);

        $order->update([
            'total' => 30.2,
            'due_amount' => 30.2,
        ]);

        app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $order->products()->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $creditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($creditNote);
        $this->assertEquals(25.5, $creditNote->fresh()->subtotal->amount());
        $this->assertEquals(5.4, $creditNote->fresh()->tax_total->amount());
        $this->assertEquals(30.9, $creditNote->fresh()->total->amount());
        $this->assertSame(3, InvoiceTax::query()->where('invoice_id', $creditNote->id)->count());
        $this->assertSame(1, InvoiceTax::query()->where('invoice_id', $creditNote->id)->whereNull('invoice_line_id')->count());
        $this->assertEquals(5.4, InvoiceTax::query()->where('invoice_id', $creditNote->id)->sum('amount'));
        $this->assertDatabaseHas('invoice_taxes', [
            'invoice_id' => $creditNote->id,
            'invoice_line_id' => null,
            'amount' => 2.7,
        ]);
    }

    public function test_full_cancellation_credit_note_includes_all_refundable_delivery_related_charges(): void
    {
        $order = $this->createOrderWithProductsAndCharges([
            'status' => OrderStatus::Cancelled,
        ]);
        app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $order->products()->update([
            'status' => OrderProductStatus::Cancelled,
        ]);

        $creditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Cancellation);

        $this->assertNotNull($creditNote);
        $this->assertSame(5, $creditNote->lines()->count());
        $this->assertSame(3, $creditNote->lines()->whereNotNull('order_charge_id')->count());
        $this->assertEquals(25.5, $creditNote->fresh()->total->amount());
    }

    public function test_full_cancellation_credit_note_includes_order_level_tax(): void
    {
        $order = $this->createOrderWithProductsAndCharges([
            'status' => OrderStatus::Cancelled,
        ]);

        $firstProduct = $order->products->first();
        $secondProduct = $order->products->last();
        $tax = $this->createVatTax($order);

        $order->taxes()->createMany([
            [
                'order_product_id' => $firstProduct->id,
                'tax_id' => $tax->id,
                'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
                'rate' => 15,
                'currency' => 'JOD',
                'currency_rate' => 1,
                'amount' => 1.5,
                'type' => TaxType::Exclusive,
                'compound' => false,
            ],
            [
                'order_product_id' => $secondProduct->id,
                'tax_id' => $tax->id,
                'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
                'rate' => 15,
                'currency' => 'JOD',
                'currency_rate' => 1,
                'amount' => 1.2,
                'type' => TaxType::Exclusive,
                'compound' => false,
            ],
            [
                'order_product_id' => null,
                'tax_id' => $tax->id,
                'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
                'rate' => 15,
                'currency' => 'JOD',
                'currency_rate' => 1,
                'amount' => 2.7,
                'type' => TaxType::Exclusive,
                'compound' => false,
            ],
        ]);

        $firstProduct->update([
            'tax_total' => 1.5,
            'total' => 11.5,
        ]);

        $secondProduct->update([
            'tax_total' => 1.2,
            'total' => 9.2,
        ]);

        $order->update([
            'total' => 30.2,
            'due_amount' => 30.2,
        ]);

        app(CreateInvoiceServiceInterface::class)->create($order->fresh());
        $order->products()->update(['status' => OrderProductStatus::Cancelled]);

        $creditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Cancellation);

        $this->assertNotNull($creditNote);
        $this->assertEquals(25.5, $creditNote->fresh()->subtotal->amount());
        $this->assertEquals(5.4, $creditNote->fresh()->tax_total->amount());
        $this->assertEquals(30.9, $creditNote->fresh()->total->amount());
        $this->assertSame(3, InvoiceTax::query()->where('invoice_id', $creditNote->id)->count());
        $this->assertSame(1, InvoiceTax::query()->where('invoice_id', $creditNote->id)->whereNull('invoice_line_id')->count());
        $this->assertEquals(5.4, InvoiceTax::query()->where('invoice_id', $creditNote->id)->sum('amount'));
        $this->assertDatabaseHas('invoice_taxes', [
            'invoice_id' => $creditNote->id,
            'invoice_line_id' => null,
            'amount' => 2.7,
        ]);
    }

    public function test_unknown_charge_code_is_not_refunded_and_emits_warning_log(): void
    {
        Log::spy();

        $order = $this->createOrderWithProductsAndCharges();
        $order->charges()->create([
            'code' => 'mystery_fee',
            'name' => ['en' => 'Mystery Fee', 'ar' => 'رسم غامض'],
            'currency' => 'JOD',
            'currency_rate' => 1,
            'unit_amount' => 1.5,
            'quantity' => 1,
            'amount' => 1.5,
        ]);
        $order->update([
            'total' => 27.0,
            'due_amount' => 27.0,
        ]);

        app(CreateInvoiceServiceInterface::class)->create($order->fresh());
        $order->products()->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $creditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($creditNote);
        $this->assertSame(3, $creditNote->lines()->whereNotNull('order_charge_id')->count());
        $this->assertDatabaseMissing('invoice_lines', [
            'invoice_id' => $creditNote->id,
            'order_charge_id' => $order->charges()->where('code', 'mystery_fee')->value('id'),
        ]);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_duplicate_full_order_credit_note_creation_does_not_duplicate_product_or_charge_reversal_lines(): void
    {
        $order = $this->createOrderWithProductsAndCharges();
        app(CreateInvoiceServiceInterface::class)->create($order->fresh());
        $order->products()->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $firstCreditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);
        $secondCreditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);

        $this->assertSame(3, $firstCreditNote->lines()->whereNotNull('order_charge_id')->count());
        $this->assertSame(2, $firstCreditNote->lines()->whereNotNull('order_product_id')->count());
        $this->assertNull($secondCreditNote);
    }

    public function test_duplicate_partial_refund_credit_note_creation_does_not_duplicate_product_reversal_lines(): void
    {
        $order = $this->createOrderWithProductsAndCharges();
        app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $refundedProductId = $order->products->first()->id;
        $order->products()->whereKey($refundedProductId)->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $firstCreditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);
        $secondCreditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($firstCreditNote);
        $this->assertSame(1, $firstCreditNote->lines()->whereNotNull('order_product_id')->count());
        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $firstCreditNote->id,
            'order_product_id' => $refundedProductId,
        ]);
        $this->assertNull($secondCreditNote);
    }

    public function test_partial_credit_notes_allocate_the_discount_once_and_reconcile_on_full_reversal(): void
    {
        $order = $this->createOrderWithProductsAndCharges();
        $this->addOrderDiscount($order, 5);
        $original = app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $firstProductId = $order->products->first()->id;
        $secondProductId = $order->products->last()->id;

        $order->products()->whereKey($firstProductId)->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $firstCreditNote = app(CreateCreditNoteServiceInterface::class)
            ->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($firstCreditNote);
        $this->assertEquals(2.778, $firstCreditNote->fresh()->discount_total->amount());
        $this->assertEquals(7.222, $firstCreditNote->fresh()->total->amount());

        $order->products()->whereKey($secondProductId)->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $secondCreditNote = app(CreateCreditNoteServiceInterface::class)
            ->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($secondCreditNote);
        $this->assertEquals(2.222, $secondCreditNote->fresh()->discount_total->amount());
        $this->assertEquals(
            $original->fresh()->total->amount(),
            $firstCreditNote->fresh()->total->amount() + $secondCreditNote->fresh()->total->amount(),
        );
        $this->assertEquals(
            5.0,
            InvoiceDiscount::query()
                ->whereIn('invoice_id', [$firstCreditNote->id, $secondCreditNote->id])
                ->sum('amount'),
        );
    }

    public function test_cancelled_order_listener_creates_a_cancellation_credit_note(): void
    {
        $order = $this->createOrderWithProductsAndCharges([
            'payment_status' => OrderPaymentStatus::Paid,
        ]);
        app(CreateInvoiceServiceInterface::class)->create($order->fresh());
        $order->products()->update(['status' => OrderProductStatus::Cancelled]);

        (new CreateOrderCreditNote)->handle(new OrderVoided(
            order: $order->fresh(),
            status: OrderStatus::Cancelled,
        ));

        $creditNote = Invoice::query()
            ->where('order_id', $order->id)
            ->whereNotNull('reference_invoice_id')
            ->latest('id')
            ->first();

        $this->assertNotNull($creditNote);
        $this->assertSame(InvoicePurpose::Cancellation, $creditNote->purpose);
    }

    public function test_noop_credit_note_does_not_consume_a_number_or_create_party_snapshots(): void
    {
        $order = $this->createOrderWithProductsAndCharges();
        app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $partyCount = InvoiceParty::query()->count();
        $counter = InvoiceCounter::query()
            ->where('cr_number', $order->branch->registration_number)
            ->value('last_counter');

        $creditNote = app(CreateCreditNoteServiceInterface::class)
            ->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNull($creditNote);
        $this->assertSame($partyCount, InvoiceParty::query()->count());
        $this->assertSame(
            $counter,
            InvoiceCounter::query()
                ->where('cr_number', $order->branch->registration_number)
                ->value('last_counter'),
        );
    }

    public function test_merged_orders_keep_remaining_order_level_tax_scoped_to_each_order(): void
    {
        $firstOrder = $this->createOrderWithProductsAndCharges();
        $floor = Floor::factory()->create([
            'branch_id' => $firstOrder->branch_id,
            'name' => ['en' => 'Main Floor', 'ar' => 'الطابق الرئيسي'],
            'is_active' => true,
        ]);
        $zone = Zone::factory()->create([
            'branch_id' => $firstOrder->branch_id,
            'floor_id' => $floor->id,
            'name' => ['en' => 'Main Zone', 'ar' => 'المنطقة الرئيسية'],
            'is_active' => true,
        ]);
        $table = Table::factory()->create([
            'branch_id' => $firstOrder->branch_id,
            'floor_id' => $floor->id,
            'zone_id' => $zone->id,
        ]);
        $merge = TableMerge::query()->create([
            'branch_id' => $firstOrder->branch_id,
            'table_id' => $table->id,
            'type' => TableMergeType::Billing,
        ]);
        $firstOrder->update(['table_merge_id' => $merge->id]);

        $secondOrder = $this->createOrderWithProductsAndCharges([
            'branch_id' => $firstOrder->branch_id,
            'table_merge_id' => $merge->id,
        ]);
        $tax = $this->createVatTax($firstOrder);

        foreach ([$firstOrder, $secondOrder] as $order) {
            $order->taxes()->create([
                'tax_id' => $tax->id,
                'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
                'rate' => 15,
                'currency' => 'JOD',
                'currency_rate' => 1,
                'amount' => 2.7,
                'type' => TaxType::Exclusive,
                'compound' => false,
            ]);
        }

        $partiallyLoadedOrder = Order::query()
            ->with([
                'branch' => fn ($query) => $query->select([
                    'id',
                    'name',
                    'legal_name',
                    'vat_tin',
                    'registration_number',
                    'address_line1',
                    'address_line2',
                    'city',
                    'state',
                    'country_code',
                    'postal_code',
                    'email',
                ]),
            ])
            ->findOrFail($firstOrder->id);

        $this->assertArrayNotHasKey('phone', $partiallyLoadedOrder->branch->getAttributes());

        app(CreateInvoiceServiceInterface::class)->create($partiallyLoadedOrder);

        $firstOrder->products()->update(['status' => OrderProductStatus::Refunded]);
        $firstCreditNote = app(CreateCreditNoteServiceInterface::class)
            ->create($firstOrder->fresh(), InvoicePurpose::Return);

        $secondOrder->products()->update(['status' => OrderProductStatus::Refunded]);
        $secondCreditNote = app(CreateCreditNoteServiceInterface::class)
            ->create($secondOrder->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($firstCreditNote);
        $this->assertNotNull($secondCreditNote);
        $this->assertEquals(2.7, $firstCreditNote->taxes()->whereNull('invoice_line_id')->sum('amount'));
        $this->assertEquals(2.7, $secondCreditNote->taxes()->whereNull('invoice_line_id')->sum('amount'));
    }

    public function test_credit_note_uses_original_invoice_snapshots_after_operational_data_changes(): void
    {
        $order = $this->createOrderWithProductsAndCharges();
        $tax = $this->createVatTax($order);
        $order->taxes()->create([
            'tax_id' => $tax->id,
            'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
            'rate' => 15,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'amount' => 2.7,
            'type' => TaxType::Exclusive,
            'compound' => false,
        ]);
        $discount = $this->addOrderDiscount($order, 5);
        $original = app(CreateInvoiceServiceInterface::class)->create($order->fresh());
        $originalLine = $original->lines()->whereNotNull('order_product_id')->firstOrFail();
        $originalDescription = $originalLine->getTranslations('description');

        $order->products()->update([
            'unit_price' => 999,
            'subtotal' => 999,
            'tax_total' => 999,
            'total' => 1998,
            'status' => OrderProductStatus::Refunded,
        ]);
        $order->products()->firstOrFail()->product->update([
            'name' => ['en' => 'Changed after invoice', 'ar' => 'تم التغيير بعد الفاتورة'],
        ]);
        $order->update([
            'currency' => 'USD',
            'currency_rate' => 0.71,
        ]);
        $order->taxes()->update(['amount' => 999]);
        $discount->update(['amount' => 999]);

        $creditNote = app(CreateCreditNoteServiceInterface::class)
            ->create($order->fresh(), InvoicePurpose::Return);

        $creditedLine = $creditNote->lines()
            ->where('order_product_id', $originalLine->order_product_id)
            ->firstOrFail();
        $this->assertSame($originalDescription, $creditedLine->getTranslations('description'));
        $this->assertEquals(
            $originalLine->getRawOriginal('line_total_excl_tax'),
            $creditedLine->getRawOriginal('line_total_excl_tax'),
        );
        $this->assertEquals($original->fresh()->subtotal->amount(), $creditNote->fresh()->subtotal->amount());
        $this->assertEquals($original->fresh()->tax_total->amount(), $creditNote->fresh()->tax_total->amount());
        $this->assertEquals($original->fresh()->discount_total->amount(), $creditNote->fresh()->discount_total->amount());
        $this->assertEquals($original->fresh()->total->amount(), $creditNote->fresh()->total->amount());
        $this->assertSame($original->currency, $creditNote->currency);
        $this->assertSame(
            $original->getRawOriginal('currency_rate'),
            $creditNote->getRawOriginal('currency_rate'),
        );
    }

    public function test_credit_note_rolls_back_when_cumulative_balances_exceed_the_original_invoice(): void
    {
        $order = $this->createOrderWithProductsAndCharges();
        $original = app(CreateInvoiceServiceInterface::class)->create($order->fresh());
        $firstProductId = $order->products->first()->id;

        $order->products()->whereKey($firstProductId)->update([
            'status' => OrderProductStatus::Refunded,
        ]);
        $firstCreditNote = app(CreateCreditNoteServiceInterface::class)
            ->create($order->fresh(), InvoicePurpose::Return);
        $firstCreditNote->update([
            'subtotal' => $original->getRawOriginal('subtotal'),
            'total' => $original->getRawOriginal('total'),
        ]);

        $order->products()->whereKeyNot($firstProductId)->update([
            'status' => OrderProductStatus::Refunded,
        ]);
        $invoiceCount = Invoice::query()->count();

        try {
            app(CreateCreditNoteServiceInterface::class)
                ->create($order->fresh(), InvoicePurpose::Return);
            $this->fail('Expected the cumulative credit-note balance guard to reject the transaction.');
        } catch (DomainException) {
            $this->assertSame($invoiceCount, Invoice::query()->count());
        }
    }

    public function test_later_partial_refund_credit_note_includes_only_newly_refunded_product_lines(): void
    {
        $order = $this->createOrderWithProductsAndCharges();
        app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $firstProductId = $order->products->first()->id;
        $secondProductId = $order->products->last()->id;

        $order->products()->whereKey($firstProductId)->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $firstCreditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($firstCreditNote);
        $this->assertSame(1, $firstCreditNote->lines()->whereNotNull('order_product_id')->count());
        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $firstCreditNote->id,
            'order_product_id' => $firstProductId,
        ]);

        $order->products()->whereKey($secondProductId)->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $secondCreditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($secondCreditNote);
        $this->assertSame(1, $secondCreditNote->lines()->whereNotNull('order_product_id')->count());
        $this->assertDatabaseMissing('invoice_lines', [
            'invoice_id' => $secondCreditNote->id,
            'order_product_id' => $firstProductId,
        ]);
        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $secondCreditNote->id,
            'order_product_id' => $secondProductId,
        ]);
    }

    public function test_last_refunded_product_credit_note_includes_only_remaining_order_level_tax_and_charge_lines(): void
    {
        $order = $this->createOrderWithProductsAndCharges();

        $menu = Menu::factory()->create([
            'branch_id' => $order->branch_id,
            'is_active' => true,
        ]);

        $thirdProduct = Product::factory()->create([
            'menu_id' => $menu->id,
            'is_active' => true,
            'sku' => 'SKU-C',
        ]);

        $thirdOrderProduct = OrderProduct::query()->create([
            'order_id' => $order->id,
            'product_id' => $thirdProduct->id,
            'created_by' => User::factory()->create()->id,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'unit_price' => 12,
            'quantity' => 1,
            'subtotal' => 12,
            'tax_total' => 0,
            'total' => 12,
            'cost_price' => 0,
            'gross_profit' => 12,
            'status' => OrderProductStatus::Served,
        ]);

        $tax = $this->createVatTax($order);

        $order->taxes()->create([
            'order_product_id' => null,
            'tax_id' => $tax->id,
            'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
            'rate' => 15,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'amount' => 4.5,
            'type' => TaxType::Exclusive,
            'compound' => false,
        ]);

        $order->update([
            'subtotal' => 30,
            'total' => 42,
            'due_amount' => 42,
        ]);

        app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $firstProductId = $order->products->first()->id;
        $secondProductId = $order->products->last()->id;
        $thirdProductId = $thirdOrderProduct->id;

        $order->products()->whereKey($firstProductId)->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $firstCreditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);
        $this->assertNotNull($firstCreditNote);
        $this->assertEquals(1.5, $firstCreditNote->fresh()->tax_total->amount());

        $order->products()->whereKey($secondProductId)->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $secondCreditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);
        $this->assertNotNull($secondCreditNote);
        $this->assertEquals(1.2, $secondCreditNote->fresh()->tax_total->amount());

        $order->products()->whereKey($thirdProductId)->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $thirdCreditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($thirdCreditNote);
        $this->assertSame(1, $thirdCreditNote->lines()->whereNotNull('order_product_id')->count());
        $this->assertSame(3, $thirdCreditNote->lines()->whereNotNull('order_charge_id')->count());
        $this->assertDatabaseMissing('invoice_lines', [
            'invoice_id' => $thirdCreditNote->id,
            'order_product_id' => $firstProductId,
        ]);
        $this->assertDatabaseMissing('invoice_lines', [
            'invoice_id' => $thirdCreditNote->id,
            'order_product_id' => $secondProductId,
        ]);
        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $thirdCreditNote->id,
            'order_product_id' => $thirdProductId,
        ]);
        $this->assertEquals(19.5, $thirdCreditNote->fresh()->subtotal->amount());
        $this->assertEquals(1.8, $thirdCreditNote->fresh()->tax_total->amount());
        $this->assertEquals(21.3, $thirdCreditNote->fresh()->total->amount());
        $this->assertDatabaseHas('invoice_taxes', [
            'invoice_id' => $thirdCreditNote->id,
            'invoice_line_id' => null,
            'amount' => 1.8,
        ]);
    }

    public function test_credit_note_is_not_created_when_no_reversed_products_or_eligible_charge_lines_exist(): void
    {
        $order = $this->createOrderWithProductsAndCharges();
        app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $creditNote = app(CreateCreditNoteServiceInterface::class)->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNull($creditNote);
    }

    public function test_credit_note_records_accounting_diagnostic_without_blocking_creation_when_posting_fails(): void
    {
        $order = $this->createOrderWithProductsAndCharges();
        app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $bridge = new class implements FinancialPostingBridgeInterface
        {
            /** @var array<int, array<string, mixed>> */
            public array $failures = [];

            public function recordEvent(string $sourceType, int $sourceId, string $event, array $payload = []): void
            {
                throw new RuntimeException('Accounting posting is unavailable.');
            }

            public function recordFailure(
                string $sourceType,
                int $sourceId,
                string $event,
                string $errorCode,
                string $message,
                array $payload = [],
            ): void {
                $this->failures[] = compact(
                    'sourceType',
                    'sourceId',
                    'event',
                    'errorCode',
                    'message',
                    'payload',
                );
            }
        };

        $this->app->instance(FinancialPostingBridgeInterface::class, $bridge);
        $order->products()->whereKey($order->products->first()->id)->update([
            'status' => OrderProductStatus::Refunded,
        ]);

        $creditNote = app(CreateCreditNoteServiceInterface::class)
            ->create($order->fresh(), InvoicePurpose::Return);

        $this->assertNotNull($creditNote);
        $this->assertDatabaseHas('invoices', ['id' => $creditNote->id]);
        $this->assertCount(1, $bridge->failures);
        $this->assertSame('invoice', $bridge->failures[0]['sourceType']);
        $this->assertSame($creditNote->id, $bridge->failures[0]['sourceId']);
        $this->assertSame('issued', $bridge->failures[0]['event']);
        $this->assertSame('RuntimeException', $bridge->failures[0]['errorCode']);
        $this->assertSame(
            "invoice:{$creditNote->id}:issued",
            $bridge->failures[0]['payload']['idempotency_key'],
        );
    }

    public function test_current_delivery_related_charge_codes_are_explicitly_non_taxable_and_create_no_tax_rows(): void
    {
        $order = $this->createOrderWithProductsAndCharges();
        $invoice = app(CreateInvoiceServiceInterface::class)->create($order->fresh());

        $this->assertSame(0, $invoice->lines()->whereNotNull('order_charge_id')->where('tax_amount', '>', 0)->count());
        $this->assertSame(0, InvoiceTax::query()->whereIn('invoice_line_id', $invoice->lines()->whereNotNull('order_charge_id')->pluck('id'))->count());
        $this->assertTrue($order->charges->every(fn (OrderCharge $charge) => $charge->taxable === false));
    }

    public function test_charge_policy_marks_current_codes_refundable_on_full_order_reversal_only_and_unknown_codes_safe(): void
    {
        $resolver = app(OrderChargeInvoicePolicyResolverServiceInterface::class);
        $order = $this->createOrderWithProductsAndCharges();

        foreach ($order->charges as $charge) {
            $this->assertTrue($resolver->shouldRefundOnFullOrderReversal($charge));
            $this->assertFalse($resolver->isTaxable($charge));
        }

        Log::spy();

        $unknownCharge = $order->charges()->create([
            'code' => 'platform_fee',
            'name' => ['en' => 'Platform Fee', 'ar' => 'رسم منصة'],
            'currency' => 'JOD',
            'currency_rate' => 1,
            'unit_amount' => 1,
            'quantity' => 1,
            'amount' => 1,
        ]);

        $this->assertFalse($resolver->shouldRefundOnFullOrderReversal($unknownCharge));
        $this->assertFalse($resolver->isTaxable($unknownCharge));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_full_order_reversal_resolver_uses_actual_product_states(): void
    {
        $resolver = app(FullOrderReversalResolverServiceInterface::class);
        $order = $this->createOrderWithProductsAndCharges();

        $this->assertFalse($resolver->isFullOrderReversal($order));

        $firstProductId = $order->products->first()->id;
        $order->products()->whereKey($firstProductId)->update(['status' => OrderProductStatus::Refunded]);
        $this->assertFalse($resolver->isFullOrderReversal($order->fresh()));

        $order->products()->update(['status' => OrderProductStatus::Refunded]);
        $this->assertTrue($resolver->isFullOrderReversal($order->fresh()));

        $emptyOrder = $this->createOrderOnly();
        $this->assertFalse($resolver->isFullOrderReversal($emptyOrder));
    }

    protected function createOrderWithProductsAndCharges(array $orderOverrides = []): Order
    {
        $order = $this->createOrderOnly($orderOverrides);

        $menu = Menu::factory()->create([
            'branch_id' => $order->branch_id,
            'is_active' => true,
        ]);

        $productA = Product::factory()->create([
            'menu_id' => $menu->id,
            'is_active' => true,
            'sku' => 'SKU-A',
        ]);

        $productB = Product::factory()->create([
            'menu_id' => $menu->id,
            'is_active' => true,
            'sku' => 'SKU-B',
        ]);

        OrderProduct::query()->create([
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'created_by' => User::factory()->create()->id,
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

        OrderProduct::query()->create([
            'order_id' => $order->id,
            'product_id' => $productB->id,
            'created_by' => User::factory()->create()->id,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'unit_price' => 4,
            'quantity' => 2,
            'subtotal' => 8,
            'tax_total' => 0,
            'total' => 8,
            'cost_price' => 0,
            'gross_profit' => 8,
            'status' => OrderProductStatus::Served,
        ]);

        $order->charges()->createMany([
            [
                'code' => OrderChargeCode::DeliveryFee->value,
                'name' => ['en' => 'Delivery Fee', 'ar' => 'رسوم التوصيل'],
                'currency' => 'JOD',
                'currency_rate' => 1,
                'unit_amount' => 5,
                'quantity' => 1,
                'amount' => 5,
                'taxable' => false,
                'tax_metadata' => null,
            ],
            [
                'code' => OrderChargeCode::SmallOrderSurcharge->value,
                'name' => ['en' => 'Small Order Surcharge', 'ar' => 'رسم الحد الأدنى'],
                'currency' => 'JOD',
                'currency_rate' => 1,
                'unit_amount' => 1.5,
                'quantity' => 1,
                'amount' => 1.5,
                'taxable' => false,
                'tax_metadata' => null,
            ],
            [
                'code' => OrderChargeCode::PeakTimeSurcharge->value,
                'name' => ['en' => 'Peak Time Surcharge', 'ar' => 'رسم وقت الذروة'],
                'currency' => 'JOD',
                'currency_rate' => 1,
                'unit_amount' => 1,
                'quantity' => 1,
                'amount' => 1,
                'taxable' => false,
                'tax_metadata' => null,
            ],
        ]);

        $order->update([
            'subtotal' => 18,
            'total' => 25.5,
            'due_amount' => 25.5,
        ]);

        return $order->fresh(['products', 'charges']);
    }

    protected function createOrderOnly(array $overrides = []): Order
    {
        $branch = Branch::factory()->create([
            'currency' => 'JOD',
            'order_types' => [OrderType::Delivery->value],
        ]);
        $customer = User::factory()->create();

        return Order::query()->create(array_merge([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'type' => OrderType::Delivery,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'subtotal' => 0,
            'total' => 0,
            'due_amount' => 0,
            'guest_count' => 1,
            'order_date' => now(),
        ], $overrides));
    }

    protected function createVatTax(Order $order): Tax
    {
        return Tax::query()->create([
            'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة القيمة المضافة 15%'],
            'code' => 'vat_15',
            'rate' => 15,
            'type' => TaxType::Exclusive,
            'compound' => false,
            'is_global' => false,
            'order_types' => [OrderType::Delivery->value],
            'is_active' => true,
            'branch_id' => $order->branch_id,
        ]);
    }

    protected function addOrderDiscount(Order $order, float $amount): OrderDiscount
    {
        $discount = Discount::factory()->create([
            'branch_id' => $order->branch_id,
            'is_active' => true,
        ]);

        return OrderDiscount::query()->create([
            'order_id' => $order->id,
            'discountable_id' => $discount->id,
            'discountable_type' => Discount::class,
            'type' => DiscountType::Discount,
            'currency' => $order->currency,
            'currency_rate' => $order->currency_rate,
            'amount' => $amount,
        ]);
    }
}
