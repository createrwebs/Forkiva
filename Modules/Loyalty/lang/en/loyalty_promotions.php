<?php

return [
    'loyalty_promotions' => 'Loyalty Promotions',
    'loyalty_promotion' => 'Loyalty Promotion',

    'table' => [
        'name' => 'Name',
        'description' => 'Description',
        'type' => 'Type',
        'loyalty_program' => 'Program',
        'value' => 'Value',
        'multiplier' => 'Multiplier',
        'bonus_points' => 'Bonus Points',
        'usage_limit' => 'Usage Limit',
        'per_customer_limit' => 'Per Customer Limit',
        'total_used' => 'Used',
        'total_customers' => 'Customers',
        'conditions' => 'Conditions',
        'meta' => 'Meta',
        'starts_at' => 'Starts At',
        'ends_at' => 'Ends At',
        'is_active' => 'Status',
    ],

    'filters' => [
        'type' => 'Type',
        'loyalty_program' => 'Program',
    ],

    'form' => [
        'cards' => [
            'loyalty_promotion_information' => 'Loyalty Promotion Information',
            'configuration' => 'Configuration',
            'conditions' => 'Conditions',
            'usage_rules' => 'Usage Rules',
            'multiplier_and_bonus_points' => 'Multiplier & Bonus Points',
        ],
        'alert_note_for_multiplier_and_bonus_points' => 'Please select a promotion type to view its Multiplier & Bonus Points.',
    ],

];
