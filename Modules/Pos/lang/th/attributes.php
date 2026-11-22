<?php

return [
    'pos_registers' => [
        'name' => 'ชื่อ',
        'branch_id' => 'สาขา',
        'code' => 'รหัส',
        'note' => 'หมายเหตุ',
        'is_active' => 'เปิดใช้งาน',
        'invoice_printer_id' => 'เครื่องพิมพ์ใบแจ้งหนี้',
        'bill_printer_id' => 'เครื่องพิมพ์บิล',
        'waiter_printer_id' => 'เครื่องพิมพ์พนักงานเสิร์ฟ',
        'delivery_printer_id' => 'เครื่องพิมพ์จัดส่ง',
    ],

    'pos_sessions' => [
        'branch_id' => 'สาขา',
        'pos_register_id' => 'เครื่อง POS',
        'opening_float' => 'เงินสดเปิดกะ',
        'notes' => 'หมายเหตุ',
        'declared_cash' => 'เงินสดที่นับได้',
        'closing_reason' => 'เหตุผลในการปิดกะของพนักงานอื่น',
        'variance_reason' => 'เหตุผลผลต่างเงินสด',
    ],

    'pos_cash_movements' => [
        'pos_register_id' => 'เครื่อง POS',
        'reference' => 'เลขอ้างอิง',
        'amount' => 'จำนวนเงิน',
        'notes' => 'หมายเหตุ',
        'reason' => 'เหตุผล',
        'direction' => 'ทิศทาง',
    ],
];
