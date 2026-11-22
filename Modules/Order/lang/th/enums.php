<?php

return [
    'order_types' => [
        'dine_in' => 'ทานที่ร้าน',
        'delivery' => 'จัดส่ง',
        'pick_up' => 'รับเอง',
        'takeaway' => 'ซื้อกลับบ้าน',
        'drive_thru' => 'ไดรฟ์ทรู',
        'pre_order' => 'สั่งล่วงหน้า',
        'catering' => 'จัดเลี้ยง',
    ],

    'order_statuses' => [
        'pending' => 'รอดำเนินการ',
        'confirmed' => 'ยืนยันแล้ว',
        'preparing' => 'กำลังเตรียม',
        'ready' => 'พร้อมเสิร์ฟ',
        'served' => 'เสิร์ฟแล้ว',
        'completed' => 'เสร็จสิ้น',
        'cancelled' => 'ยกเลิก',
        'refunded' => 'คืนเงินแล้ว',
        'merged' => 'รวมบิลแล้ว',
    ],

    'order_payment_statuses' => [
        'unpaid' => 'ยังไม่ชำระ',
        'paid' => 'ชำระแล้ว',
        'partially_paid' => 'ชำระบางส่วน',
    ],

    'reason_types' => [
        'cancellation' => 'ยกเลิก',
        'refund' => 'คืนเงิน',
    ],

    'order_product_statuses' => [
        'pending' => 'รอดำเนินการ',
        'preparing' => 'กำลังเตรียม',
        'ready' => 'พร้อมเสิร์ฟ',
        'served' => 'เสิร์ฟแล้ว',
        'cancelled' => 'ยกเลิก',
        'refunded' => 'คืนเงินแล้ว',
    ],

    'discount_types' => [
        'discount' => 'ส่วนลด',
        'voucher' => 'บัตรกำนัล',
    ],

    'order_charge_codes' => [
        'delivery_fee' => 'ค่าจัดส่ง',
        'small_order_surcharge' => 'ค่าบริการออเดอร์เล็ก',
        'peak_time_surcharge' => 'ค่าบริการช่วงเร่งด่วน',
        'tip' => 'ทิป',
        'service_charge' => 'ค่าบริการ',
    ],

    'order_sources' => [
        'table_qr' => 'สแกน QR ที่โต๊ะ',
    ],
];
