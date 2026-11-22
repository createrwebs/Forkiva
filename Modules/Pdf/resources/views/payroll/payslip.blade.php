@php
    use Illuminate\Support\Carbon;
    use Modules\Pdf\Enums\PdfDriver;
    use Modules\Support\Enums\DateTimeFormat;

    $locale = app()->getLocale();
    $rtl = isRtl($locale);
    $logo = getLogoBase64();
    $hasLogo = $logo && ! str_ends_with($logo, 'base64,');
    $regularFont = base64_encode(file_get_contents(storage_path('fonts/Cairo-Regular.ttf')));
    $boldFont = base64_encode(file_get_contents(storage_path('fonts/Cairo-Bold.ttf')));
    // Match Cairo's physical line spacing in browser engines and Dompdf.
    $lineHeight = static fn (float $points): string => (($pdfDriver ?? null) === PdfDriver::Dompdf ? $points / 2.0614 : $points).'pt';
    $employee = $payslip->employee;
    $period = $payslip->run?->period;
    $reference = $payslip->reference_id ?: '#'.$payslip->id;
    $bank = (array) ($employee?->bank_information ?? []);
    $snapshot = (array) ($payslip->calculation_snapshot ?? []);
    $periodFrom = $period?->date_from;
    $periodTo = $period?->date_to;
    $periodDays = ($periodFrom && $periodTo) ? Carbon::parse($periodFrom)->diffInDays(Carbon::parse($periodTo)) + 1 : null;
    $daysWorked = data_get($snapshot, 'days_worked') ?? data_get($snapshot, 'worked_days_total') ?? data_get($snapshot, 'present_days_total');
    $paidLeave = data_get($snapshot, 'leave_paid_days_total');
    $unpaidLeave = data_get($snapshot, 'leave_unpaid_days_total');
    // Calendar days are not proof of attendance; unknown snapshot values remain unknown.
    $leaveDays = is_numeric($paidLeave) && is_numeric($unpaidLeave) ? $paidLeave + $unpaidLeave : null;
    $formatNumber = static fn ($value): string => is_numeric($value) ? number_format((float) $value, 2, '.', '') : '-';
    $formatTime = static function ($minutes): string {
        if (! is_numeric($minutes)) {
            return '-';
        }
        $total = (int) round((float) $minutes);
        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    };
    $details = [
        ['label' => 'department', 'value' => $employee?->department?->name ?: '-'],
        ['label' => 'designation', 'value' => $employee?->designation?->name ?: '-'],
        ['label' => 'branch', 'value' => $payslip->run?->branch?->name ?: '-'],
        ['label' => 'period', 'value' => $period?->name ?: '-'],
        ['label' => 'salary_type', 'value' => $payslip->salary_type?->trans() ?? '-'],
        ['label' => 'status', 'value' => $payslip->status?->trans() ?? '-'],
    ];
    $attendance = [
        ['label' => 'days_in_month', 'value' => $periodFrom ? Carbon::parse($periodFrom)->daysInMonth : '-'],
        ['label' => 'period_days', 'value' => $periodDays ?? '-'],
        ['label' => 'days_worked', 'value' => $formatNumber($daysWorked)],
        ['label' => 'paid_leave_days', 'value' => $formatNumber($paidLeave)],
        ['label' => 'unpaid_leave_days', 'value' => $formatNumber($unpaidLeave)],
        ['label' => 'leave_days', 'value' => $formatNumber($leaveDays)],
        ['label' => 'absent_days', 'value' => $formatNumber(data_get($snapshot, 'absent_days_total'))],
        ['label' => 'worked_time', 'value' => $formatTime(data_get($snapshot, 'worked_minutes_total'))],
        ['label' => 'overtime_time', 'value' => $formatTime(data_get($snapshot, 'overtime_minutes_approved_total'))],
    ];
    $columns = ['component' => 44, 'quantity' => 12, 'rate' => 22, 'amount' => 22];
    $summary = ['gross' => 'gross_amount', 'deductions' => 'deductions_amount', 'net' => 'net_amount'];
    $bankDetails = [
        ['label' => 'bank_name', 'value' => data_get($bank, 'bank_name') ?: '-'],
        ['label' => 'bank_branch_name', 'value' => data_get($bank, 'branch_name') ?: '-'],
        ['label' => 'bank_account_name', 'value' => data_get($bank, 'account_name') ?: '-'],
    ];
    // Explicit column order keeps RTL tables consistent in Dompdf and browser drivers.
    if ($rtl) {
        $columns = array_reverse($columns, true);
        $summary = array_reverse($summary, true);
        $bankDetails = array_reverse($bankDetails);
    }
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('hr::payroll_payslips.template.title') }} {{ $reference }}</title>
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
        body { margin: 0; padding: 0; font-family: 'Cairo', 'DejaVu Sans', sans-serif; font-size: 8pt; line-height: {{ $lineHeight(12) }}; color: #303740; background: #fff; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; direction: ltr; }
        th, td { padding: 0; vertical-align: top; text-align: {{ $rtl ? 'right' : 'left' }}; overflow-wrap: break-word; word-wrap: break-word; }
        .text { direction: {{ $rtl ? 'rtl' : 'ltr' }}; }
        .number { direction: ltr; unicode-bidi: embed; }
        .label { color: #626b75; font-size: 7pt; line-height: {{ $lineHeight(10) }}; }
        .value { margin-top: 2pt; font-weight: 700; }
        .masthead { border-top: 4pt solid #f57c00; border-bottom: 1pt solid #dfe3e7; margin-bottom: 10pt; page-break-inside: avoid; }
        .masthead td { padding: 10pt 0; vertical-align: middle; }
        .masthead .logo-cell { width: 8%; text-align: left; }
        .logo { width: 30pt; height: auto; max-height: 36pt; }
        .masthead .brand-cell { width: {{ $hasLogo ? 62 : 70 }}%; text-align: left; }
        .brand { font-size: 14pt; line-height: {{ $lineHeight(19) }}; font-weight: 700; color: #252b33; }
        .document-title { width: 30%; text-align: right; font-size: 17pt; line-height: {{ $lineHeight(22) }}; font-weight: 700; color: #252b33; }
        .identity { margin-bottom: 9pt; page-break-inside: avoid; }
        .identity .employee { width: 65%; }
        .identity .reference { width: 35%; text-align: {{ $rtl ? 'left' : 'right' }}; }
        .employee-name { font-size: 12pt; line-height: {{ $lineHeight(17) }}; font-weight: 700; color: #252b33; margin: 3pt 0; margin-{{ $rtl ? 'left' : 'right' }}: 8pt; }
        .reference .value { font-size: 9pt; }
        .details { margin-bottom: 7pt; page-break-inside: avoid; }
        .details td { width: 33.33%; padding: 5pt 7pt; border: 1pt solid #dfe3e7; }
        .dates { margin-bottom: 9pt; page-break-inside: avoid; }
        .dates td { width: 50%; font-size: 7pt; line-height: {{ $lineHeight(10) }}; }
        .section-title { font-size: 9pt; line-height: {{ $lineHeight(13) }}; font-weight: 700; color: #252b33; margin: 11pt 0 5pt; page-break-after: avoid; }
        .lines { margin-bottom: 7pt; }
        .lines thead { display: table-header-group; }
        .lines tr { page-break-inside: avoid; }
        .lines th { background: #f0f2f4; border-top: 1pt solid #d0d6dc; border-bottom: 1pt solid #d0d6dc; padding: 5pt 6pt; font-size: 7pt; line-height: {{ $lineHeight(10) }}; color: #3d4650; }
        .lines td { padding: 4pt 6pt; border-bottom: 1pt solid #e6e9ec; vertical-align: middle; }
        .lines .alternate td { background: #fafbfc; }
        .lines .amount { text-align: right; }
        .lines .total-amount { font-weight: 700; }
        .lines .empty { text-align: center; padding: 9pt; color: #626b75; }
        .summary { margin: 9pt 0 12pt; page-break-inside: avoid; }
        .summary td { width: 33.33%; padding: 9pt; border: 1pt solid #dfe3e7; background: #f7f8fa; }
        .summary .value { font-size: 11pt; line-height: {{ $lineHeight(16) }}; }
        .summary .net { background: #eef1f4; border-bottom: 3pt solid #89939e; }
        .summary .net .value { font-size: 13pt; line-height: {{ $lineHeight(18) }}; color: #252b33; }
        .attendance { page-break-inside: avoid; }
        .attendance td { width: 33.33%; padding: 4pt 7pt; border: 1pt solid #e6e9ec; }
        .bank { page-break-inside: avoid; }
        .bank td { width: 33.33%; padding: 5pt 7pt; border: 1pt solid #e6e9ec; }
        .bank .value { font-weight: 400; }
    </style>
</head>
<body>
<table class="masthead">
    <tr>
        @if($hasLogo)
            <td class="logo-cell"><img class="logo" alt="" src="{{ $logo }}"></td>
        @endif
        <td class="brand-cell text"><div class="brand">{{ setting('app_name') ?: config('app.name') }}</div></td>
        <td class="document-title text">{{ __('hr::payroll_payslips.template.title') }}</td>
    </tr>
</table>
<table class="identity">
    <tr>
        @if($rtl)
            <td class="reference"><div class="label text">{{ __('hr::payroll_payslips.template.reference_id') }}</div><div class="value number">{{ $reference }}</div></td>
        @endif
        <td class="employee text">
            <div class="label">{{ __('hr::payroll_payslips.template.employee') }}</div>
            <div class="employee-name">{{ $employee?->name ?: '-' }}</div>
            <div class="number">{{ $employee?->code ?: '-' }}</div>
        </td>
        @if(! $rtl)
            <td class="reference"><div class="label text">{{ __('hr::payroll_payslips.template.reference_id') }}</div><div class="value number">{{ $reference }}</div></td>
        @endif
    </tr>
</table>
<table class="details">
    @foreach(array_chunk($details, 3) as $row)
        <tr>
            @foreach($rtl ? array_reverse($row) : $row as $item)
                <td class="text"><div class="label">{{ __('hr::payroll_payslips.template.'.$item['label']) }}</div><div class="value">{{ $item['value'] }}</div></td>
            @endforeach
        </tr>
    @endforeach
</table>
<table class="dates">
    <tr>
        @foreach($rtl ? ['date_to' => $periodTo, 'date_from' => $periodFrom] : ['date_from' => $periodFrom, 'date_to' => $periodTo] as $label => $date)
            <td><div class="label text">{{ __('hr::payroll_periods.table.'.$label) }}</div><div class="number">{{ dateTimeFormat($date, DateTimeFormat::Date, '-') }}</div></td>
        @endforeach
    </tr>
</table>
@foreach(['earning' => 'section_earning', 'deduction' => 'section_deduction'] as $type => $label)
    <div class="section-title text">{{ __('hr::payroll_payslips.template.'.$label) }}</div>
    <table class="lines">
        <colgroup>
            @foreach($columns as $width)
                <col style="width: {{ $width }}%;">
            @endforeach
        </colgroup>
        <thead>
        <tr>
            @foreach($columns as $key => $width)
                <th style="width: {{ $width }}%;" class="text {{ $key !== 'component' ? 'amount' : '' }}">{{ __('hr::payroll_payslips.template.'.$key) }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse($payslip->lines->filter(fn ($line) => $line->type?->value === $type) as $line)
            <tr class="{{ $loop->even ? 'alternate' : '' }}">
                @foreach($columns as $key => $width)
                    <td class="{{ $key === 'component' ? 'text' : 'amount' }} {{ $key === 'amount' ? 'total-amount' : '' }}">
                        @if($key === 'component')
                            {{ $line->name }}
                        @elseif($key === 'quantity')
                            <span class="number">{{ $formatNumber($line->quantity) }}</span>
                        @else
                            <span class="number">{{ $line->{$key}?->format(locale: $locale) ?? '-' }}</span>
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="4" class="empty text">{{ __('hr::payroll_payslips.template.no_data') }}</td></tr>
        @endforelse
        </tbody>
    </table>
@endforeach
<table class="summary">
    <tr>
        @foreach($summary as $label => $field)
            <td class="text {{ $label === 'net' ? 'net' : '' }}">
                <div class="label">{{ __('hr::payroll_payslips.template.'.$label) }}</div>
                <div class="value number">{{ $payslip->{$field}->format(locale: $locale) }}</div>
            </td>
        @endforeach
    </tr>
</table>
<div class="section-title text">{{ __('hr::payroll_payslips.template.pay_details') }}</div>
<table class="attendance">
    @foreach(array_chunk($attendance, 3) as $row)
        <tr>
            @foreach($rtl ? array_reverse($row) : $row as $item)
                <td data-attendance="{{ $item['label'] }}"><div class="label text">{{ __('hr::payroll_payslips.template.'.$item['label']) }}</div><div class="value number">{{ $item['value'] }}</div></td>
            @endforeach
        </tr>
    @endforeach
</table>
<div class="section-title text">{{ __('hr::payroll_payslips.template.bank_information') }}</div>
<table class="bank">
    <tr>
        @foreach($bankDetails as $item)
            <td class="text"><div class="label">{{ __('hr::payroll_payslips.template.'.$item['label']) }}</div><div class="value">{{ $item['value'] }}</div></td>
        @endforeach
    </tr>
    <tr>
        @if($rtl)
            <td><div class="label text">{{ __('hr::payroll_payslips.template.bank_swift_code') }}</div><div class="value number">{{ data_get($bank, 'swift_code') ?: '-' }}</div></td>
        @endif
        <td colspan="2"><div class="label text">{{ __('hr::payroll_payslips.template.bank_iban') }}</div><div class="value number">{{ data_get($bank, 'iban') ?: '-' }}</div></td>
        @if(! $rtl)
            <td><div class="label text">{{ __('hr::payroll_payslips.template.bank_swift_code') }}</div><div class="value number">{{ data_get($bank, 'swift_code') ?: '-' }}</div></td>
        @endif
    </tr>
</table>
</body>
</html>
