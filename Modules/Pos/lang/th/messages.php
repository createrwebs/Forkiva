<?php

return [
    'closing_review_changed' => 'ยอดรวมของกะหรือกฎการปิดกะเปลี่ยนหลังการตรวจสอบ กรุณารีเฟรชข้อมูลและตรวจนับเงินสดอีกครั้ง',
    'counted_cash_precision' => 'เงินสดที่นับได้ต้องมีทศนิยมไม่เกิน :precision ตำแหน่งสำหรับสกุลเงินนี้',
    'cash_movement_idempotency_conflict' => 'คีย์คำขอนี้ถูกใช้กับรายละเอียดการเคลื่อนไหวเงินสดที่ต่างออกไปแล้ว',
    'session_already_opened' => 'เครื่อง POS นี้มีกะที่เปิดอยู่แล้ว กรุณาปิดก่อนเปิดกะใหม่',
    'session_closed' => 'กะ POS นี้ปิดแล้ว',
    'session_close_not_responsible' => 'เฉพาะแคชเชียร์ผู้รับผิดชอบหรือผู้มีสิทธิ์ปิดกะของพนักงานอื่นเท่านั้นที่ปิดกะนี้ได้',
    'closing_reason_required' => 'ระบุเหตุผลที่ปิดกะของพนักงานอื่น (3–1000 ตัวอักษร)',
    'variance_reason_required' => 'อธิบายเงินสดขาดหรือเกิน (3–1000 ตัวอักษร)',
    'success_opened_session' => 'เปิดกะ POS เรียบร้อยแล้ว เริ่มรับออเดอร์และชำระเงินได้ทันที',
    'success_closed_session' => 'ปิดกะ POS เรียบร้อยแล้ว บันทึกยอดขายและธุรกรรมทั้งหมดแล้ว',
    'amount_must_be_positive' => 'จำนวนเงินต้องมากกว่าศูนย์',
    'invalid_payment_method' => 'แต่ละช่องทางชำระเงินใช้ได้ครั้งเดียว',
    'no_active_session' => 'ไม่สามารถทำ :action ได้ เนื่องจากไม่มีกะ POS ที่เปิดอยู่',
    'cash_payment' => 'การชำระด้วยเงินสด',
    'cash_over_short_note_over' => 'เกิน: นับเงินสดได้มากกว่าที่คาดไว้',
    'cash_over_short_note_short' => 'ขาด: นับเงินสดได้น้อยกว่าที่คาดไว้',
    'cannot_close_session_cash_difference' => 'ปิดกะไม่ได้: เงินสดที่นับได้ (:declared) เทียบกับที่คาดไว้ (:expected) ผลต่าง :difference เกินเกณฑ์ :threshold',
    'menu_is_not_active' => 'ยังไม่มีเมนูที่เปิดใช้งาน',
];
