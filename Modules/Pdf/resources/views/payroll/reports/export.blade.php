@php
    use Modules\Pdf\Enums\PdfDriver;

    $locale = app()->getLocale();
    $rtl = isRtl($locale);
    $logo = getLogoBase64();
    $hasLogo = $logo && ! str_ends_with($logo, 'base64,');
    $regularFont = base64_encode(file_get_contents(storage_path('fonts/Cairo-Regular.ttf')));
    $boldFont = base64_encode(file_get_contents(storage_path('fonts/Cairo-Bold.ttf')));
    // Match Cairo's physical line spacing in browser engines and Dompdf.
    $lineHeight = static fn (float $points): string => (($pdfDriver ?? null) === PdfDriver::Dompdf ? $points / 2.0614 : $points).'pt';
    $wide = count($headers) > 6;
    $textKeys = ['employee', 'period', 'status', 'run_business_status', 'code', 'name', 'policy_name', 'department_name', 'tax_base_effect', 'type'];
    $columns = collect($headers)->map(static function (array $header) use ($textKeys): array {
        $key = $header['key'];
        return $header + [
            'numeric' => ! in_array($key, $textKeys, true),
            'weight' => match ($key) {
                'employee', 'name', 'policy_name', 'department_name' => 2,
                'period', 'reference_id', 'tax_base_effect' => 1.4,
                'run_id', 'employees_count', 'employee_count', 'headcount' => 0.7,
                default => 1,
            },
            'emphasis' => in_array($key, ['net_amount', 'sum_net', 'ytd_net', 'total_amount'], true),
        ];
    });
    $totalWeight = max(1, $columns->sum('weight'));
    // Explicit column order works consistently in Dompdf and browser engines.
    if ($rtl) {
        $columns = $columns->reverse();
    }
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @font-face { font-family: 'Cairo'; font-style: normal; font-weight: 400; src: url('data:font/truetype;base64,{{ $regularFont }}') format('truetype'); }
        @font-face { font-family: 'Cairo'; font-style: normal; font-weight: 700; src: url('data:font/truetype;base64,{{ $boldFont }}') format('truetype'); }
        body { margin: 0; padding: 0; font-family: 'Cairo', 'DejaVu Sans', sans-serif; font-size: {{ $wide ? 7.5 : 8.5 }}pt; line-height: {{ $lineHeight($wide ? 11 : 13) }}; color: #303740; background: #fff; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; direction: ltr; }
        th, td { padding: 0; vertical-align: top; text-align: {{ $rtl ? 'right' : 'left' }}; overflow-wrap: break-word; word-wrap: break-word; }
        .text { direction: {{ $rtl ? 'rtl' : 'ltr' }}; }
        .number { direction: ltr; unicode-bidi: embed; }
        .masthead { border-top: 4pt solid #f57c00; border-bottom: 1pt solid #dfe3e7; margin-bottom: 14pt; page-break-inside: avoid; }
        .masthead td { padding: 11pt 0; vertical-align: middle; }
        .masthead .logo-cell { width: {{ $wide ? 5.5 : 8 }}%; text-align: left; }
        .logo { width: 30pt; height: auto; max-height: 36pt; }
        .masthead .brand-cell { width: {{ $hasLogo ? ($wide ? 54.5 : 52) : 60 }}%; text-align: left; }
        .brand { font-size: 15pt; line-height: {{ $lineHeight(20) }}; font-weight: 700; color: #252b33; }
        .document-title { width: 40%; text-align: right; font-size: 18pt; line-height: {{ $lineHeight(24) }}; font-weight: 700; color: #252b33; }
        .report-title { margin: 0 0 10pt; font-size: 13pt; line-height: {{ $lineHeight(18) }}; font-weight: 700; color: #252b33; page-break-after: avoid; }
        .metadata { margin-bottom: 14pt; background: #f7f8fa; border: 1pt solid #dfe3e7; page-break-inside: avoid; page-break-after: avoid; }
        .metadata td { padding: 7pt 9pt; width: 50%; }
        .label { color: #626b75; font-size: 7pt; line-height: {{ $lineHeight(10) }}; }
        .value { margin-top: 3pt; font-size: 8pt; line-height: {{ $lineHeight(12) }}; font-weight: 700; }
        .data thead { display: table-header-group; }
        .data tr { page-break-inside: avoid; }
        .data th { padding: 7pt 5pt; font-size: 7.5pt; line-height: {{ $lineHeight(11) }}; color: #303740; background: #f0f2f4; border-top: 1pt solid #d0d6dc; border-bottom: 1pt solid #d0d6dc; font-weight: 700; }
        .data td { padding: 7pt 5pt; border-bottom: 0.6pt solid #e6e9ec; }
        .data .alternate td { background: #fafbfc; }
        .data .numeric { text-align: right; }
        .data .emphasis { font-weight: 700; color: #252b33; }
        .detail { margin-top: 2pt; color: #626b75; font-size: 7pt; line-height: {{ $lineHeight(10) }}; }
        .empty { padding: 24pt 10pt !important; text-align: center !important; color: #626b75; background: #fafbfc; border: 1pt solid #e6e9ec; }
    </style>
</head>
<body>
<table class="masthead" dir="ltr">
    <tr>
        @if($hasLogo)
            <td class="logo-cell"><img class="logo" src="{{ $logo }}" alt=""></td>
        @endif
        <td class="brand-cell"><div class="brand text">{{ setting('app_name') ?: config('app.name') }}</div></td>
        <td class="document-title"><div class="text">{{ __('hr::payroll_reports.payroll_report') }}</div></td>
    </tr>
</table>
<h1 class="report-title text">{{ $title }}</h1>
<table class="metadata">
    <tr>
        @foreach($rtl ? ['generated', 'records'] : ['records', 'generated'] as $field)
            <td>
                <div class="label text">{{ $field === 'records' ? __('hr::payroll_reports.export_records') : __('admin::admin.table.generated_at') }}</div>
                <div class="value number">{{ $field === 'records' ? count($rows) : dateTimeFormat($generatedAt) }}</div>
            </td>
        @endforeach
    </tr>
</table>
<table class="data">
    <colgroup>
        @foreach($columns as $column)
            <col style="width: {{ $column['weight'] / $totalWeight * 100 }}%">
        @endforeach
    </colgroup>
    @if($columns->isNotEmpty())
        <thead>
        <tr>
            @foreach($columns as $column)
                <th style="width: {{ $column['weight'] / $totalWeight * 100 }}%" class="{{ $column['numeric'] ? 'numeric' : '' }}"><div class="text">{{ $column['title'] }}</div></th>
            @endforeach
        </tr>
        </thead>
    @endif
    <tbody>
    @forelse($columns->isEmpty() ? [] : $rows as $row)
        <tr class="{{ $loop->even ? 'alternate' : '' }}">
            @foreach($columns as $column)
                @php($cell = $row[$column['key']] ?? ['value' => '', 'detail' => ''])
                <td class="{{ $column['numeric'] ? 'numeric' : '' }} {{ $column['emphasis'] ? 'emphasis' : '' }}">
                    <div class="{{ $column['numeric'] ? 'number' : 'text' }}">{{ $cell['value'] === '' ? '-' : $cell['value'] }}</div>
                    @if($cell['detail'] !== '')
                        <div class="detail number">{{ $cell['detail'] }}</div>
                    @endif
                </td>
            @endforeach
        </tr>
    @empty
        <tr><td class="empty text" colspan="{{ max(1, $columns->count()) }}">{{ __('admin::admin.table.no_data_available') }}</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
