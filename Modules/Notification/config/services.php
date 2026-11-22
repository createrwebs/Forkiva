<?php

use Modules\Service\Enums\PushNotificationProvider;
use Modules\Support\Enums\MailEncryptionProtocol;
use Modules\Support\Enums\Mailer;

return [
    [
        'key' => 'notifications',
        'label' => 'notification::services.categories.notifications.title',
        'description' => 'notification::services.categories.notifications.description',
        'icon' => 'tabler-bell',
        'order' => 1,
        'services' => [
            [
                'key' => 'notifications.email',
                'name' => 'notification::services.items.email.name',
                'description' => 'notification::services.items.email.description',
                'order' => 1,
                'icon' => 'tabler-mail-fast',
                'color' => '#2563eb',
                'settings' => [
                    'enabled' => [
                        'type' => 'boolean',
                        'default' => false,
                        'validation' => 'required|boolean',
                    ],
                    'from_address' => [
                        'type' => 'string',
                        'validation' => 'required|string|max:100|email',
                    ],
                    'from_name' => [
                        'type' => 'string',
                        'validation' => 'required|string|max:30',
                    ],
                    'mailer' => [
                        'type' => 'dropdown',
                        'options' => Mailer::class,
                        'validation' => 'required|in:smtp,log,array',
                        'conditional_fields' => [
                            'smtp' => [
                                'host' => [
                                    'type' => 'string',
                                    'validation' => 'required_if:mailer,smtp|string',
                                ],
                                'port' => [
                                    'type' => 'integer',
                                    'validation' => 'required_if:mailer,smtp|integer|between:1,65535',
                                ],
                                'username' => [
                                    'type' => 'string',
                                    'validation' => 'nullable|string',
                                ],
                                'password' => [
                                    'type' => 'string',
                                    'sensitive' => true,
                                    'validation' => 'nullable|string',
                                ],
                                'encryption' => [
                                    'type' => 'dropdown',
                                    'options' => MailEncryptionProtocol::class,
                                    'validation' => 'required_if:mailer,smtp|in:options',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'notifications.in_app',
                'name' => 'notification::services.items.in_app.name',
                'description' => 'notification::services.items.in_app.description',
                'order' => 2,
                'icon' => 'tabler-bell-ringing',
                'color' => '#7c3aed',
                'settings' => [
                    'enabled' => [
                        'type' => 'boolean',
                        'default' => false,
                        'validation' => 'required|boolean',
                    ],
                ],
            ],
            [
                'key' => 'notifications.push_notification',
                'name' => 'notification::services.items.push_notification.name',
                'description' => 'notification::services.items.push_notification.description',
                'order' => 3,
                'icon' => 'tabler-device-mobile-message',
                'color' => '#f97316',
                'settings' => [
                    'enabled' => [
                        'type' => 'boolean',
                        'default' => false,
                        'validation' => 'required|boolean',
                    ],
                    'provider' => [
                        'type' => 'dropdown',
                        'options' => PushNotificationProvider::class,
                        'validation' => 'required|in:options',
                        'conditional_fields' => [
                            'fcm' => [
                                'credentials' => [
                                    'type' => 'textarea',
                                    'sensitive' => true,
                                    'validation' => 'required_if:provider,fcm|string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
