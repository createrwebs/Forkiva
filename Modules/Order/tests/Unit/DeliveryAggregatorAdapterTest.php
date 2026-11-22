<?php

namespace Modules\Order\Tests\Unit;

use Modules\Order\Services\DeliveryAggregator\GrabFoodAdapter;
use Modules\Order\Services\DeliveryAggregator\LineManAdapter;
use PHPUnit\Framework\TestCase;

class DeliveryAggregatorAdapterTest extends TestCase
{
    public function test_grabfood_adapter_verifies_signature_and_normalizes_order(): void
    {
        $adapter = new GrabFoodAdapter;
        $secret = 'grab-webhook-secret';
        $payload = [
            'orderID' => 'GF-20261122-001',
            'currency' => 'THB',
            'price' => ['subtotal' => 45000, 'deliveryFee' => 0],
            'items' => [
                ['id' => 'SKU-01', 'name' => 'Pad Thai', 'quantity' => 2, 'price' => 22500],
            ],
        ];
        $raw = (string) json_encode($payload);
        $sig = hash_hmac('sha256', $raw, $secret);

        $this->assertTrue($adapter->verifyWebhookSignature($raw, $sig, $secret));
        $this->assertFalse($adapter->verifyWebhookSignature($raw, 'bad-sig', $secret));

        $normalized = $adapter->normalizeOrder($payload);
        $this->assertSame('grabfood', $normalized['channel']);
        $this->assertSame('GF-20261122-001', $normalized['external_order_id']);
        $this->assertSame('450.00', $normalized['gross_total']);
        $this->assertSame('225.00', $normalized['items'][0]['unit_price']);
    }

    public function test_lineman_adapter_normalizes_order_payload(): void
    {
        $adapter = new LineManAdapter;
        $normalized = $adapter->normalizeOrder([
            'order_no' => 'LM-998877',
            'total_amount' => '320.00',
            'order_items' => [
                ['sku' => 'TEA-01', 'title' => 'Thai Tea', 'qty' => 4, 'unit_price' => '80.00'],
            ],
        ]);

        $this->assertSame('lineman', $normalized['channel']);
        $this->assertSame('LM-998877', $normalized['external_order_id']);
        $this->assertSame('320.00', $normalized['gross_total']);
        $this->assertSame(4, $normalized['items'][0]['quantity']);
    }
}
