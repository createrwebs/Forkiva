@if ($party->legal_name)
    <div class="party-name">{{ $party->legal_name }}</div>
@endif

@if ($party->address_line1 || $party->address_line2)
    <div class="party-text">
        {{ $party->address_line1 }}
        @if ($party->address_line2)
            , {{ $party->address_line2 }}
        @endif
    </div>
@endif

@if ($party->city || $party->state || $party->country_name)
    <div class="party-text">
        @if ($party->city)
            {{ $party->city }},
        @endif
        @if ($party->state)
            {{ $party->state }},
        @endif
        {{ $party->country_name }}
    </div>
@endif

@if ($party->postal_code)
    <div class="party-text">{{ $party->postal_code }}</div>
@endif

@if ($party->vat_tin)
    <div class="party-text">
        <strong>@lang("invoice::invoices.show.vat_tin")</strong>: {{ $party->vat_tin }}
    </div>
@endif

@if ($party->tax_branch_code)
    <div class="party-text">
        <strong>@lang("invoice::invoices.show.tax_branch_code")</strong>:
        {{ $party->tax_branch_code === '00000'
            ? __('invoice::invoices.show.head_office')
            : __('invoice::invoices.show.branch_no', ['code' => ltrim($party->tax_branch_code, '0') ?: $party->tax_branch_code]) }}
    </div>
@endif

@if ($party->cr_number)
    <div class="party-text">
        <strong>@lang("invoice::invoices.show.cr_number")</strong>: {{ $party->cr_number }}
    </div>
@endif

@if ($party->email || $party->phone)
    <div class="party-contact">
        @if ($party->email)
            {{ $party->email }}
        @endif
        @if ($party->phone)
            @if($party->email)<br>@endif
            <span dir="ltr">{{ $party->phone }}</span>
        @endif
    </div>
@endif
