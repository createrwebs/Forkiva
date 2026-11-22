<?php

namespace Modules\Invoice\Services\ThaiTax;

use DOMDocument;

/**
 * Generates ETDA-structured Thai e-Tax Invoice XML (TaxInvoice_CrossIndustryInvoice)
 * ready for digital signing / Revenue Department submission.
 */
class ETaxXmlBuilder
{
    /**
     * @param  array<string, mixed>  $invoice
     */
    public function build(array $invoice): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('TaxInvoice_CrossIndustryInvoice');
        $dom->appendChild($root);

        $doc = $dom->createElement('ExchangedDocument');
        $doc->appendChild($dom->createElement('ID', htmlspecialchars((string) $invoice['invoice_number'])));
        $doc->appendChild($dom->createElement('TypeCode', '388'));
        $doc->appendChild($dom->createElement('IssueDateTime', htmlspecialchars((string) $invoice['issued_at'])));
        $root->appendChild($doc);

        $tx = $dom->createElement('SupplyChainTradeTransaction');
        $root->appendChild($tx);

        $agreement = $dom->createElement('ApplicableHeaderTradeAgreement');
        $agreement->appendChild($this->buildTradeParty($dom, 'SellerTradeParty', (array) $invoice['seller']));
        $agreement->appendChild($this->buildTradeParty($dom, 'BuyerTradeParty', (array) $invoice['buyer']));
        $tx->appendChild($agreement);

        $settlement = $dom->createElement('ApplicableHeaderTradeSettlement');
        $settlement->appendChild($dom->createElement('InvoiceCurrencyCode', (string) ($invoice['currency'] ?? 'THB')));

        $summation = $dom->createElement('SpecifiedTradeSettlementHeaderMonetarySummation');
        $summation->appendChild($dom->createElement('LineTotalAmount', (string) $invoice['subtotal']));
        $summation->appendChild($dom->createElement('TaxTotalAmount', (string) $invoice['tax_total']));
        $summation->appendChild($dom->createElement('GrandTotalAmount', (string) $invoice['total']));
        $settlement->appendChild($summation);
        $tx->appendChild($settlement);

        return (string) $dom->saveXML();
    }

    /**
     * @param  array<string, mixed>  $party
     */
    private function buildTradeParty(DOMDocument $dom, string $nodeName, array $party): \DOMElement
    {
        $element = $dom->createElement($nodeName);
        $element->appendChild($dom->createElement('Name', htmlspecialchars((string) ($party['name'] ?? ''))));

        $taxId = (string) ($party['vat_tin'] ?? '').str_pad((string) ($party['branch_code'] ?? '00000'), 5, '0', STR_PAD_LEFT);
        $taxReg = $dom->createElement('SpecifiedTaxRegistration');
        $taxReg->appendChild($dom->createElement('ID', htmlspecialchars($taxId)));
        $element->appendChild($taxReg);

        return $element;
    }
}
