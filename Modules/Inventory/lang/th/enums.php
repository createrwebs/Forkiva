<?php

return [
    'unit_types' => [
        'mass' => 'น้ำหนัก',
        'volume' => 'ปริมาตร',
        'count' => 'นับเป็นชิ้น',
        'custom' => 'กำหนดเอง',
    ],

    'stock_movement_types' => [
        'in' => 'เข้า',
        'out' => 'ออก',
        'spoil' => 'เน่าเสีย',
        'adjust_add' => 'ปรับปรุงเพิ่ม',
        'adjust_subtract' => 'ปรับปรุงลด',
        'transfer_in' => 'โอนเข้า',
        'transfer_out' => 'โอนออก',
        'return_supplier' => 'คืนซัพพลายเออร์',
        'sample' => 'ตัวอย่าง',
        'waste' => 'ของเสีย',
        'staff_meal' => 'อาหารพนักงาน',
    ],

    'purchase_statuses' => [
        'draft' => 'ฉบับร่าง',
        'pending' => 'รอดำเนินการ',
        'partially_received' => 'รับบางส่วน',
        'received' => 'รับแล้ว',
        'cancelled' => 'ยกเลิก',
    ],

    'inventory_count_statuses' => [
        'draft' => 'ฉบับร่าง',
        'approved' => 'อนุมัติแล้ว',
        'cancelled' => 'ยกเลิก',
    ],

    'stock_transfer_statuses' => [
        'draft' => 'ฉบับร่าง',
        'approved' => 'อนุมัติแล้ว',
        'dispatched' => 'ส่งแล้ว',
        'partially_received' => 'รับบางส่วน',
        'received' => 'รับแล้ว',
        'closed_with_discrepancy' => 'ปิดพร้อมผลต่าง',
        'cancelled' => 'ยกเลิก',
        'voided' => 'เป็นโมฆะ',
    ],

    'inventory_stock_sync_operations' => [
        'deduct' => 'ตัดสต็อก',
        'restore' => 'คืนสต็อก',
    ],

    'inventory_stock_sync_statuses' => [
        'pending_stock' => 'รอสต็อก',
        'processing_stock' => 'กำลังดำเนินการสต็อก',
        'pending_accounting' => 'รอบัญชี',
        'completed' => 'เสร็จสิ้น',
        'failed' => 'ล้มเหลว',
    ],
];