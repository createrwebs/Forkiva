<?php

return [
    'unit_types' => [
        'mass' => 'الوزن',
        'volume' => 'الحجم',
        'count' => 'العدد',
        'custom' => 'مخصص',
    ],

    'stock_movement_types' => [
        'in' => 'وارد',
        'out' => 'صادر',
        'spoil' => 'تالف',
        'adjust_add' => 'تعديل – إضافة',
        'adjust_subtract' => 'تعديل – خصم',
        'transfer_in' => 'تحويل وارد',
        'transfer_out' => 'تحويل صادر',
        'return_supplier' => 'مرتجع للمورد',
        'sample' => 'عينة',
        'waste' => 'هدر',
        'staff_meal' => 'وجبة موظف',
    ],

    'purchase_statuses' => [
        'draft' => 'مسودة',
        'pending' => 'قيد الانتظار',
        'partially_received' => 'تم الاستلام جزئيًا',
        'received' => 'تم الاستلام',
        'cancelled' => 'ملغي',
    ],

    'inventory_count_statuses' => [
        'draft' => 'مسودة',
        'approved' => 'معتمد',
        'cancelled' => 'ملغي',
    ],

    'stock_transfer_statuses' => [
        'draft' => 'مسودة',
        'approved' => 'معتمد',
        'dispatched' => 'تم الإرسال',
        'partially_received' => 'مستلم جزئياً',
        'received' => 'مستلم',
        'closed_with_discrepancy' => 'مغلق مع فرق',
        'cancelled' => 'ملغي',
        'voided' => 'مبطل',
    ],

    'inventory_stock_sync_operations' => [
        'deduct' => 'خصم المخزون',
        'restore' => 'استعادة المخزون',
    ],

    'inventory_stock_sync_statuses' => [
        'pending_stock' => 'بانتظار المخزون',
        'processing_stock' => 'جارٍ معالجة المخزون',
        'pending_accounting' => 'بانتظار المحاسبة',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
    ],
];
