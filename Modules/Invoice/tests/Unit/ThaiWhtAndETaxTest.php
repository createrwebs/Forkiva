<?php

namespace Modules\Invoice\Tests\Unit;

use Modules\Invoice\Services\ThaiTax\ETaxXmlBuilder;
use Modules\Invoice\Services\ThaiTax\WithholdingTaxService;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class ThaiWhtAndETaxTest extends TestCase
{
    public function test_calculates_thai_wht_and_classifies_pnd3_vs_pnd53(): void
    {
        $service = new WithholdingTaxService;

        $pnd53 = $service->calculate('10000.00', '3.00', isJuristicPerson: true);
        $this->assertSame('300.00', $pnd53['wht_amount']);
        $this->assertSame('9700.00', $pnd53['net_payable']);
        $this->assertSame('PND53', $pnd53['form_type']);

        $pnd3 = $service->calculate('5000.00', '5.00', isJuristicPerson: false);
        $this->assertSame('250.00', $pnd3['wht_amount']);
        $this->assertSame('4750.00', $pnd3['net_payable']);
        $this->assertSame('PND3', $pnd3['form_type']);
    }

    public function test_builds_valid_etda_thai_etax_invoice_xml(): void
    {
        $builder = new ETaxXmlBuilder;
        $xmlString = $builder->build([
            'invoice_number' => 'TAX-202611-0001',
            'issued_at' => '2026-11-22T12:00:00+07:00',
            'currency' => 'THB',
            'seller' => [
                'name' => 'Krua Thai Co., Ltd.',
                'vat_tin' => '0105560012345',
                'branch_code' => '00000',
            ],
            'buyer' => [
                'name' => 'Customer Co., Ltd.',
                'vat_tin' => '0105560099999',
                'branch_code' => '00001',
            ],
            'subtotal' => '1000.00',
            'tax_total' => '70.00',
            'total' => '1070.00',
        ]);

        $xml = new SimpleXMLElement($xmlString);
        $this->assertSame('TAX-202611-0001', (string) $xml->ExchangedDocument->ID);
        $this->assertSame('010556001234500000', (string) $xml->SupplyChainTradeTransaction->ApplicableHeaderTradeAgreement->SellerTradeParty->SpecifiedTaxRegistration->ID);
        $this->assertSame('1070.00', (string) $xml->SupplyChainTradeTransaction->ApplicableHeaderTradeSettlement->SpecifiedTradeSettlementHeaderMonetarySummation->GrandTotalAmount);
    }
}
