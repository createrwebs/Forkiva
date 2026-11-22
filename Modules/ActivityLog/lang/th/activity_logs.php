<?php

return [
    'activity_logs' => 'บันทึกกิจกรรม',
    'activity_log' => 'บันทึกกิจกรรม',

    'table' => [
        'ip' => 'ที่อยู่ IP',
        'user' => 'ผู้ใช้',
        'log_name' => 'ชื่อบันทึก',
        'subject' => 'เรื่อง',
        'event' => 'เหตุการณ์',
        'logged_at' => 'บันทึกเมื่อ',
    ],

    'filters' => [
        'ip' => 'ที่อยู่ IP',
        'batch_uuid' => 'Batch Uuid',
        'causer_user' => 'ผู้ใช้',
        'event' => 'ประเภทเหตุการณ์',
        'log_name' => 'ชื่อบันทึก',
    ],

    'events' => [
        'created' => 'สร้าง',
        'updated' => 'อัปเดต',
        'approved' => 'อนุมัติ',
        'cancelled' => 'ยกเลิก',
        'voided' => 'เป็นโมฆะ',
        'exported_to_payroll' => 'ส่งออกไปเงินเดือน',
        'paid_from_payroll' => 'จ่ายจากเงินเดือน',
        'paid' => 'ชำระ',
        'deleted' => 'ลบ',
        'backup' => 'สำรองข้อมูล',
        'download' => 'ดาวน์โหลด',
        'restored' => 'กู้คืน',
    ],

    'show' => [
        'user' => 'ผู้ใช้',
        'subject' => 'เรื่อง',
        'subject_type' => 'ประเภทเรื่อง',
        'subject_id' => 'รหัสเรื่อง',
        'description' => 'คำอธิบาย',
        'log_name' => 'ชื่อบันทึก',
        'event' => 'เหตุการณ์',
        'logged_at' => 'บันทึกเมื่อ',
        'ip' => 'ที่อยู่ IP',
        'http_method' => 'HTTP Method',
        'agent' => 'Agent',
        'batch_uuid' => 'Batch Uuid',
        'old' => 'เก่า',
        'new' => 'ใหม่',
        'key' => 'คีย์',
        'value' => 'ค่า',
        'changes' => 'การเปลี่ยนแปลง',
        'field' => 'ฟิลด์',
        'properties' => 'คุณสมบัติ',
    ],
];