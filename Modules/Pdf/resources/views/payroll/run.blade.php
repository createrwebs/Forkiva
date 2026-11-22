@php
    use Modules\Pdf\Enums\PdfDriver;
    use Modules\Support\Enums\DateTimeFormat;
    use Modules\Support\Money;

    $locale = app()->getLocale();
    $rtl = isRtl($locale);
    $currency = $run->currency ?: ($run->payslips->first()?->currency ?: ($run->branch?->currency ?: setting('default_currency', 'JOD')));
    $logo = getLogoBase64();
    $hasLogo = $logo && ! str_ends_with($logo, 'base64,');
    $regularFont = base64_encode(file_get_contents(storage_path('fonts/Cairo-Regular.ttf')));
    $boldFont = base64_encode(file_get_contents(storage_path('fonts/Cairo-Bold.ttf')));
    // Match physical line spacing across Cairo's browser and Dompdf font metrics.
    $lineHeight = static fn (float $points): string => (($pdfDriver ?? null) === PdfDriver::Dompdf ? $points / 2.0614 : $points).'pt';
    $amountFields = ['gross_amount', 'deductions_amount', 'net_amount', 'employer_cost_amount'];
    $totals = collect($amountFields)->mapWithKeys(fn ($field) => [
        $field => new Money($run->payslips->sum(fn ($row) => $row->{$field}->amount()), $currency),
    ]);
    $columns = [
        'reference' => ['label' => __('hr::payroll_payslips.details.reference_id'), 'width' => 11],
        'employee' => ['label' => __('hr::payroll_payslips.table.employee'), 'width' => 21],
        'salary_type' => ['label' => __('hr::payroll_payslips.table.salary_type'), 'width' => 10],
        'status' => ['label' => __('hr::payroll_payslips.table.status'), 'width' => 10],
    ];
    foreach ($amountFields as $field) {
        $columns[$field] = ['label' => __('hr::payroll_payslips.table.'.$field), 'width' => 12];
    }
    $metadata = [
        ['label' => __('hr::payroll_runs.table.period'), 'value' => $run->period?->name ?: '-'],
        ['label' => __('hr::payroll_runs.table.branch'), 'value' => $run->branch?->name ?: '-'],
        ['label' => __('hr::payroll_runs.table.status'), 'value' => $run->status?->trans() ?? '-'],
        ['label' => __('hr::payroll_runs.table.business_status'), 'value' => $run->business_status?->trans() ?? '-'],
    ];
    $dates = [
        ['label' => __('hr::payroll_periods.table.date_from'), 'value' => dateTimeFormat($run->period?->date_from, DateTimeFormat::Date, '-')],
        ['label' => __('hr::payroll_periods.table.date_to'), 'value' => dateTimeFormat($run->period?->date_to, DateTimeFormat::Date, '-')],
        ['label' => __('admin::admin.table.created_at'), 'value' => dateTimeFormat($run->created_at, default: '-')],
    ];
    $summaryFields = ['count', ...$amountFields];
    // Explicit cell order also works in engines without RTL table layout support.
    if ($rtl) {
        $columns = array_reverse($columns, true);
        $metadata = array_reverse($metadata);
        $dates = array_reverse($dates);
        $summaryFields = array_reverse($summaryFields);
    }
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('hr::payroll_runs.payroll_run') }}</title>
    <style>
        @font-face {
            font-family: 'Cairo';
            font-style: normal;
            font-weight: 400;
            src: url('data:font/truetype;base64,{{ $regularFont }}') format('truetype');
        }

        @font-face {
            font-family: 'Cairo';
            font-style: normal;
            font-weight: 700;
            src: url('data:font/truetype;base64,{{ $boldFont }}') format('truetype');
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Cairo', 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            line-height: {{ $lineHeight(14) }};
            color: #303740;
            background: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            direction: ltr;
        }

        td, th {
            padding: 0;
            vertical-align: top;
            text-align: {{ $rtl ? 'right' : 'left' }};
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .text {
            direction: {{ $rtl ? 'rtl' : 'ltr' }};
        }

        .number {
            direction: ltr;
            unicode-bidi: embed;
        }

        .masthead {
            border-top: 4pt solid #f57c00;
            border-bottom: 1pt solid #dfe3e7;
            margin-bottom: 10pt;
            page-break-inside: avoid;
        }

        .masthead td { padding: 9pt 0; vertical-align: middle; }
        .masthead .logo-cell { width: 5.5%; text-align: left; }
        .masthead .brand-cell { width: {{ $hasLogo ? 64.5 : 70 }}%; text-align: left; }
        .logo { width: 34pt; height: auto; max-height: 40pt; }
        .brand { font-size: 16pt; line-height: {{ $lineHeight(22) }}; font-weight: 700; color: #252b33; }
        .document-title { width: 30%; text-align: right; font-size: 16pt; line-height: {{ $lineHeight(22) }}; font-weight: 700; color: #252b33; }
        .label { color: #626b75; font-size: 8pt; line-height: {{ $lineHeight(12) }}; }
        .value { font-weight: 700; margin-top: 2pt; }

        .metadata { margin-bottom: 10pt; page-break-inside: avoid; }
        .metadata td { width: 25%; padding: 5pt 9pt; border: 1pt solid #dfe3e7; }
        .dates { margin-bottom: 10pt; color: #626b75; font-size: 8pt; line-height: {{ $lineHeight(12) }}; }
        .dates td { width: 33.33%; }

        .summary { margin-bottom: 12pt; page-break-inside: avoid; }
        .summary td { width: 22%; padding: 6pt 8pt; background: #f7f8fa; border: 1pt solid #dfe3e7; }
        .summary .count { width: 12%; }
        .summary .value { font-size: 12pt; line-height: {{ $lineHeight(18) }}; }
        .summary .net { background: #eef1f4; border-bottom: 3pt solid #89939e; }
        .summary .net .value { color: #303740; }

        .section-heading { margin-bottom: 7pt; page-break-after: avoid; }
        .section-heading th { font-size: 11pt; line-height: {{ $lineHeight(17) }}; color: #252b33; }
        .section-heading td { text-align: {{ $rtl ? 'left' : 'right' }}; font-size: 8pt; color: #626b75; }
        .register { font-size: 8pt; line-height: {{ $lineHeight(12) }}; }
        .register thead { display: table-header-group; }
        .register tr { page-break-inside: avoid; }
        .register th { padding: 8pt 6pt; background: #f0f2f4; border-top: 1pt solid #d0d6dc; border-bottom: 1pt solid #d0d6dc; color: #3d4650; }
        .register td { padding: 4pt 6pt; border-bottom: 1pt solid #e6e9ec; vertical-align: middle; }
        .register .alternate td { background: #fafbfc; }
        .register .amount { text-align: right; }
        .register .net { font-weight: 700; color: #303740; }
        .employee-name { font-weight: 700; line-height: {{ $lineHeight(11) }}; }
        .employee-code { color: #626b75; font-size: 7pt; line-height: {{ $lineHeight(9) }}; margin-top: 1pt; }
        .register .totals td { border-top: 2pt solid #aab2bb; background: #f0f2f4; font-weight: 700; padding-top: 10pt; padding-bottom: 10pt; }
        .register .empty { text-align: center; padding: 24pt; color: #626b75; }
        .report-note { margin-top: 12pt; padding-top: 8pt; border-top: 1pt solid #dfe3e7; color: #626b75; font-size: 8pt; page-break-inside: avoid; }
    </style>
</head>
<body>
<table class="masthead">
    <tr>
        @if($hasLogo)
            <td class="logo-cell"><img class="logo" alt="" src="{{ $logo }}"></td>
        @endif
        <td class="brand-cell text">
            <div class="brand">{{ setting('app_name') ?: config('app.name') }}</div>
        </td>
        <td class="document-title text">{{ __('hr::payroll_runs.payroll_run') }}</td>
    </tr>
</table>

<table class="metadata">
    <colgroup>
        @foreach($metadata as $item)
            <col style="width: 25%;">
        @endforeach
    </colgroup>
    <tr>
        @foreach($metadata as $item)
            <td class="text">
                <div class="label">{{ $item['label'] }}</div>
                <div class="value">{{ $item['value'] }}</div>
            </td>
        @endforeach
    </tr>
</table>
<table class="dates">
    <tr>
        @foreach($dates as $date)
            <td>
                <div class="label text">{{ $date['label'] }}</div>
                <div class="number">{{ $date['value'] }}</div>
            </td>
        @endforeach
    </tr>
</table>

<table class="summary">
    <colgroup>
        @foreach($summaryFields as $field)
            <col style="width: {{ $field === 'count' ? 12 : 22 }}%;">
        @endforeach
    </colgroup>
    <tr>
        @foreach($summaryFields as $field)
            <td class="text {{ $field === 'count' ? 'count' : ($field === 'net_amount' ? 'net' : '') }}">
                <div class="label">{{ $field === 'count' ? __('hr::payroll_runs.table.payslips_count') : __('hr::payroll_payslips.table.'.$field) }}</div>
                <div class="value number">{{ $field === 'count' ? $run->payslips->count() : $totals[$field]->format(locale: $locale) }}</div>
            </td>
        @endforeach
    </tr>
</table>

<table class="section-heading">
    <tr>
        @if($rtl)
            <td><span class="number">{{ $currency }}</span></td>
        @endif
        <th class="text">{{ __('hr::payroll_payslips.payroll_payslips') }}</th>
        @if(! $rtl)
            <td><span class="number">{{ $currency }}</span></td>
        @endif
    </tr>
</table>
<table class="register">
    <colgroup>
        @foreach($columns as $column)
            <col style="width: {{ $column['width'] }}%;">
        @endforeach
    </colgroup>
    <thead>
    <tr>
        @foreach($columns as $key => $column)
            <th style="width: {{ $column['width'] }}%;" class="text {{ in_array($key, $amountFields, true) ? 'amount' : '' }}">{{ $column['label'] }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @forelse($run->payslips as $payslip)
        <tr class="{{ $loop->even ? 'alternate' : '' }}">
            @foreach($columns as $key => $column)
                <td class="{{ in_array($key, $amountFields, true) ? 'amount' : 'text' }} {{ $key === 'net_amount' ? 'net' : '' }}">
                    @if($key === 'reference')
                        <span class="number">{{ $payslip->reference_id ?: ('#'.$payslip->id) }}</span>
                    @elseif($key === 'employee')
                        <div class="employee-name">{{ $payslip->employee?->name ?: '-' }}</div>
                        @if($payslip->employee?->code)
                            <div class="employee-code number">{{ $payslip->employee->code }}</div>
                        @endif
                    @elseif(in_array($key, $amountFields, true))
                        <span class="number">{{ $payslip->{$key}->format(locale: $locale) }}</span>
                    @else
                        {{ $payslip->{$key}?->trans() ?? '-' }}
                    @endif
                </td>
            @endforeach
        </tr>
    @empty
        <tr><td colspan="8" class="empty text">{{ __('admin::admin.table.no_data_available') }}</td></tr>
    @endforelse
    @if($run->payslips->isNotEmpty())
        {{-- A body row prints totals once; a table footer can repeat them on every page. --}}
        <tr class="totals">
            @if(! $rtl)
                <td colspan="4" class="text">{{ __('hr::payroll_runs.pdf.total') }}</td>
            @endif
            @foreach($rtl ? array_reverse($amountFields) : $amountFields as $field)
                <td class="amount {{ $field === 'net_amount' ? 'net' : '' }}"><span class="number">{{ $totals[$field]->format(locale: $locale) }}</span></td>
            @endforeach
            @if($rtl)
                <td colspan="4" class="text">{{ __('hr::payroll_runs.pdf.total') }}</td>
            @endif
        </tr>
    @endif
    </tbody>
</table>
<div class="report-note text">{{ __('hr::payroll_runs.pdf.totals_note') }}</div>
</body>
</html>
