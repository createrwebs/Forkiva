<?php

namespace Modules\Product\Tests\Unit;

use Modules\Inventory\Services\Retail\RetailStockService;
use Modules\Product\Enums\ProductInventoryConsumptionMode;
use Modules\Product\Services\Retail\RetailBarcodeService;
use PHPUnit\Framework\TestCase;

class RetailBarcodeAndPricingTest extends TestCase
{
    public function test_direct_product_consumption_mode_exists(): void
    {
        $this->assertSame('direct_product', ProductInventoryConsumptionMode::DirectProduct->value);
    }

    public function test_decodes_thai_ean13_weighted_barcode(): void
    {
        $service = new RetailBarcodeService;
        // Prefix 21 (price-embedded), SKU 00123, value 04550 (45.50 THB), valid EAN-13 check digit
        $barcode = $service->buildWeightedEan13('21', '00123', '45.50');
        $decoded = $service->decodeWeightedEan13($barcode);

        $this->assertNotNull($decoded);
        $this->assertSame('21', $decoded['prefix']);
        $this->assertSame('00123', $decoded['sku_code']);
        $this->assertSame('45.50', $decoded['embedded_value']);
    }

    public function test_converts_multi_uom_and_resolves_wholesale_tier_price(): void
    {
        $stockService = new RetailStockService;

        $this->assertSame('24.0000', $stockService->convertUomToBaseQuantity('2', '12'));

        $tiers = [
            ['min_quantity' => 1, 'unit_price' => '50.00'],
            ['min_quantity' => 6, 'unit_price' => '45.00'],
            ['min_quantity' => 12, 'unit_price' => '40.00'],
        ];

        $this->assertSame('50.00', $stockService->resolveWholesaleTierPrice($tiers, 3, '50.00'));
        $this->assertSame('45.00', $stockService->resolveWholesaleTierPrice($tiers, 6, '50.00'));
        $this->assertSame('40.00', $stockService->resolveWholesaleTierPrice($tiers, 20, '50.00'));
    }
}
