<?php

return [
    'categories' => [
        'notifications' => [
            'title' => 'Notifications',
            'description' => 'Notifications services such as email, SMS, in-app, push notifications, WhatsApp, and marketing integrations.',
        ],
    ],
    'items' => [
        'email' => [
            'name' => 'Email',
            'description' => 'Send emails using configured email providers.',
        ],
        'in_app' => [
            'name' => 'In-app',
            'description' => 'Internal database notifications without external provider.',
        ],
        'push_notification' => [
            'name' => 'Push Notification',
            'description' => 'Send push notifications using Firebase Cloud Messaging.',
        ],
    ],
];
