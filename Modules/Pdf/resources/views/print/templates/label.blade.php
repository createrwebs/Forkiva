<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <style>
        body { margin: 0; padding: 0; font-family: 'Sarabun', sans-serif; font-size: 11px; }
        .receipt { width: {{ $profile['printable_width_mm'] ?? 46 }}mm; }
        .sticker { padding: 3mm 2mm; border-bottom: 1px dashed #000; page-break-after: always; }
        .header { display: flex; justify-content: space-between; font-weight: 700; font-size: 12px; }
        .item-name { font-size: 13px; font-weight: 700; margin-top: 2px; }
        .options { font-size: 10px; margin-top: 2px; }
        .footer { font-size: 9px; margin-top: 3px; color: #333; }
    </style>
</head>
<body>
<div class="receipt">
    @foreach(($payload['labels'] ?? []) as $label)
        <div class="sticker">
            <div class="header">
                <span>#{{ $label['order_reference_no'] ?? '' }}</span>
                <span>{{ $label['sequence'] ?? '' }}</span>
            </div>
            <div class="item-name">{{ $label['item_name'] ?? '' }}</div>
            @if(! empty($label['options']))
                <div class="options">{{ implode(', ', $label['options']) }}</div>
            @endif
            <div class="footer">{{ $label['order_type'] ?? '' }} • {{ $label['created_at'] ?? '' }}</div>
        </div>
    @endforeach
</div>
</body>
</html>
