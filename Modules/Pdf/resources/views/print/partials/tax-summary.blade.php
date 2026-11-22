@foreach($taxes as $tax)
    @php
        $scopeLabel = match (data_get($tax, 'scope')) {
            'product' => $t('product_tax'),
            'global' => $t('global_tax'),
            default => null,
        };
        $typeLabel = match (data_get($tax, 'type')) {
            'inclusive' => $t('inclusive_tax'),
            'exclusive' => $t('exclusive_tax'),
            default => null,
        };
        $rate = data_get($tax, 'rate');
        $details = collect([$scopeLabel, $typeLabel])
            ->filter()
            ->implode(' · ');
    @endphp
    <div class="summary-row">
        <div class="summary-label">
            <div>{{ data_get($tax, 'name') }}</div>
            <div class="secondary">{{ $details }}@if(is_numeric($rate)) · <span class="number">{{ (float) $rate }}%</span>@endif</div>
        </div>
        <div class="summary-value">{{ $fmt(data_get($tax, 'amount')) }}</div>
    </div>
@endforeach
