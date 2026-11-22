<?php

return [
    'purchases' => 'การซื้อ',
    'purchase' => 'การซื้อ',

    'table' => [
        'reference_number' => 'เลขอ้างอิง',
        'supplier' => 'ซัพพลายเออร์',
        'currency' => 'สกุลเงิน',
        'currency_rate' => 'อัตราแลกเปลี่ยน',
        'discount' => 'ส่วนลด',
        'tax' => 'ภาษี',
        'sub_total' => 'ยอดรวมย่อย',
        'total' => 'ยอดรวม',
        'status' => 'สถานะ',
        'expected_at' => 'คาดว่าจะได้รับ',
        'notes' => 'หมายเหตุ',
        'items_count' => 'จำนวนรายการ',
        'receipts_count' => 'จำนวนใบรับ',
    ],

    'form' => [
        'cards' => [
            'purchase_information' => 'ข้อมูลการซื้อ',
            'purchase_items' => 'รายการซื้อ',
        ],
        'sub_total' => 'ยอดรวมย่อย',
        'total' => 'ยอดรวม',
        'line_total' => 'รวมต่อบรรทัด',
        'add_item' => 'เพิ่มรายการ',
    ],

    'filters' => [
        'received' => 'รับแล้ว',
        'supplier' => 'ซัพพลายเออร์',
        'status' => 'สถานะ',
        'expected_at' => 'คาดว่าจะได้รับ',
        'received_at' => 'รับเมื่อ',
    ],

    'buttons' => [
        'mark_as_received' => 'ทำเครื่องหมายว่ารับแล้ว',
    ],

    'mark_as_received' => [
        'dialog' => [
            'title' => 'ยืนยันจำนวนที่รับ',
            'description' => 'ตรวจสอบและยืนยันจำนวนที่รับสำหรับแต่ละรายการในใบสั่งซื้อ',
        ],

        'table' => [
            'ingredient' => 'วัตถุดิบ',
            'ordered_quantity' => 'จำนวนที่สั่ง',
            'received_quantity' => 'จำนวนที่รับ',
            'unit_cost' => 'ต้นทุนต่อหน่วย',
        ],
    ],
    'show' => [
        'reference_number' => 'เลขอ้างอิง',
        'branch' => 'สาขา',
        'supplier' => 'ซัพพลายเออร์',
        'status' => 'สถานะ',
        'expected_at' => 'คาดว่าจะได้รับ',
        'updated_at' => 'อัปเดตเมื่อ',
        'created_at' => 'สร้างเมื่อ',
        'sub_total' => 'ยอดรวมย่อย',
        'tax' => 'ภาษี',
        'total' => 'ยอดรวม',
        'discount' => 'ส่วนลด',
        'notes' => 'หมายเหตุ',

        'ingredient' => 'วัตถุดิบ',
        'quantity' => 'จำนวน',
        'received_quantity' => 'จำนวนที่รับ',
        'unit_cost' => 'ต้นทุนต่อหน่วย',
        'line_total' => 'รวมต่อบรรทัด',
        'receipt' => 'ใบรับ',
        'ref' => 'อ้างอิง',
        'cards' => [
            'purchase_information' => 'ข้อมูลการซื้อ',
            'purchase_items' => 'รายการซื้อ',
            'purchase_receipts' => 'ใบรับสินค้าซื้อ',
        ],
    ],
];