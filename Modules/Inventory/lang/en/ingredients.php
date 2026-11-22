<?php

return [
    'ingredients' => 'Ingredients',
    'ingredient' => 'Ingredient',

    'table' => [
        'name' => 'Name',
        'unit' => 'Unit',
        'unit_symbol' => 'Unit Symbol',
        'currency' => 'Currency',
        'current_stock' => 'Current Stock',
        'cost_per_unit' => 'Cost Per Unit',
        'alert_quantity' => 'Alert Quantity',
        'is_returnable' => 'Returnable',
    ],

    'import' => [
        'exists' => 'This record already exists.',
        'missing' => 'No matching record was found.',
        'missing_id' => 'No record with this ID was found.',
    ],

    'form' => [
        'cards' => [
            'ingredient_information' => 'Ingredient Information',
        ],
    ],

    'filters' => [
        'unit' => 'Unit',
    ],

    'tooltips' => [
        'current_stock_is_above_the_alert_threshold' => 'Current stock is above the alert threshold.',
        'current_stock_has_fallen_below_the_alert_threshold' => 'Current stock has fallen below the alert threshold.',
    ],
];
