<?php

return [
    'stock_movements' => 'การเคลื่อนไหวสต็อก',
    'stock_movement' => 'การเคลื่อนไหวสต็อก',

    'table' => [
        'ingredient_name' => 'วัตถุดิบ',
        'unit_symbol' => 'สัญลักษณ์หน่วย',
        'type' => 'ประเภท',
        'quantity' => 'จำนวน',
        'unit_cost' => 'ต้นทุนต่อหน่วย',
        'total_cost' => 'ต้นทุนรวม',
        'currency' => 'สกุลเงิน',
        'currency_rate' => 'อัตราแลกเปลี่ยน',
        'cost_snapshot_source' => 'แหล่งสแนปช็อตต้นทุน',
        'source_id' => 'รหัสแหล่งที่มา',
        'source_type' => 'ประเภทแหล่งที่มา',
        'source_name' => 'ชื่อแหล่งที่มา',
        'note' => 'หมายเหตุ',
    ],

    'form' => [
        'cards' => [
            'stock_movement_information' => 'ข้อมูลการเคลื่อนไหวสต็อก',
        ],
    ],

    'filters' => [
        'type' => 'ประเภท',
        'ingredient' => 'วัตถุดิบ',
    ],

    'actions' => [
        'correct' => 'แก้ไขการเคลื่อนไหว',
    ],

    'correction' => [
        'title' => 'แก้ไขการเคลื่อนไหวสต็อก',
        'description' => 'การดำเนินการนี้สร้างการกลับรายการที่ล็อกแล้วและการเคลื่อนไหวทดแทน การเคลื่อนไหวเดิมไม่เปลี่ยนแปลง',
        'replacement_quantity' => 'จำนวนทดแทน',
        'reason' => 'เหตุผลในการแก้ไข',
    ],

    'show' => [
        'details' => 'รายละเอียดการเคลื่อนไหวสต็อก',
        'audit' => 'แหล่งที่มาและการตรวจสอบ',
        'branch' => 'สาขา',
        'ingredient' => 'วัตถุดิบ',
        'type' => 'ประเภท',
        'quantity' => 'จำนวน',
        'unit_cost' => 'ต้นทุนต่อหน่วย',
        'total_cost' => 'ต้นทุนรวม',
        'currency' => 'สกุลเงิน',
        'currency_rate' => 'อัตราแลกเปลี่ยน',
        'note' => 'หมายเหตุ',
        'source_id' => 'รหัสแหล่งที่มา',
        'source_type' => 'ประเภทแหล่งที่มา',
        'source_name' => 'ชื่อแหล่งที่มา',
        'created_at' => 'สร้างเมื่อ',
        'updated_at' => 'อัปเดตเมื่อ',
        'occurred_at' => 'เกิดเมื่อ',
        'committed_at' => 'บันทึกเมื่อ',
        'idempotency_key' => 'คีย์ Idempotency',
        'accounting_status' => 'สถานะบัญชี',
        'original_movement' => 'การเคลื่อนไหวเดิม',
        'reversals' => 'การกลับรายการ',
        'related_movements' => 'การเคลื่อนไหวที่เกี่ยวข้อง',
        'reversal' => 'การเคลื่อนไหวกลับรายการ',
    ],
];