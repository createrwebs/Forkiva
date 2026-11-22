<?php

return [
    'notification_workflows' => [
        'enabled' => 'เปิดใช้งาน',
        'channels' => 'ช่องทาง',
        'recipient' => 'ผู้รับ',
    ],
    'notifications' => [
        'search' => 'ค้นหา',
        'status' => 'สถานะ',
        'per_page' => 'ต่อหน้า',
    ],
    'notification_templates' => [
        'name' => 'ชื่อ',
        'description' => 'คำอธิบาย',
    ],
    'notification_template_messages' => [
        'messages' => 'ข้อความ',
        'subject' => 'หัวข้อ',
        'content' => 'เนื้อหา',
        'content_type' => 'ประเภทเนื้อหา',
    ],
    'notification_push_tokens' => [
        'provider' => 'ผู้ให้บริการ',
        'token' => 'โทเคน',
        'device_id' => 'รหัสอุปกรณ์',
        'device_name' => 'ชื่ออุปกรณ์',
        'platform' => 'แพลตฟอร์ม',
        'client' => 'ไคลเอนต์',
    ],
    'settings' => [
        'enabled' => 'เปิดใช้งาน',
        'provider' => 'ผู้ให้บริการ',
        'mailer' => 'Mailer',
        'from_address' => 'ที่อยู่ผู้ส่ง',
        'from_name' => 'ชื่อผู้ส่ง',
        'host' => 'โฮสต์',
        'port' => 'พอร์ต',
        'username' => 'ชื่อผู้ใช้',
        'password' => 'รหัสผ่าน',
        'encryption' => 'การเข้ารหัส',
        'credentials' => 'ข้อมูลรับรอง',
        'app_id' => 'App ID',
        'rest_api_key' => 'REST API Key',
        'smtp' => [
            'host' => 'SMTP Host',
            'port' => 'SMTP Port',
            'username' => 'SMTP Username',
            'password' => 'SMTP Password',
            'encryption' => 'SMTP Encryption',
        ],
        'fcm' => [
            'credentials' => 'Firebase Credentials',
        ],
    ],
];