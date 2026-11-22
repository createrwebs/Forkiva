<?php

return [
    'categories' => [
        'notifications' => [
            'title' => 'การแจ้งเตือน',
            'description' => 'บริการการแจ้งเตือน เช่น อีเมล, SMS, ในแอป, push notification, WhatsApp และการเชื่อมต่อทางการตลาด',
        ],
    ],
    'items' => [
        'email' => [
            'name' => 'อีเมล',
            'description' => 'ส่งอีเมลผ่านผู้ให้บริการอีเมลที่ตั้งค่า',
        ],
        'in_app' => [
            'name' => 'ในแอป',
            'description' => 'การแจ้งเตือนในฐานข้อมูลภายในโดยไม่ต้องผู้ให้บริการภายนอก',
        ],
        'push_notification' => [
            'name' => 'Push Notification',
            'description' => 'ส่ง push notification ผ่าน Firebase Cloud Messaging',
        ],
    ],
];