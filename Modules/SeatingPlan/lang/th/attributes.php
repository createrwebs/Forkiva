<?php

return [
    'floors' => [
        'name' => 'ชั้น',
        'branch_id' => 'สาขา',
        'is_active' => 'เปิดใช้งาน',
    ],
    'zones' => [
        'name' => 'ชื่อ',
        'branch_id' => 'สาขา',
        'floor_id' => 'ชั้น',
        'is_active' => 'เปิดใช้งาน',
    ],
    'tables' => [
        'name' => 'ชื่อ',
        'branch_id' => 'สาขา',
        'floor_id' => 'ชั้น',
        'zone_id' => 'โซน',
        'capacity' => 'ความจุ',
        'shape' => 'รูปทรง',
        'is_active' => 'เปิดใช้งาน',
        'waiter_id' => 'พนักงานเสิร์ฟ',
    ],

    'table_merges' => [
        'table_ids' => 'โต๊ะ',
        'table_ids.*' => 'โต๊ะ',
        'type' => 'ประเภทการรวมโต๊ะ',
    ],

    'table_qr' => [
        'product_id' => 'สินค้า',
        'qty' => 'จำนวน',
        'options' => 'ตัวเลือก',
        'idempotency_key' => 'คีย์การส่ง',
        'guest_count' => 'จำนวนลูกค้า',
        'customer_note' => 'หมายเหตุลูกค้า',
        'discount_code' => 'รหัสส่วนลด',
    ],
];