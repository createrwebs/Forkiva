<?php

return [
    'payment_methods' => [
        'cash' => 'เงินสด',
        'card' => 'บัตร',
        'bank_transfer' => 'โอนเงินผ่านธนาคาร',
        'mobile_wallet' => 'กระเป๋าเงินดิจิทัล',
        'promptpay' => 'พร้อมเพย์',
        'gift_card' => 'บัตรของขวัญ',
        'reservation_deposit' => 'เงินมัดจำการจอง',
    ],

    'payment_statuses' => [
        'pending' => 'รอดำเนินการ',
        'completed' => 'เสร็จสิ้น',
        'failed' => 'ล้มเหลว',
        'refunded' => 'คืนเงินแล้ว',
    ],

    'payment_modes' => [
        'full' => 'ชำระเต็มจำนวน',
        'partial' => 'ชำระบางส่วน',
    ],

    'payment_types' => [
        'payment' => 'รับชำระเงิน',
        'refund' => 'คืนเงิน',
    ],
];
