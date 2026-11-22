<?php

return [
    'ingredients' => 'วัตถุดิบ',
    'ingredient' => 'วัตถุดิบ',

    'table' => [
        'name' => 'ชื่อ',
        'unit' => 'หน่วย',
        'unit_symbol' => 'สัญลักษณ์หน่วย',
        'currency' => 'สกุลเงิน',
        'current_stock' => 'สต็อกปัจจุบัน',
        'cost_per_unit' => 'ต้นทุนต่อหน่วย',
        'alert_quantity' => 'จำนวนแจ้งเตือน',
        'is_returnable' => 'คืนได้',
    ],

    'import' => [
        'exists' => 'มีรายการนี้อยู่แล้ว',
        'missing' => 'ไม่พบรายการที่ตรงกัน',
        'missing_id' => 'ไม่พบรายการที่มี ID นี้',
    ],

    'form' => [
        'cards' => [
            'ingredient_information' => 'ข้อมูลวัตถุดิบ',
        ],
    ],

    'filters' => [
        'unit' => 'หน่วย',
    ],

    'tooltips' => [
        'current_stock_is_above_the_alert_threshold' => 'สต็อกปัจจุบันสูงกว่าเกณฑ์แจ้งเตือน',
        'current_stock_has_fallen_below_the_alert_threshold' => 'สต็อกปัจจุบันต่ำกว่าเกณฑ์แจ้งเตือน',
    ],
];