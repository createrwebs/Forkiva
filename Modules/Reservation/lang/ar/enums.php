<?php

return [
    'reservation_statuses' => [
        'pending' => 'قيد الانتظار',
        'confirmed' => 'مؤكد',
        'arrived' => 'وصل',
        'seated' => 'تمت الجلوس',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
        'rejected' => 'مرفوض',
        'no_show' => 'لم يحضر',
        'expired' => 'منتهي',
    ],
    'reservation_types' => [
        'standard' => 'عادي',
        'walk_in' => 'بدون حجز مسبق',
        'large_party' => 'مجموعة كبيرة',
        'private_room' => 'غرفة خاصة',
        'event' => 'فعالية',
        'vip' => 'مميز',
    ],
    'table_assignment_modes' => [
        'assign_later' => 'تعيين لاحقاً',
        'assign_now' => 'تعيين الآن',
        'auto_assign' => 'تعيين تلقائي',
    ],
    'table_assignment_statuses' => [
        'unassigned' => 'غير معين',
        'assigned' => 'معين',
        'partially_assigned' => 'معين جزئياً',
        'not_required' => 'غير مطلوب',
    ],
    'reservation_payment_statuses' => [
        'not_required' => 'غير مطلوب',
        'unpaid' => 'غير مدفوع',
        'partially_paid' => 'مدفوع جزئياً',
        'paid' => 'مدفوع',
        'refunded' => 'مسترد',
        'failed' => 'فشل',
    ],
    'reservation_reminder_statuses' => [
        'pending' => 'قيد الانتظار',
        'sent' => 'مرسل',
        'failed' => 'فشل',
        'cancelled' => 'ملغي',
    ],
    'reservation_waitlist_statuses' => [
        'waiting' => 'في الانتظار',
        'notified' => 'تم الإشعار',
        'converted' => 'تم التحويل',
        'cancelled' => 'ملغي',
        'expired' => 'منتهي',
    ],
    'deposit_transaction_types' => [
        'opening' => 'رصيد افتتاحي',
        'collection' => 'تحصيل',
        'application' => 'تطبيق على الطلب',
        'refund' => 'استرداد',
        'forfeiture' => 'مصادرة',
        'reversal' => 'عكس',
    ],
];
