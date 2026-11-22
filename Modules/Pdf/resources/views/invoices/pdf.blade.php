@php
    use Modules\Invoice\Services\InvoicePDF\InvoicePdfBranding;
    use Modules\Pdf\Enums\PdfDriver;
    $isRtl = app()->getLocale() === 'ar';
    $brandName = setting('app_name') ?: $invoice->seller->legal_name;
    $brandLayout = app(InvoicePdfBranding::class)->layout($brandName);
    $cairoRegular = base64_encode(file_get_contents(storage_path('fonts/Cairo-Regular.ttf')));
    $cairoBold = base64_encode(file_get_contents(storage_path('fonts/Cairo-Bold.ttf')));
    // Sarabun covers Thai glyphs that Cairo and DejaVu Sans lack.
    $sarabunRegular = base64_encode(file_get_contents(storage_path('fonts/Sarabun-Regular.ttf')));
    $sarabunBold = base64_encode(file_get_contents(storage_path('fonts/Sarabun-Bold.ttf')));

    // DOMPDF multiplies line-height by Cairo's 1.874em ascent/descent and its 1.1 font-height ratio.
    $lineHeight = static fn (float $points): string => (
        ($pdfDriver ?? null) === PdfDriver::Dompdf ? $points / 2.0614 : $points
    ).'pt';
