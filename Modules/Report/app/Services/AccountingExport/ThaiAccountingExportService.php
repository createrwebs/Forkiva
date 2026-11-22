<?php

namespace Modules\Report\Services\AccountingExport;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Generates UTF-8 BOM CSV exports for major Thai SME accounting platforms
 * (PEAK, FlowAccount, Express) with strict double-entry balance checks.
 */
class ThaiAccountingExportService
{
    private const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function export(string $format, array $rows): string
    {
        return match ($format) {
            'peak' => $this->buildCsv(
                ['DocumentDate', 'DocumentNo', 'ContactName', 'TaxID', 'SubTotal', 'VAT', 'GrandTotal', 'AccountCode'],
                array_map(fn (array $r): array => [
                    $r['issued_at'],
                    $r['invoice_number'],
                    $r['buyer_name'] ?? '',
                    $r['buyer_vat_tin'] ?? '',
                    $r['subtotal'],
                    $r['tax_total'],
                    $r['total'],
                    $r['payment_account'] ?? '1111-01',
                ], $rows)
            ),
            'flowaccount' => $this->buildCsv(
                ['Date', 'DocNo', 'CustomerName', 'TaxNumber', 'NetAmount', 'VatAmount', 'TotalAmount'],
                array_map(fn (array $r): array => [
                    $r['issued_at'],
                    $r['invoice_number'],
                    $r['buyer_name'] ?? '',
                    $r['buyer_vat_tin'] ?? '',
                    $r['subtotal'],
                    $r['tax_total'],
                    $r['total'],
                ], $rows)
            ),
            'express' => $this->buildCsv(
                ['DOCDAT', 'DOCNUM', 'CUSNAM', 'TAXID', 'AMTNET', 'VATAMT', 'AMTTOT', 'ACCNUM'],
                array_map(fn (array $r): array => [
                    $r['issued_at'],
                    $r['invoice_number'],
                    $r['buyer_name'] ?? '',
                    $r['buyer_vat_tin'] ?? '',
                    $r['subtotal'],
                    $r['tax_total'],
                    $r['total'],
                    $r['payment_account'] ?? '1111-01',
                ], $rows)
            ),
            default => throw new InvalidArgumentException("Unsupported Thai accounting export format [{$format}]."),
        };
    }

    /**
     * Build double-entry journal lines and verify Debit === Credit using BigDecimal.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{entries: list<array<string, string>>, total_debit: string, total_credit: string, is_balanced: bool}
     */
    public function buildBalancedJournalEntries(array $rows): array
    {
        $entries = [];
        $totalDebit = BigDecimal::zero()->toScale(2);
        $totalCredit = BigDecimal::zero()->toScale(2);

        foreach ($rows as $row) {
            $subtotal = BigDecimal::of((string) $row['subtotal'])->toScale(2, RoundingMode::HALF_UP);
            $tax = BigDecimal::of((string) $row['tax_total'])->toScale(2, RoundingMode::HALF_UP);
            $total = BigDecimal::of((string) $row['total'])->toScale(2, RoundingMode::HALF_UP);

            $entries[] = ['account' => (string) ($row['payment_account'] ?? '1111-01'), 'debit' => (string) $total, 'credit' => '0.00'];
            $entries[] = ['account' => '4100-01', 'debit' => '0.00', 'credit' => (string) $subtotal];

            if ($tax->isGreaterThan(0)) {
                $entries[] = ['account' => '2150-01', 'debit' => '0.00', 'credit' => (string) $tax];
            }

            $totalDebit = $totalDebit->plus($total);
            $totalCredit = $totalCredit->plus($subtotal)->plus($tax);
        }

        return [
            'entries' => $entries,
            'total_debit' => (string) $totalDebit->toScale(2, RoundingMode::HALF_UP),
            'total_credit' => (string) $totalCredit->toScale(2, RoundingMode::HALF_UP),
            'is_balanced' => $totalDebit->isEqualTo($totalCredit),
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $records
     */
    private function buildCsv(array $headers, array $records): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($records as $record) {
            fputcsv($stream, $record);
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return self::UTF8_BOM.$csv;
    }
}
