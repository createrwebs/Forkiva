<?php

return [
    'stock_movements' => 'حركات المخزون',
    'stock_movement' => 'حركة المخزون',

    'table' => [
        'ingredient_name' => 'المكوّن',
        'unit_symbol' => 'رمز الوحدة',
        'type' => 'النوع',
        'quantity' => 'الكمية',
        'unit_cost' => 'تكلفة الوحدة',
        'total_cost' => 'التكلفة الإجمالية',
        'currency' => 'العملة',
        'currency_rate' => 'سعر الصرف',
        'cost_snapshot_source' => 'مصدر لقطة التكلفة',
        'source_id' => 'معرّف المصدر',
        'source_type' => 'نوع المصدر',
        'source_name' => 'اسم المصدر',
        'note' => 'ملاحظة',
    ],

    'form' => [
        'cards' => [
            'stock_movement_information' => 'معلومات حركة المخزون',
        ],
    ],

    'filters' => [
        'type' => 'النوع',
        'ingredient' => 'المكوّن',
    ],

    'actions' => [
        'correct' => 'تصحيح الحركة',
    ],

    'correction' => [
        'title' => 'تصحيح حركة المخزون',
        'description' => 'ينشئ هذا الإجراء حركة عكسية ثابتة وحركة بديلة دون تعديل الحركة الأصلية.',
        'replacement_quantity' => 'الكمية البديلة',
        'reason' => 'سبب التصحيح',
    ],

    'show' => [
        'details' => 'تفاصيل حركة المخزون',
        'audit' => 'المصدر والتدقيق',
        'branch' => 'الفرع',
        'ingredient' => 'المكوّن',
        'type' => 'النوع',
        'quantity' => 'الكمية',
        'unit_cost' => 'تكلفة الوحدة',
        'total_cost' => 'التكلفة الإجمالية',
        'currency' => 'العملة',
        'currency_rate' => 'سعر الصرف',
        'note' => 'ملاحظة',
        'source_id' => 'معرّف المصدر',
        'source_type' => 'نوع المصدر',
        'source_name' => 'اسم المصدر',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ التحديث',
        'occurred_at' => 'وقت الحدوث',
        'committed_at' => 'وقت الاعتماد',
        'idempotency_key' => 'مفتاح منع التكرار',
        'accounting_status' => 'حالة الترحيل المحاسبي',
        'original_movement' => 'الحركة الأصلية',
        'reversals' => 'الحركات العكسية',
        'related_movements' => 'الحركات المرتبطة',
        'reversal' => 'الحركة العكسية',
    ],
];
