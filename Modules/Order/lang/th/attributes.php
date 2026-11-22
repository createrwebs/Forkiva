<?php

return [
    'orders' => [
        'type' => 'ประเภท',
        'table_id' => 'โต๊ะ',
        'pos_register_id' => 'เครื่อง POS',
        'products.*' => 'สินค้า',
        'notes' => 'หมายเหตุ',
        'guest_count' => 'จำนวนลูกค้า',
        'payment_type' => 'ประเภทการชำระเงิน',
        'amount_to_be_paid' => 'ยอดที่ต้องชำระ',
        'car_plate' => 'ป้ายทะเบียนรถ',
        'car_description' => 'รายละเอียดรถ',
        'scheduled_at' => 'เวลานัดรับ',
        'products.*.actions' => 'การดำเนินการ',
        'products.*.actions.*.action' => 'การดำเนินการ',
        'products.*.actions.*.quantity' => 'จำนวน',
        'refund_payment_method' => 'ช่องทางคืนเงิน',
        'products.*.order_product_id' => 'รายการสินค้าในออเดอร์',
    ],

    'reasons' => [
        'name' => 'ชื่อ',
        'type' => 'ประเภท',
        'is_active' => 'เปิดใช้งาน',
    ],

    'cancel_or_refund' => [
        'register_id' => 'เครื่อง POS',
        'session_id' => 'กะขาย',
        'reason_id' => 'เหตุผล',
        'note' => 'หมายเหตุ',
        'refund_payment_method' => 'ช่องทางคืนเงิน',
        'gift_card_code' => 'รหัสบัตรของขวัญ',
    ],

    'payments' => [
        'register_id' => 'เครื่อง POS',
        'payment_mode' => 'รูปแบบการชำระเงิน',
        'amount_to_be_paid' => 'ยอดที่ต้องชำระ',
        'customer_given_amount' => 'เงินที่ลูกค้าให้',
        'change_return' => 'เงินทอน',
        'payments' => 'การชำระเงิน',
        'payments.*.method' => 'ช่องทางชำระเงิน',
        'payments.*.amount' => 'จำนวนเงิน',
        'payments.*.transaction_id' => 'รหัสธุรกรรม',
        'payments.*.gift_card_code' => 'รหัสบัตรของขวัญ',
    ],

    'print' => [
        'register_id' => 'เครื่อง POS',
        'branch_id' => 'สาขา',
    ],
];
