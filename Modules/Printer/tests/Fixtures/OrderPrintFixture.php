<?php

namespace Modules\Printer\Tests\Fixtures;

final class OrderPrintFixture
{
    public static function make(int $count = 3, bool $arabic = false): array
    {
        $products = [];
        for ($i = 1; $i <= $count; $i++) {
            $products[] = [
                'name' => ($arabic ? 'ساندويش الدجاج المشوي مع الخضار' : 'Grilled chicken sandwich with vegetables').' ITEM-'.$i,
                'description' => ($arabic ? 'ساندويش الدجاج المشوي مع الخضار' : 'Grilled chicken sandwich with vegetables').' ITEM-'.$i,
                'sku' => 'SKU-'.$i,
                'quantity' => 1.5,
                'unit_price' => 4,
                'subtotal' => 6,
                'total' => 6,
                'line_total_incl_tax' => 6,
                'tax_total' => 0.3,
                'tax_amount' => 0.3,
                'options' => [['name' => $arabic ? 'إضافات' : 'Extras', 'values' => [['label' => $arabic ? 'بدون بصل، صلصة جانبية' : 'No onions, sauce on the side', 'price' => 0]]]],
            ];
        }

        return [
            'order' => ['order_number' => 'A-1042', 'type' => $arabic ? 'محلي' : 'Dine in', 'order_date' => '2026-09-06 12:30', 'subtotal' => $count * 6, 'total' => $count * 6 + 0.5, 'due_amount' => 0, 'notes' => $arabic ? 'يرجى التأكد من الإضافات قبل التقديم.' : 'Please check the modifiers before serving.'],
            'branch' => ['name' => $arabic ? 'فوركيفا' : 'Forkiva', 'address_line1' => $arabic ? 'شارع الحدائق، عمان' : 'Garden Street, Amman', 'phone' => '+962 6 555 0100'],
            'customer' => ['name' => $arabic ? 'عبد الرحمن محمد الخطيب' : 'Alexandra Montgomery-Williams', 'phone' => '+962 79 555 0100'],
            'table' => ['name' => 'T-12'],
            'waiter' => ['name' => $arabic ? 'أحمد' : 'Ahmed'],
            'products' => $products,
            'lines' => $products,
            'discount' => ['name' => $arabic ? 'خصم العميل' : 'Loyalty', 'amount' => 1],
            'discounts' => [['name' => $arabic ? 'خصم العميل' : 'Loyalty', 'amount' => 1]],
            'charges' => [['name' => $arabic ? 'الخدمة' : 'Service', 'amount' => 0.5]],
            'tax_summary' => [['name' => 'VAT', 'rate' => 5, 'type' => 'exclusive', 'scope' => 'global', 'amount' => 1]],
            'payments' => [['method' => $arabic ? 'نقدي' : 'Cash', 'amount' => $count * 6 + 0.5, 'transaction_id' => 'PAY-00012345']],
            'delivery' => ['customer_address' => ['fullAddress' => $arabic ? 'شارع الحدائق، بناية 12، الطابق الثاني، عمان' : 'Garden Street, building 12, second floor, Amman']],
            'currency_subunit' => 3,
            'invoice_number' => 'INV-1042',
            'issued_at' => ['full_date' => '2026-09-06 12:30'],
            'seller' => ['legal_name' => $arabic ? 'فوركيفا' : 'Forkiva', 'vat_tin' => '123456789', 'address_line1' => $arabic ? 'عمان' : 'Amman'],
            'buyer' => ['legal_name' => $arabic ? 'عميل نقدي' : 'Cash customer'],
            'subtotal' => $count * 6,
            'total' => $count * 6,
            'paid_amount' => $count * 6,
            'net_paid' => $count * 6,
            'allocations' => [],
        ];
    }
}
