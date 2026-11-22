@extends('pdf::print.layout')

@php
    $isNarrow = (int)($profile['paper_width_mm'] ?? 80) <= 58;

    $order = (array)data_get($payload, 'order', []);
    $branch = (array)data_get($payload, 'branch', []);
    $customer = (array)data_get($payload, 'customer', []);
    $table = data_get($payload, 'table');
    $discount = data_get($payload, 'discount');
    $products = collect(data_get($payload, 'products', []));
    $taxes = collect(data_get($payload, 'tax_summary', data_get($payload, 'taxes', [])));
    $charges = collect(data_get($payload, 'charges', []));
    $payments = collect(data_get($payload, 'payments', []));

    $currencySubunit = (int)data_get($payload, 'currency_subunit', 2);
    $fmt = fn($n) => number_format((float)($n ?? 0), $currencySubunit, '.', '');
    $fmtQty = function ($n): string {
        $value = number_format((float)($n ?? 0), 3, '.', '');

        return rtrim(rtrim($value, '0'), '.') ?: '0';
    };

    $defaultLocale = (string)setting('default_locale', config('app.locale'));
    $t = fn(string $key) => __("printer::thermal_print.$key", [], $defaultLocale);

    $logo = data_get($branch, 'logo');
    $branchName = data_get($branch, 'name') ?: data_get($branch, 'legal_name');
    $branchAddress = data_get($branch, 'address_line1');
    $branchContact = collect([
        data_get($branch, 'phone'),
        data_get($branch, 'email'),
    ])->filter()->implode(' | ');

    $orderNumber = data_get($order, 'order_number') ?: data_get($order, 'reference_no') ?: '---';
    $orderDate = data_get($order, 'order_date');
    $tableName = !empty($table) ? data_get($table, 'name') : null;
    $customerName = data_get($customer, 'name');
    $customerPhone = data_get($customer, 'phone');
    $vehicleValue = trim(implode(' | ', array_filter([
        data_get($order, 'car_plate'),
        data_get($order, 'car_description'),
    ])));
    $notes = trim((string)data_get($order, 'notes', ''));

    $topMetaRows = collect([
        ['label' => $t('order_type'), 'value' => data_get($order, 'type')],
        ['label' => $t('table'), 'value' => $tableName],
        ['label' => $t('customer'), 'value' => $customerName],
        ['label' => $t('phone'), 'value' => $customerPhone, 'ltr' => true],
        ['label' => $t('vehicle'), 'value' => $vehicleValue],
    ])->filter(fn(array $row) => filled($row['value']))->values();
@endphp

@section('title', 'Bill')

@section('header')
    <section class="section bill-sheet">
        @if(!empty($logo))
            <img class="logo bill-logo" src="{{ $logo }}" alt="{{ $branchName ?: 'Logo' }}">
        @endif

        <div class="bill-ticket-title">{{ $t('bill') }}</div>

        @if($branchName)
            <div class="bill-brand">{{ $branchName }}</div>
        @endif

        @if($branchAddress)
            <div class="bill-brand-subtitle">{{ $branchAddress }}</div>
        @endif

        @if($branchContact !== '')
            <div class="bill-brand-subtitle"><span class="mono">{{ $branchContact }}</span></div>
        @endif

        <div class="bill-order-number">#{{ $orderNumber }}</div>

        @if(filled($orderDate))
            <div class="bill-order-date">
                <span class="bill-order-date-label">{{ $t('order_date') }}</span>
                <span class="bill-order-date-value">{{ $orderDate }}</span>
            </div>
        @endif

        @if($topMetaRows->isNotEmpty())
            <div class="bill-details-block">
                @foreach($topMetaRows as $row)
                    <div class="bill-details-row">
                        <div class="bill-details-label">{{ $row['label'] }}</div>
                        <div class="bill-details-value">@if($row['ltr'] ?? false)<span class="mono">{{ $row['value'] }}</span>@else{{ $row['value'] }}@endif</div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection

