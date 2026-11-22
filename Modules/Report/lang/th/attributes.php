<?php

return [
    'sales_vat' => [
        'issued_at' => 'วันที่ออกใบกำกับภาษี',
        'invoice_number' => 'เลขที่ใบกำกับภาษี',
        'invoice_kind' => 'ชนิดเอกสาร',
        'buyer_name' => 'ชื่อผู้ซื้อ',
        'buyer_vat_tin' => 'เลขประจำตัวผู้เสียภาษีผู้ซื้อ',
        'buyer_branch_code' => 'รหัสสาขาผู้ซื้อ',
        'subtotal' => 'มูลค่าก่อนภาษี',
        'tax_total' => 'ภาษีมูลค่าเพิ่ม',
        'total' => 'มูลค่ารวมทั้งสิ้น',
    ],

    'purchase_vat' => [
        'purchase_date' => 'วันที่ซื้อ',
        'reference_number' => 'เลขที่ใบซื้อ',
        'supplier_name' => 'ชื่อผู้ขาย',
        'supplier_vat_tin' => 'เลขประจำตัวผู้เสียภาษีผู้ขาย',
        'supplier_branch_code' => 'รหัสสาขาผู้ขาย',
        'sub_total' => 'มูลค่าก่อนภาษี',
        'tax' => 'ภาษีมูลค่าเพิ่ม',
        'total' => 'มูลค่ารวมทั้งสิ้น',
    ],
];
