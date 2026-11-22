<?php

namespace Modules\Report\Tests\Unit;

use Modules\Report\Services\AccountingExport\ThaiAccountingExportService;
use PHPUnit\Framework\TestCase;

class ThaiAccountingExportServiceTest extends TestCase
{
    public function test_exports_peak_flowaccount_and_express_csv_with_utf8_bom_and_balanced_entries(): void
    {
        $service = new ThaiAccountingExportService;
        $rows = [
            [
                'issued_at' => '2026-11-22',
                'invoice_number' => 'INV-202611-0001',
                'buyer_name' => 'บริษัท กขค จำกัด',
                'buyer_vat_tin' => '0105560012345',
                'subtotal' => '1000.00',
                'tax_total' => '70.00',
                'total' => '1070.00',
                'payment_account' => '1111-01',
            ],
        ];

        $peakCsv = $service->export('peak', $rows);
        $flowCsv = $service->export('flowaccount', $rows);
        $expressCsv = $service->export('express', $rows);

        foreach ([$peakCsv, $flowCsv, $expressCsv] as $csv) {
            $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
            $this->assertStringContainsString('INV-202611-0001', $csv);
            $this->assertStringContainsString('1070.00', $csv);
        }

        $journal = $service->buildBalancedJournalEntries($rows);
        $this->assertTrue($journal['is_balanced']);
        $this->assertSame('1070.00', $journal['total_debit']);
        $this->assertSame('1070.00', $journal['total_credit']);
    }
}