@section('content')
    <section class="section">
        <h2 class="section-title">{{ $t('items') }}</h2>

        @if($products->isEmpty())
            <div class="secondary">{{ $t('no_items') }}</div>
        @else
            @if(!$isNarrow)
                <div class="items-head">
                    <div>{{ $t('description') }}</div>
                    <div class="text-end">{{ $t('qty') }}</div>
                    <div class="text-end">{{ $t('amount') }}</div>
                </div>
            @endif

            <div class="items">
                @foreach($products as $product)
                    @php
                        $product = (array)$product;
                        $options = collect(data_get($product, 'options', []));
                        $qty = (float)data_get($product, 'quantity', 0);
                        $unitPrice = (float)data_get($product, 'unit_price', 0);
                        $lineTotal = (float)(data_get($product, 'total') ?? data_get($product, 'subtotal') ?? 0);
                        $taxTotal = (float)data_get($product, 'tax_total', 0);
                    @endphp

                    <article class="item">
                        @if($isNarrow)
                            <div class="compact-item-top">
                                <div class="item-main">
                                    <div class="item-name">{{ data_get($product, 'name') }}</div>
                                </div>
                                <div class="item-amount"><span class="number">{{ $fmt($lineTotal) }}</span></div>
                            </div>

                            <div class="compact-item-meta">
                                <div><span class="number">{{ $fmtQty($qty) }}</span> x <span class="number">{{ $fmt($unitPrice) }}</span></div>
                                @if($taxTotal > 0)
                                    <div>{{ $t('tax') }} <span class="number">{{ $fmt($taxTotal) }}</span></div>
                                @endif
                            </div>
                        @else
                            <div class="item-grid">
                                <div class="item-main">
                                    <div class="item-name">{{ data_get($product, 'name') }}</div>
                                    <div class="item-caption">
                                        {{ $t('unit') }}: <span class="number">{{ $fmt($unitPrice) }}</span>
                                        @if($taxTotal > 0)
                                            | {{ $t('tax') }}: <span class="number">{{ $fmt($taxTotal) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="item-qty"><span class="number">{{ $fmtQty($qty) }}</span></div>
                                <div class="item-amount"><span class="number">{{ $fmt($lineTotal) }}</span></div>
                            </div>
                        @endif

                        @if($options->isNotEmpty())
                            <div class="item-options">
                                @foreach($options as $option)
                                    @php
                                        $option = (array)$option;
                                        $values = collect(data_get($option, 'values', []));
                                        $labels = $values->pluck('label')->filter()->implode(', ');
                                        $optionPrice = (float)$values->sum('price');
                                    @endphp
                                    <div class="item-option">
                                        + {{ data_get($option, 'name') }}@if($labels !== '')
                                            : {{ $labels }}
                                        @endif
                                        @if($optionPrice > 0)
                                            | <span class="number">{{ $fmt($optionPrice) }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="section">
        <h2 class="section-title">{{ $t('summary') }}</h2>

        <div class="summary-box">
            @if(!is_null(data_get($order, 'subtotal')))
                <div class="summary-row">
                    <div class="summary-label">{{ $t('subtotal') }}</div>
                    <div class="summary-value"><span class="number">{{ $fmt(data_get($order, 'subtotal')) }}</span></div>
                </div>
            @endif

            @if(!empty($discount))
                <div class="summary-row">
                    <div class="summary-label">
                        {{ $t('discount') }}
                        @if(data_get($discount, 'name'))
                            ({{ data_get($discount, 'name') }})
                        @endif
                    </div>
                    <div class="summary-value">-<span class="number">{{ $fmt(data_get($discount, 'amount')) }}</span></div>
                </div>
            @endif

            @include('pdf::print.partials.tax-summary', compact('taxes', 'fmt', 't'))

            @foreach($charges as $charge)
                <div class="summary-row">
                    <div class="summary-label">{{ data_get($charge, 'name') }}</div>
                    <div class="summary-value"><span class="number">{{ $fmt(data_get($charge, 'amount')) }}</span></div>
                </div>
            @endforeach

            @if(!is_null(data_get($order, 'total')))
                <div class="summary-row total">
                    <div class="summary-label">{{ $t('total') }}</div>
                    <div class="summary-value"><span class="number">{{ $fmt(data_get($order, 'total')) }}</span></div>
                </div>
            @endif

            @if(!is_null(data_get($order, 'due_amount')) && (float)data_get($order, 'due_amount') > 0)
                <div class="summary-row balance">
                    <div class="summary-label">{{ $t('due') }}</div>
                    <div class="summary-value"><span class="number">{{ $fmt(data_get($order, 'due_amount')) }}</span></div>
                </div>
            @endif
        </div>
    </section>

    @if($payments->isNotEmpty())
        <section class="section">
            <h2 class="section-title">{{ $t('payments') }}</h2>

            <div class="payment-list">
                @foreach($payments as $payment)
                    @php
                        $method = (string)data_get($payment, 'method', '');
                        $paymentType = strtolower((string)data_get($payment, 'type', ''));
                        $isRefund = str_contains($paymentType, 'refund');
                        $transactionId = (string)data_get($payment, 'transaction_id', '');
                    @endphp

                    <div class="payment-row">
                        <div>
                            <div class="payment-method">
                                {{ $method }}
                                @if($isRefund)
                                    ({{ $t('refund') }})
                                @endif
                            </div>

                            @if($transactionId !== '')
                                <div class="payment-detail mono">
                                    {{ $t('transaction') }}: {{ $transactionId }}
                                </div>
                            @endif
                        </div>

                        <div class="payment-amount"><span class="number">{{ $fmt(data_get($payment, 'amount')) }}</span></div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection

@section('footer')
    @if($notes !== '')
        <section class="section kitchen-subsection">
            <h2 class="section-title">{{ $t('notes') }}</h2>
            <div class="kitchen-notes-panel">{{ $notes }}</div>
        </section>
    @endif

    <section class="section text-center secondary bill-footer">
        {{ $t('thank_you_for_your_visit') }}
    </section>
@endsection
