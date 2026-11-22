<?php

return [
    'suppliers' => [
        'name' => 'الاسم',
        'phone' => 'رقم الهاتف',
        'email' => 'البريد الإلكتروني',
        'address' => 'العنوان',
        'vat_tin' => 'الرقم الضريبي',
        'tax_branch_code' => 'رمز الفرع الضريبي',
        'branch_id' => 'الفرع',
    ],

    'units' => [
        'name' => 'الاسم',
        'symbol' => 'الرمز',
        'type' => 'النوع',
    ],

    'ingredients' => [
        'name' => 'الاسم',
        'branch_id' => 'الفرع',
        'unit_id' => 'الوحدة',
        'cost_per_unit' => 'التكلفة لكل وحدة',
        'alert_quantity' => 'كمية التنبيه',
        'is_returnable' => 'قابل للإرجاع',
    ],

    'stock_movements' => [
        'branch_id' => 'الفرع',
        'ingredient_id' => 'المكوّن',
        'supplier_id' => 'المورد',
        'type' => 'النوع',
        'quantity' => 'الكمية',
        'note' => 'ملاحظة',
        'reason' => 'السبب',
    ],

    'purchases' => [
        'branch_id' => 'الفرع',
        'supplier_id' => 'المورد',
        'notes' => 'ملاحظات',
        'expected_at' => 'تاريخ الاستلام المتوقع',
        'discount' => 'الخصم',
        'tax' => 'الضريبة',
        'items' => 'العناصر',
        'items.*' => 'عنصر',
        'items.*.ingredient_id' => 'المكوّن',
        'items.*.quantity' => 'الكمية',
        'items.*.received_quantity' => 'الكمية المستلمة',
        'items.*.unit_cost' => 'تكلفة الوحدة',
        'items.*.id' => 'المعرّف',
        'reference' => 'رقم المرجع',
    ],

    'inventory_counts' => [
        'branch_id' => 'الفرع',
        'count_date' => 'تاريخ الجرد',
        'notes' => 'ملاحظات',
        'reason' => 'سبب الإلغاء',
        'items' => 'عناصر الجرد',
        'items.*.ingredient_id' => 'المكوّن',
        'items.*.counted_quantity' => 'الكمية المعدودة',
    ],
    'stock_transfers' => [
        'source_branch_id' => 'فرع المصدر',
        'destination_branch_id' => 'فرع الوجهة',
        'expected_at' => 'الوصول المتوقع',
        'currency' => 'العملة',
        'notes' => 'ملاحظات',
        'reason' => 'السبب',
        'items' => 'عناصر التحويل',
        'items.*.source_ingredient_id' => 'مكوّن المصدر',
        'items.*.destination_ingredient_id' => 'مكوّن الوجهة',
        'items.*.quantity' => 'الكمية',
    ],
    'stock_transfer_receipts' => [
        'idempotency_key' => 'معرّف طلب الاستلام',
        'notes' => 'ملاحظات',
        'items' => 'عناصر الاستلام',
        'items.*.stock_transfer_item_id' => 'عنصر التحويل',
        'items.*.quantity' => 'الكمية المستلمة',
    ],
];
