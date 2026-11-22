<?php

return [
    'print_agent' => 'ตัวแทนการพิมพ์',
    'print_agents' => 'ตัวแทนการพิมพ์',

    'table' => [
        'name' => 'ชื่อ',
        'api_key' => 'API Key',
        'host' => 'โฮสต์',
        'port' => 'พอร์ต',
    ],

    'form' => [
        'cards' => [
            'print_agent_information' => 'ข้อมูลตัวแทนการพิมพ์',
            'qintrix_integration' => 'การเชื่อมต่อ Qintrix',
        ],
    ],
    'validation' => [
        'host_without_scheme_or_path' => 'ฟิลด์โฮสต์ต้องไม่รวม scheme หรือ path',
        'host_without_port' => 'ฟิลด์โฮสต์ต้องไม่รวมพอร์ต',
        'host_valid_hostname_or_ip' => 'ฟิลด์โฮสต์ต้องเป็นชื่อโฮสต์หรือที่อยู่ IP ที่ถูกต้องโดยไม่มี scheme หรือพอร์ต',
    ],
];