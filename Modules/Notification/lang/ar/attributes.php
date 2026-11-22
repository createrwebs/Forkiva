<?php

return [
    'notification_workflows' => [
        'enabled' => 'مفعّل',
        'channels' => 'القنوات',
        'recipient' => 'المستلم',
    ],
    'notifications' => [
        'search' => 'البحث',
        'status' => 'الحالة',
        'per_page' => 'عدد العناصر',
    ],
    'notification_templates' => [
        'name' => 'الاسم',
        'description' => 'الوصف',
    ],
    'notification_template_messages' => [
        'messages' => 'الرسائل',
        'subject' => 'الموضوع',
        'content' => 'المحتوى',
        'content_type' => 'نوع المحتوى',
    ],
    'notification_push_tokens' => [
        'provider' => 'المزوّد',
        'token' => 'الرمز',
        'device_id' => 'معرّف الجهاز',
        'device_name' => 'اسم الجهاز',
        'platform' => 'النظام',
        'client' => 'الواجهة',
    ],
    'settings' => [
        'enabled' => 'مفعّل',
        'provider' => 'المزوّد',
        'mailer' => 'طريقة الإرسال',
        'from_address' => 'بريد المرسل',
        'from_name' => 'اسم المرسل',
        'host' => 'الخادم',
        'port' => 'المنفذ',
        'username' => 'اسم المستخدم',
        'password' => 'كلمة المرور',
        'encryption' => 'التشفير',
        'credentials' => 'بيانات الاعتماد',
        'app_id' => 'معرّف التطبيق',
        'rest_api_key' => 'مفتاح REST API',
        'smtp' => [
            'host' => 'خادم SMTP',
            'port' => 'منفذ SMTP',
            'username' => 'اسم مستخدم SMTP',
            'password' => 'كلمة مرور SMTP',
            'encryption' => 'تشفير SMTP',
        ],
        'fcm' => [
            'credentials' => 'بيانات اعتماد Firebase',
        ],
    ],
];
