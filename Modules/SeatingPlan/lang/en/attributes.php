<?php

return [
    'floors' => [
        'name' => 'Floor',
        'branch_id' => 'Branch',
        'is_active' => 'Active',
    ],
    'zones' => [
        'name' => 'Name',
        'branch_id' => 'Branch',
        'floor_id' => 'Floor',
        'is_active' => 'Active',
    ],
    'tables' => [
        'name' => 'Name',
        'branch_id' => 'Branch',
        'floor_id' => 'Floor',
        'zone_id' => 'Zone',
        'capacity' => 'Capacity',
        'shape' => 'Shape',
        'is_active' => 'Active',
        'waiter_id' => 'Waiter',
    ],

    'table_merges' => [
        'table_ids' => 'Tables',
        'table_ids.*' => 'Table',
        'type' => 'Merge Type',
    ],

    'table_qr' => [
        'product_id' => 'Product',
        'qty' => 'Quantity',
        'options' => 'Options',
        'idempotency_key' => 'Submit key',
        'guest_count' => 'Guest count',
        'customer_note' => 'Customer note',
        'discount_code' => 'Discount code',
    ],
];
