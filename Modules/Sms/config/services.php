<?php

use Modules\Sms\Enums\SmsProvider;

return [
    [
        'key' => 'notifications',
        'label' => 'notification::services.categories.notifications.title',
        'description' => 'notification::services.categories.notifications.description',
        'icon' => 'tabler-bell',
        'order' => 1,
        'services' => [
            [
                'key' => 'notifications.sms',
                'name' => 'sms::services.items.sms.name',
                'description' => 'sms::services.items.sms.description',
                'order' => 4,
                'icon' => 'tabler-message',
                'color' => '#16a34a',
                'settings' => [
                    'enabled' => [
                        'type' => 'boolean',
                        'default' => false,
                        'validation' => 'required|boolean',
                    ],
                    'provider' => [
                        'type' => 'dropdown',
                        'options' => SmsProvider::class,
                        'validation' => 'required|in:options',
                        'conditional_fields' => [
                            'log' => [
                                'channel' => [
                                    'type' => 'string',
                                    'validation' => 'nullable|string|max:100',
                                ],
                            ],
                            'jawal_sms' => [
                                'username' => [
                                    'type' => 'string',
                                    'validation' => 'required|string|max:255',
                                ],
                                'password' => [
                                    'type' => 'string',
                                    'sensitive' => true,
                                    'validation' => 'required|string|max:255',
                                ],
                            ],
                            'taqnyat' => [
                                'authorization' => [
                                    'type' => 'string',
                                    'sensitive' => true,
                                    'validation' => 'required|string|max:1000',
                                ],
                            ],
                            'nexmo' => [
                                'api_key' => [
                                    'type' => 'string',
                                    'sensitive' => true,
                                    'validation' => 'required|string|max:255',
                                ],
                                'api_secret' => [
                                    'type' => 'string',
                                    'sensitive' => true,
                                    'validation' => 'required|string|max:255',
                                ],
                            ],
                            'twilio' => [
                                'sid' => [
                                    'type' => 'string',
                                    'sensitive' => true,
                                    'validation' => 'required|string|max:255',
                                ],
                                'token' => [
                                    'type' => 'string',
                                    'sensitive' => true,
                                    'validation' => 'required|string|max:255',
                                ],
                            ],
                            'morasa' => [
                                'username' => [
                                    'type' => 'string',
                                    'validation' => 'required|string|max:255',
                                ],
                                'api_key' => [
                                    'type' => 'string',
                                    'sensitive' => true,
                                    'validation' => 'required|string|max:255',
                                ],
                            ],
                            'msegat' => [
                                'username' => [
                                    'type' => 'string',
                                    'validation' => 'required|string|max:255',
                                ],
                                'api_key' => [
                                    'type' => 'string',
                                    'sensitive' => true,
                                    'validation' => 'required|string|max:255',
                                ],
                            ],
                            'kobikom' => [
                                'api_key' => [
                                    'type' => 'string',
                                    'sensitive' => true,
                                    'validation' => 'required|string|max:255',
                                ],
                            ],
                            'unifonic' => [
                                'sid' => [
                                    'type' => 'string',
                                    'sensitive' => true,
                                    'validation' => 'required|string|max:255',
                                ],
                                'message_type' => [
                                    'type' => 'integer',
                                    'default' => 3,
                                    'validation' => 'required|integer',
                                ],
                            ],
                            'jor_mall' => [
                                'acc_name' => [
                                    'type' => 'string',
                                    'validation' => 'required|string|max:255',
                                ],
                                'password' => [
                                    'type' => 'string',
                                    'sensitive' => true,
                                    'validation' => 'required|string|max:255',
                                ],
                            ],
                        ],
                    ],
                    'sender_name' => [
                        'type' => 'string',
                        'validation' => 'nullable|string|max:255',
                    ],
                ],
            ],
        ],
    ],
];
