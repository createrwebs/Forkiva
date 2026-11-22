<?php

return [
    'floors' => [
        'name' => 'الطابق',
        'branch_id' => 'الفرع',
        'is_active' => 'نشط',
    ],
    'zones' => [
        'name' => 'الاسم',
        'branch_id' => 'الفرع',
        'floor_id' => 'الطابق',
        'is_active' => 'نشط',
    ],
    'tables' => [
        'name' => 'الاسم',
        'branch_id' => 'الفرع',
        'floor_id' => 'الطابق',
        'zone_id' => 'المنطقة',
        'capacity' => 'السعة',
        'shape' => 'الشكل',
        'is_active' => 'نشط',
        'waiter_id' => 'النادل',
    ],

    'table_merges' => [
        'table_ids' => 'الطاولات',
        'table_ids.*' => 'الطاولة',
        'type' => 'نوع الدمج',
    ],

    'table_qr' => [
        'product_id' => 'المنتج',
        'qty' => 'الكمية',
        'options' => 'الخيارات',
        'idempotency_key' => 'مفتاح الإرسال',
        'guest_count' => 'عدد الضيوف',
        'customer_note' => 'ملاحظة العميل',
        'discount_code' => 'رمز الخصم',
    ],
];
