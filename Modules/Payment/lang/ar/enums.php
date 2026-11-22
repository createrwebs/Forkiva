<?php

return [
    'payment_methods' => [
        'cash' => 'نقداً',
        'card' => 'بطاقة',
        'bank_transfer' => 'تحويل بنكي',
        'mobile_wallet' => 'محفظة إلكترونية',
        'promptpay' => 'برومبت باي',
        'gift_card' => 'بطاقة هدية',
        'reservation_deposit' => 'عربون حجز',
    ],

    'payment_statuses' => [
        'pending' => 'قيد الانتظار',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
        'refunded' => 'مسترد',
    ],

    'payment_modes' => [
        'full' => 'دفع كامل',
        'partial' => 'دفع جزئي',
    ],

    'payment_types' => [
        'payment' => 'دفع',
        'refund' => 'استرداد',
    ],

];
