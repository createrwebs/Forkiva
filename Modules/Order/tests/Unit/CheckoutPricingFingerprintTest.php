<?php

namespace Modules\Order\Tests\Unit;

use Modules\Order\Support\CheckoutPricingFingerprint;
use PHPUnit\Framework\TestCase;

class CheckoutPricingFingerprintTest extends TestCase
{
    private function pricing(): array
    {
        $money = fn ($amount) => ['amount' => $amount, 'currency' => 'JOD', 'precision' => 3, 'formatted' => (string) $amount];

        return [
            'quantity' => 1, 'subTotal' => $money(10), 'total' => $money(11),
            'items' => [['id' => 'line', 'qty' => 1, 'unitPrice' => $money(10), 'subtotal' => $money(10), 'taxTotal' => $money(0), 'total' => $money(10)]],
            'taxes' => [['id' => 1, 'rate' => 10, 'type' => 'exclusive', 'scope' => 'global', 'amount' => $money(1)]],
            'charges' => [['code' => 'delivery', 'amount' => $money(0)]],
        ];
    }

    public function test_ignores_display_changes_and_sub_precision_float_noise(): void
    {
        $before = $this->pricing();
        $after = $before;
        $after['total']['amount'] = 11.000000000000002;
        $after['total']['formatted'] = '١١٫٠٠٠ د.أ';
        $after['items'][0]['catalog_product'] = ['thumbnail' => 'different-url'];
        $after['taxes'][0]['name'] = 'Translated tax';
        $this->assertSame(CheckoutPricingFingerprint::hash($before), CheckoutPricingFingerprint::hash($after));
    }

    public function test_detects_one_fils_and_offsetting_financial_changes(): void
    {
        $before = $this->pricing();
        $after = $before;
        $after['total']['amount'] = 11.001;
        $this->assertNotSame(CheckoutPricingFingerprint::hash($before), CheckoutPricingFingerprint::hash($after));
        $after = $before;
        $after['taxes'][0]['amount']['amount'] = 0.5;
        $after['charges'][0]['amount']['amount'] = 0.5;
        $this->assertNotSame(CheckoutPricingFingerprint::hash($before), CheckoutPricingFingerprint::hash($after));
    }

    public function test_ignores_row_order_but_preserves_line_prices(): void
    {
        $before = $this->pricing();
        $before['items'][] = [...$before['items'][0], 'id' => 'second'];
        $after = $before;
        $after['items'] = array_reverse($after['items']);
        $this->assertSame(CheckoutPricingFingerprint::hash($before), CheckoutPricingFingerprint::hash($after));
        $after['items'][0]['unitPrice']['amount'] = 9;
        $this->assertNotSame(CheckoutPricingFingerprint::hash($before), CheckoutPricingFingerprint::hash($after));
    }
}