@endphp
    <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @font-face {
            font-family: 'Cairo';
            font-style: normal;
            font-weight: 400;
            src: url('data:font/truetype;base64,{{ $cairoRegular }}') format('truetype');
        }

        @font-face {
            font-family: 'Cairo';
            font-style: normal;
            font-weight: 700;
            src: url('data:font/truetype;base64,{{ $cairoBold }}') format('truetype');
        }

        @font-face {
            font-family: 'Sarabun';
            font-style: normal;
            font-weight: 400;
            src: url('data:font/truetype;base64,{{ $sarabunRegular }}') format('truetype');
        }

        @font-face {
            font-family: 'Sarabun';
            font-style: normal;
            font-weight: 700;
            src: url('data:font/truetype;base64,{{ $sarabunBold }}') format('truetype');
        }

        /* Keep the same physical line spacing in browser engines and DOMPDF. */
        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #243247;
            font-family: 'Cairo', 'Sarabun', 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            line-height: {{ $lineHeight(14) }};
            direction: ltr;
            text-align: left;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            direction: ltr;
        }

        td, th {
            padding: 0;
            vertical-align: top;
        }

        strong {
            font-weight: 700;
        }

        .invoice-document {
            display: block;
        }

        .document-header, .document-meta, .parties-layout, .settlement-layout {
            table-layout: fixed;
        }

        .document-header td {
            vertical-align: middle;
        }

        .brand-logo-cell {
            width: 9.5%;
        }

        .brand-copy {
            width: 52.5%;
            padding-right: 16pt;
        }

        .document-column {
            width: 38%;
            text-align: right;
        }

        .brand-logo {
            display: block;
            width: 44pt;
            height: auto;
        }

        .brand-name {
            color: #12334b;
            font-size: {{ $brandLayout['font_size'] }}pt;
            line-height: {{ $lineHeight($brandLayout['line_height']) }};
            font-weight: 700;
        }

        .brand-name-line {
            display: block;
            white-space: nowrap;
        }

        .document-title {
            margin: 0;
            color: #516174;
            font-size: 19pt;
            line-height: {{ $lineHeight(25) }};
            font-weight: 700;
        }

        .reference-number {
            display: block;
            margin-top: 4pt;
            color: #607084;
            font-size: 8pt;
            line-height: {{ $lineHeight(12) }};
        }

        .accent-rule {
            height: 2pt;
            margin: 14pt 0 0;
            background: #f57c00;
        }

        .document-meta {
            margin-bottom: 18pt;
            border-bottom: 1px solid #dce3e9;
        }

        .document-meta td {
            padding: 9pt 0 10pt;
        }

        .document-meta td + td {
            padding-left: 12pt;
        }

        .meta-label {
            display: block;
            color: #607084;
            font-size: 7.5pt;
            line-height: {{ $lineHeight(11) }};
            margin-bottom: 3pt;
        }

        .meta-value {
            color: #243247;
            font-size: 9pt;
            line-height: {{ $lineHeight(14) }};
            font-weight: 700;
        }

        .parties-layout {
            margin-bottom: 20pt;
        }

        .party-column {
            width: 38%;
        }

        .party-gap {
            width: 4%;
        }

        .qr-column {
            width: 16%;
            text-align: right;
        }

        .party-heading {
            color: #607084;
            font-size: 8pt;
            line-height: {{ $lineHeight(12) }};
            margin-bottom: 5pt;
            font-weight: 700;
        }

        .party-content {
            color: #516174;
            font-size: 8.5pt;
            line-height: {{ $lineHeight(13) }};
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .party-name {
            color: #243247;
            font-size: 10pt;
            line-height: {{ $lineHeight(15) }};
            font-weight: 700;
            margin-bottom: 3pt;
        }

        .party-text, .party-contact {
            margin: 0;
            line-height: {{ $lineHeight(13) }};
        }

        .qr-frame {
            display: inline-block;
            padding: 4pt;
            border: 1px solid #dce3e9;
            background: #ffffff;
        }

        .qr-frame img {
            display: block;
            width: 62pt;
            height: 62pt;
        }

        .items-table {
            table-layout: fixed;
            margin-bottom: 18pt;
            border-bottom: 1px solid #cbd5df;
        }

        .items-table thead {
            display: table-header-group;
        }

        .items-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .items-table th {
            padding: 9pt 5pt;
            background: #edf2f6;
            border-top: 1px solid #dce3e9;
            border-bottom: 1px solid #cbd5df;
            color: #243247;
            font-size: 7.5pt;
            line-height: {{ $lineHeight(11) }};
            font-weight: 700;
            text-align: left;
            vertical-align: middle;
        }

        .items-table td {
            padding: 9pt 5pt;
            border-bottom: 1px solid #e5eaf0;
            color: #243247;
            font-size: 8.5pt;
            line-height: {{ $lineHeight(13) }};
        }

        .items-table tbody tr:nth-child(even) td {
            background: #fafbfd;
        }

        .line-number {
            width: 4%;
            text-align: center !important;
            color: #607084;
        }

        .line-description {
            width: 30%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .line-unit-price {
            width: 14%;
        }

        .line-quantity {
            width: 8%;
        }

        .line-subtotal {
            width: 15%;
        }

        .line-tax {
            width: 13%;
        }

        .line-total {
            width: 16%;
        }

        .line-sku {
            display: block;
            margin-top: 2pt;
            color: #6b7b8e;
            font-size: 7pt;
            line-height: {{ $lineHeight(10) }};
        }

        .numeric {
            direction: ltr;
            text-align: right !important;
            white-space: nowrap;
        }

        .settlement-layout {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .payment-column {
            width: 44%;
        }

        .settlement-gap {
            width: 8%;
        }

        .totals-column {
            width: 48%;
        }

        .section-heading {
            margin: 0 0 5pt;
            padding-bottom: 7pt;
            border-bottom: 1px solid #cbd5df;
            color: #243247;
            font-size: 9pt;
            line-height: {{ $lineHeight(14) }};
            font-weight: 700;
        }

        .payment-table, .totals-table {
            table-layout: fixed;
        }

        .payment-table td, .totals-table td {
            padding: 5pt 0;
            font-size: 8.5pt;
            line-height: {{ $lineHeight(13) }};
        }

        .payment-table tr + tr td {
            border-top: 1px solid #e5eaf0;
        }

        .summary-label {
            width: 65%;
            color: #516174;
        }

        .summary-amount {
            width: 35%;
            color: #243247;
            font-weight: 700;
        }

        .transaction-id {
            display: block;
            margin-top: 2pt;
            color: #6b7b8e;
            font-size: 7pt;
            line-height: {{ $lineHeight(11) }};
            word-wrap: break-word;
        }

        .tax-note {
            display: block;
            color: #6b7b8e;
            font-size: 7pt;
            line-height: {{ $lineHeight(11) }};
            margin-top: 2pt;
        }

        .refund {
            color: #a53125 !important;
        }

        .discount {
            color: #a65210 !important;
        }

        .grand-total td {
            padding: 10pt 8pt;
            border-top: 2pt solid #f57c00;
            border-bottom: 1px solid #dce3e9;
            background: #f4f7fa;
            color: #142c43;
            font-size: 13pt;
            line-height: {{ $lineHeight(19) }};
            font-weight: 700;
        }

        .paid-amount td {
            padding-top: 9pt;
        }

        .net-paid td {
            border-top: 1px solid #cbd5df;
            padding-top: 7pt;
            color: #246348;
            font-weight: 700;
        }

        .document-footer {
            display: block;
            margin-top: 22pt;
            padding-top: 8pt;
            border-top: 1px solid #dce3e9;
            color: #6b7b8e;
            font-size: 7pt;
            line-height: {{ $lineHeight(11) }};
            page-break-inside: avoid;
        }

        .document-footer strong {
            color: #516174;
        }

        .document-footer strong, .document-footer .ltr {
            display: inline-block;
            vertical-align: middle;
        }

        .ltr {
            display: inline-block;
            direction: ltr;
            unicode-bidi: embed;
        }

        .document-meta td, .party-content, .party-heading, .section-heading, .summary-label, .line-description {
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
            text-align: {{ $isRtl ? 'right' : 'left' }};
        }
    </style>
</head>
<body>
<main class="invoice-document">
    <table class="document-header" role="presentation">
        <colgroup>
            <col style="width: 9.5%;">
            <col style="width: 52.5%;">
            <col style="width: 38%;">
        </colgroup>
        <tr>
            <td class="brand-logo-cell">
                <img class="brand-logo" alt="logo" src="{{ getLogoBase64() }}">
            </td>
            <td class="brand-copy">
                <div class="brand-name">
                    @foreach($brandLayout['lines'] as $brandLine)
                        <span class="brand-name-line">{{ $brandLine }}</span>
                    @endforeach
                </div>
            </td>
            <td class="document-column">
                <h1 class="document-title">
                    {{ $invoice->invoice_kind->isCreditNote()
                        ? __('invoice::invoices.show.credit_note')
                        : __('invoice::invoices.show.invoice') }}
                </h1>
                @if($invoice->referenceInvoice)
                    <span class="reference-number">
                        @lang('invoice::invoices.show.reference_invoice'):
                        <span dir="ltr">{{ $invoice->referenceInvoice->invoice_number }}</span>
                    </span>
                @endif
            </td>
        </tr>
    </table>

    <div class="accent-rule"></div>

    <table class="document-meta" role="presentation">
        <tr>
            <td>
                <span class="meta-label">@lang('invoice::invoices.show.invoice_number')</span>
                <span class="meta-value ltr" dir="ltr">{{ $invoice->invoice_number }}</span>
            </td>
            <td>
                <span class="meta-label">@lang('invoice::invoices.show.invoice_date')</span>
                <span class="meta-value ltr" dir="ltr">{{ dateTimeFormat($invoice->issued_at) }}</span>
            </td>
            <td>
                <span class="meta-label">@lang('invoice::invoices.show.branch')</span>
                <span class="meta-value">{{ $invoice->branch->name }}</span>
            </td>
        </tr>
    </table>

    <table class="parties-layout" role="presentation">
        <tr>
            <td class="party-column">
                <section class="party-block">
                    <div class="party-heading">@lang('invoice::invoices.show.seller')</div>
                    <div class="party-content">
                        @include('pdf::invoices.partials.party', ['party' => $invoice->seller])
                    </div>
                </section>
            </td>
            <td class="party-gap"></td>
            <td class="party-column">
                <section class="party-block">
                    <div class="party-heading">@lang('invoice::invoices.show.billing_address')</div>
                    <div class="party-content">
                        @include('pdf::invoices.partials.party', ['party' => $invoice->buyer])
                    </div>
                </section>
            </td>
            <td class="party-gap"></td>
            <td class="qr-column">
                <div class="qr-frame">
                    <img alt="QR code" src="data:image/svg+xml;base64,{{ base64_encode($invoice->getQrCodeSvg()) }}">
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <colgroup>
            <col style="width: 4%;">
            <col style="width: 30%;">
            <col style="width: 14%;">
            <col style="width: 8%;">
            <col style="width: 15%;">
            <col style="width: 13%;">
            <col style="width: 16%;">
        </colgroup>
        <thead>
        <tr>
            <th class="line-number">#</th>
            <th class="line-description">@lang('invoice::invoices.show.description')</th>
            <th class="line-unit-price numeric">@lang('invoice::invoices.show.unit_price')</th>
            <th class="line-quantity numeric">@lang('invoice::invoices.show.quantity')</th>
            <th class="line-subtotal numeric">@lang('invoice::invoices.show.sub_total')</th>
            <th class="line-tax numeric">@lang('invoice::invoices.show.total_tax')</th>
            <th class="line-total numeric">@lang('invoice::invoices.show.total')</th>
        </tr>
        </thead>
        <tbody>
        @foreach($invoice->lines as $index => $line)
            <tr>
                <td class="line-number">{{ $index + 1 }}</td>
                <td class="line-description">
                    <div>{{ $line->description }}</div>
                    @if($line->sku)
                        <span class="line-sku">{{ $line->sku }}</span>
                    @endif
                </td>
                <td class="line-unit-price numeric">{{ $line->unit_price->format() }}</td>
                <td class="line-quantity numeric">{{ rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.') }}</td>
                <td class="line-subtotal numeric">{{ $line->line_total_excl_tax->format() }}</td>
                <td class="line-tax numeric">{{ $line->tax_amount->format() }}</td>
                <td class="line-total numeric"><strong>{{ $line->line_total_incl_tax->format() }}</strong></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="settlement-layout" role="presentation">
        <tr>
            <td class="payment-column">
                @if($invoice->allocations->count() > 0)
                    <h2 class="section-heading">@lang('invoice::invoices.show.payment_summary')</h2>
                    <table class="payment-table">
                        @foreach($invoice->allocations as $allocation)
                            <tr>
                                <td class="summary-label">
                                    {{ $allocation->payment->method->trans() }}
                                    @if($allocation->payment->type->isRefund())
                                        <span class="refund">({{ $allocation->payment->type->trans() }})</span>
                                    @endif
                                    @if($allocation->payment->transaction_id)
                                        <span class="transaction-id">
                                            @lang('invoice::invoices.show.transaction_id'):
                                            <span dir="ltr">{{ $allocation->payment->transaction_id }}</span>
                                        </span>
                                    @endif
                                </td>
                                <td class="summary-amount numeric {{ $allocation->payment->type->isRefund() ? 'refund' : '' }}">
                                    {{ $allocation->amount->format() }}
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </td>
            <td class="settlement-gap"></td>
            <td class="totals-column">
                <table class="totals-table">
                    <tr>
                        <td class="summary-label">@lang('invoice::invoices.show.sub_total')</td>
                        <td class="summary-amount numeric">{{ $invoice->subtotal->format() }}</td>
                    </tr>
                    @foreach($invoice->discounts as $discount)
                        <tr>
                            <td class="summary-label">
                                @lang('invoice::invoices.show.discount')
                                @if($discount->name)
                                    <span>({{ $discount->name }})</span>
                                @endif
                            </td>
                            <td class="summary-amount numeric discount">{{ $discount->amount->format() }}</td>
                        </tr>
                    @endforeach
                    @foreach($taxSummary as $tax)
                        <tr>
                            <td class="summary-label">
                                {{ data_get($tax, 'name') }}
                                <span class="tax-note">
                                    {{ data_get($tax, 'scope') === 'product'
                                        ? __('tax::taxes.display.product_tax')
                                        : __('tax::taxes.display.global_tax') }}
                                    -
                                    {{ data_get($tax, 'type') === 'inclusive'
                                        ? __('tax::taxes.display.inclusive_tax')
                                        : __('tax::taxes.display.exclusive_tax') }}
                                    - {{ (float) data_get($tax, 'rate') }}%
                                </span>
                            </td>
                            <td class="summary-amount numeric">{{ data_get($tax, 'amount')->format() }}</td>
                        </tr>
                    @endforeach
                    <tr class="grand-total">
                        <td class="summary-label">@lang('invoice::invoices.show.total')</td>
                        <td class="summary-amount numeric">{{ $invoice->total->format() }}</td>
                    </tr>
                    @if(!$invoice->invoice_kind->isCreditNote())
                        <tr class="paid-amount">
                            <td class="summary-label">@lang('invoice::invoices.show.paid_amount')</td>
                            <td class="summary-amount numeric">{{ $invoice->paid_amount->format() }}</td>
                        </tr>
                        <tr>
                            <td class="summary-label">@lang('invoice::invoices.show.refunded_amount')</td>
                            <td class="summary-amount numeric">{{ $invoice->refunded_amount->format() }}</td>
                        </tr>
                        <tr class="net-paid">
                            <td class="summary-label">@lang('invoice::invoices.show.net_paid')</td>
                            <td class="summary-amount numeric">{{ $invoice->net_paid->format() }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <footer class="document-footer">
        <strong>@lang('invoice::invoices.show.document_id'):</strong>
        <span class="ltr" dir="ltr">{{ $invoice->uuid }}</span>
    </footer>
</main>
</body>
</html>
