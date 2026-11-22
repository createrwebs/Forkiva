@php
    use Modules\Pdf\Enums\PdfDriver;
    use Modules\Support\RTLDetector;

    // Receipts use the configured printer language, including their translated labels.
    $documentLocale = (string) setting('default_locale', config('app.locale', 'en'));
    $documentDirection = RTLDetector::detect($documentLocale) ? 'rtl' : 'ltr';
    $rtl = $documentDirection === 'rtl';
    $paperWidthMm = (int) ($profile['paper_width_mm'] ?? 80);
    $isNarrow = $paperWidthMm <= 58;
    $printableWidthMm = (float) ($profile['printable_width_mm'] ?? ($isNarrow ? 48 : 72));
    $regularFont = base64_encode(file_get_contents(config('printer.fonts.cairo.regular')));
    $boldFont = base64_encode(file_get_contents(config('printer.fonts.cairo.bold')));
    // Sarabun covers Thai glyphs that Cairo and DejaVu Sans lack.
    $thaiRegularFont = base64_encode(file_get_contents(config('printer.fonts.sarabun.regular')));
    $thaiBoldFont = base64_encode(file_get_contents(config('printer.fonts.sarabun.bold')));
    // Cairo has different line metrics in Dompdf and browser engines.
    $lineHeight = static fn (float $points): string => (($pdfDriver ?? null) === PdfDriver::Dompdf ? $points / 2.0614 : $points).'pt';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $documentLocale) }}" dir="{{ $documentDirection }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        @font-face { font-family: 'Receipt Cairo'; font-style: normal; font-weight: 400; src: url('data:font/truetype;base64,{{ $regularFont }}') format('truetype'); }
        @font-face { font-family: 'Receipt Cairo'; font-style: normal; font-weight: 700; src: url('data:font/truetype;base64,{{ $boldFont }}') format('truetype'); }
        @font-face { font-family: 'Receipt Sarabun'; font-style: normal; font-weight: 400; src: url('data:font/truetype;base64,{{ $thaiRegularFont }}') format('truetype'); }
        @font-face { font-family: 'Receipt Sarabun'; font-style: normal; font-weight: 700; src: url('data:font/truetype;base64,{{ $thaiBoldFont }}') format('truetype'); }
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; background: #fff; color: #000; }
        body { font-family: 'Receipt Cairo', 'Receipt Sarabun', 'DejaVu Sans', sans-serif; font-size: {{ $isNarrow ? 8 : 9 }}pt; line-height: {{ $lineHeight($isNarrow ? 11 : 12) }}; font-weight: 400; text-align: {{ $rtl ? 'right' : 'left' }}; }
        .receipt { width: {{ $printableWidthMm - 4 }}mm; padding: 2mm; margin: 0 {{ max(0, ($paperWidthMm - $printableWidthMm) / 2) }}mm; background: #fff; }
        @if($rasterOutput ?? false)
        .receipt { box-sizing: border-box; width: {{ floor($printableWidthMm * 96 / 25.4) }}px; margin: 0; }
        @endif
        header, main, footer, section, article { display: block; }
        div, p, span, td { overflow-wrap: break-word; word-wrap: break-word; }
        .section, .ticket-head { margin: 0 0 3mm; }
        .receipt-header { margin-bottom: 3mm; }
        .receipt-footer { margin-top: 3mm; }
        .section-title { margin: 0 0 1.5mm; padding: 0 0 1mm; border-bottom: 0.7pt solid #000; font-size: 9pt; line-height: {{ $lineHeight(12) }}; font-weight: 700; page-break-after: avoid; }
        .secondary, .item-caption, .item-option, .payment-detail, .compact-item-meta, .invoice-party-line { font-size: 7.5pt; line-height: {{ $lineHeight(10.5) }}; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .mono { direction: ltr; unicode-bidi: embed; }
        .number { direction: ltr; unicode-bidi: embed; white-space: nowrap; }
        .item-caption .number, .compact-item-meta .number, .secondary .number { margin-left: 0.5mm; margin-right: 0.5mm; }
        .stack > div, .stack-tight > div { margin-bottom: 1mm; }
        .logo { display: block; width: 13mm; height: auto; max-height: 16mm; margin: 0 auto 2mm; }
        .brand-name, .bill-brand, .invoice-brand { margin: 1mm 0; text-align: center; font-size: 11pt; line-height: {{ $lineHeight(14) }}; font-weight: 700; }
        .bill-brand-subtitle, .invoice-brand-subtitle { margin-bottom: 0.7mm; text-align: center; font-size: 7.5pt; line-height: {{ $lineHeight(10.5) }}; }
        .bill-ticket-title, .waiter-ticket-title, .kitchen-ticket-title, .invoice-ticket-title { text-align: center; font-size: 12pt; line-height: {{ $lineHeight(16) }}; font-weight: 700; }
        .document-number, .bill-order-number, .waiter-order-number, .kitchen-order-number, .invoice-order-number { margin: 2mm 0; padding: 1.5mm 0; border-top: 1pt solid #000; border-bottom: 1pt solid #000; text-align: center; font-size: 14pt; line-height: {{ $lineHeight(18) }}; font-weight: 700; direction: ltr; unicode-bidi: embed; }
        .bill-order-date, .waiter-order-date, .kitchen-order-date, .invoice-order-date { margin: 0 0 2mm; text-align: center; font-size: 7.5pt; line-height: {{ $lineHeight(10.5) }}; }
        .bill-order-date-label, .waiter-order-date-label, .kitchen-order-date-label, .invoice-order-date-label { display: block; }
        .bill-order-date-value, .waiter-order-date-value, .kitchen-order-date-value, .invoice-order-date-value { display: block; direction: ltr; unicode-bidi: embed; }
        .chip-row { text-align: center; margin-bottom: 1mm; }
        .chip { display: inline-block; padding: 1mm 2mm; margin: 0 0.5mm 1mm; border: 0.7pt solid #000; font-weight: 700; }
        .bill-details-row, .waiter-details-row, .kitchen-details-row, .invoice-details-row, .meta-row { display: table; width: 100%; table-layout: fixed; direction: ltr; margin: 0 0 1mm; }
        .bill-details-label, .waiter-details-label, .kitchen-details-label, .invoice-details-label, .meta-label { display: table-cell; width: 35%; vertical-align: top; font-size: 7.5pt; line-height: {{ $lineHeight(10.5) }}; font-weight: 700; direction: {{ $documentDirection }}; }
        .bill-details-value, .waiter-details-value, .kitchen-details-value, .invoice-details-value, .meta-value { display: table-cell; width: 65%; vertical-align: top; direction: {{ $documentDirection }}; text-align: right; }
        .items-head, .item-grid, .compact-item-top, .compact-item-meta, .summary-row, .payment-row, .kitchen-item { display: table; width: 100%; table-layout: fixed; border-collapse: collapse; direction: ltr; }
        .items-head > div, .item-grid > div, .compact-item-top > div, .compact-item-meta > div, .summary-row > div, .payment-row > div, .kitchen-item > div { display: table-cell; vertical-align: top; padding: 0; }
        .items-head { border-bottom: 0.7pt solid #000; margin-bottom: 1mm; font-size: 7.5pt; line-height: {{ $lineHeight(10.5) }}; font-weight: 700; page-break-after: avoid; }
        .items-head > div { padding-bottom: 1mm; }
        .items-head > div:first-child, .item-grid > .item-main { width: 55%; }
        .items-head > div:nth-child(2), .item-grid > .item-qty { width: 13%; }
        .items-head > div:last-child, .item-grid > .item-amount { width: 32%; }
        .item { padding: 1.5mm 0; border-bottom: 0.5pt dashed #000; page-break-inside: avoid; }
        .item-main { direction: {{ $documentDirection }}; text-align: {{ $rtl ? 'right' : 'left' }}; }
        .item-name { font-weight: 700; }
        .item-caption, .item-options, .compact-item-meta { margin-top: 1mm; }
        .item-option { margin-bottom: 0.7mm; }
        .item-qty, .item-amount, .summary-value, .payment-amount { direction: ltr; unicode-bidi: embed; text-align: right; }
        .item-amount, .summary-value, .payment-amount { font-weight: 700; }
        .compact-item-top > .item-main { width: 62%; }
        .compact-item-top > .item-amount { width: 38%; }
        .compact-item-meta > div { width: 50%; direction: ltr; unicode-bidi: embed; }
        .compact-item-meta > div:last-child:not(:first-child) { text-align: right; }
        .summary-box { border-top: 0.7pt solid #000; border-bottom: 0.7pt solid #000; padding: 1mm 0; }
        .summary-row { page-break-inside: avoid; }
        .summary-row > div { padding: 0.7mm 0; }
        .summary-label { width: 62%; direction: {{ $documentDirection }}; text-align: {{ $rtl ? 'right' : 'left' }}; }
        .summary-value { width: 38%; }
        .summary-row > .summary-label { padding-right: 1.5mm; }
        .summary-row.total { border-top: 1pt solid #000; font-size: {{ $isNarrow ? 10 : 12 }}pt; line-height: {{ $lineHeight($isNarrow ? 14 : 16) }}; font-weight: 700; }
        .summary-row.total > div { padding: 1.5mm 0; }
        @if($isNarrow && $rtl)
        .summary-row.total, .summary-row.total > div { display: block; width: auto; text-align: right; }
        .summary-row.total > .summary-value { padding-top: 0; font-size: 12pt; line-height: {{ $lineHeight(16) }}; }
        @endif
        .summary-row.balance { font-weight: 700; }
        .payment-row { margin-bottom: 2mm; page-break-inside: avoid; }
        .payment-row > div:first-child { width: 62%; direction: {{ $documentDirection }}; }
        .payment-row > .payment-amount { width: 38%; }
        .payment-method { font-weight: 700; }
        .payment-detail { margin-top: 0.5mm; }
        .kitchen-item { margin: 0 0 2mm; border-bottom: 0.5pt dashed #000; page-break-inside: avoid; }
        .kitchen-item > div { padding: 1.5mm 0; }
        .kitchen-item > .kitchen-qty { width: {{ $isNarrow ? 28 : 20 }}%; font-size: 12pt; line-height: {{ $lineHeight(16) }}; font-weight: 700; direction: ltr; text-align: left; }
        .kitchen-item > .item-main { width: {{ $isNarrow ? 72 : 80 }}%; }
        .kitchen-name { font-size: 10pt; line-height: {{ $lineHeight(14) }}; font-weight: 700; }
        .kitchen-modifiers { margin-top: 1mm; }
        .kitchen-modifier { margin: 0.5mm 0; }
        .note-box, .kitchen-notes-panel { border: 0.7pt solid #000; padding: 1.5mm; font-weight: 700; white-space: pre-line; }
        .invoice-party-card { margin-bottom: 2mm; padding-bottom: 2mm; border-bottom: 0.5pt dashed #000; }
        .invoice-party-title { font-size: 7.5pt; line-height: {{ $lineHeight(10.5) }}; }
        .invoice-party-name { font-weight: 700; }
        .qrcode { display: block; width: 28mm; height: 28mm; margin: 2mm auto; }
    </style>
</head>
<body>
<div class="receipt {{ $isNarrow ? 'receipt--narrow' : 'receipt--wide' }}" @if(($pdfDriver ?? null) === PdfDriver::Dompdf) data-dompdf-wrap-width-mm="{{ $paperWidthMm }}" @endif data-paper-width-mm="{{ $paperWidthMm }}" data-document-locale="{{ $documentLocale }}" data-document-direction="{{ $documentDirection }}">
    <header class="receipt-header">@yield('header')</header>
    <main class="receipt-main">@yield('content')</main>
    <footer class="receipt-footer">@yield('footer')</footer>
</div>
</body>
</html>
