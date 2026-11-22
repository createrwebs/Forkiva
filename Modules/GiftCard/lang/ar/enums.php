<?php

return [
    'scopes' => [
        'branch' => 'فرع',
        'global' => 'عام',
    ],
    'statuses' => [
        'inactive' => 'غير نشط',
        'active' => 'نشط',
        'used' => 'مستخدم',
        'expired' => 'منتهي',
        'disabled' => 'معطل',
    ],
    'breakage_batch_statuses' => [
        'draft' => 'مسودة',
        'reviewed' => 'تمت المراجعة',
        'posted' => 'تم الترحيل',
        'reversed' => 'تم العكس',
    ],
    'transaction_types' => [
        'purchase' => 'بيع',
        'redeem' => 'استبدال',
        'refund' => 'استرجاع',
        'recharge' => 'إعادة شحن',
        'adjustment' => 'تعديل',
        'expire' => 'منتهي',
        'cash_out' => 'صرف نقدي',
        'issue_void' => 'إلغاء الإصدار',
        'adjustment_decrease' => 'تخفيض الرصيد',
        'breakage' => 'رصيد غير مطالب به',
        'reversal' => 'عكس',
    ],
    'funding_types' => [
        'paid' => 'مدفوع',
        'promotional' => 'ترويجي',
    ],
];
