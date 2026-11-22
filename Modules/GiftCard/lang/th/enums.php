<?php

return [
    'scopes' => [
        'branch' => 'สาขา',
        'global' => 'ทั้งระบบ',
    ],
    'statuses' => [
        'inactive' => 'ไม่ใช้งาน',
        'active' => 'ใช้งาน',
        'used' => 'ใช้แล้ว',
        'expired' => 'หมดอายุ',
        'disabled' => 'ปิดใช้งาน',
    ],
    'breakage_batch_statuses' => [
        'draft' => 'ฉบับร่าง',
        'reviewed' => 'ตรวจสอบแล้ว',
        'posted' => 'ผ่านรายการแล้ว',
        'reversed' => 'กลับรายการแล้ว',
    ],
    'transaction_types' => [
        'purchase' => 'ซื้อ',
        'redeem' => 'แลกใช้',
        'refund' => 'คืนเงิน',
        'recharge' => 'เติมเงิน',
        'adjustment' => 'ปรับปรุง',
        'expire' => 'หมดอายุ',
        'cash_out' => 'ถอนเป็นเงินสด',
        'issue_void' => 'ยกเลิกการออกบัตร',
        'adjustment_decrease' => 'ปรับปรุงลด',
        'breakage' => 'ยอดค้าง unused',
        'reversal' => 'กลับรายการ',
    ],
    'funding_types' => [
        'paid' => 'ชำระเงิน',
        'promotional' => 'โปรโมชัน',
    ],
];