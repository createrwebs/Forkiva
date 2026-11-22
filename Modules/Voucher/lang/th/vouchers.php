<?php

return [
    'vouchers' => 'บัตรกำนัล',
    'voucher' => 'บัตรกำนัล',

    'table' => [
        'name' => 'ชื่อ',
        'description' => 'คำอธิบาย',
        'type' => 'ประเภท',
        'value_text' => 'มูลค่า',
        'code' => 'รหัส',
        'minimum_spend' => 'ยอดซื้อขั้นต่ำ',
        'maximum_spend' => 'ยอดซื้อสูงสุด',
        'max_discount' => 'ส่วนลดสูงสุด',
        'conditions' => 'เงื่อนไข',
        'usage_limit' => 'จำนวนครั้งที่ใช้ได้',
        'per_customer_limit' => 'จำนวนครั้งต่อลูกค้า',
        'used' => 'ใช้ไปแล้ว',
        'meta' => 'ข้อมูลเสริม',
        'start_date' => 'วันเริ่มต้น',
        'end_date' => 'วันสิ้นสุด',
    ],

    'form' => [
        'cards' => [
            'voucher_information' => 'ข้อมูลบัตรกำนัล',
            'details' => 'รายละเอียด',
            'availability' => 'ช่วงเวลาที่ให้ใช้',
            'conditions' => 'เงื่อนไข',
            'limits' => 'ข้อจำกัด',
        ],
    ],
    'filters' => [
        'type' => 'ประเภท',
    ],
];