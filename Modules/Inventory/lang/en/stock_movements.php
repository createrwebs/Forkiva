<?php

return [
    'stock_movements' => 'Stock Movements',
    'stock_movement' => 'Stock Movement',

    'table' => [
        'ingredient_name' => 'Ingredient',
        'unit_symbol' => 'Unit Symbol',
        'type' => 'Type',
        'quantity' => 'Quantity',
        'unit_cost' => 'Unit Cost',
        'total_cost' => 'Total Cost',
        'currency' => 'Currency',
        'currency_rate' => 'Currency Rate',
        'cost_snapshot_source' => 'Cost Snapshot Source',
        'source_id' => 'Source ID',
        'source_type' => 'Source Type',
        'source_name' => 'Source Name',
        'note' => 'Note',
    ],

    'form' => [
        'cards' => [
            'stock_movement_information' => 'Stock Movement Information',
        ],
    ],

    'filters' => [
        'type' => 'Type',
        'ingredient' => 'Ingredient',
    ],

    'actions' => [
        'correct' => 'Correct Movement',
    ],

    'correction' => [
        'title' => 'Correct Stock Movement',
        'description' => 'This creates an immutable reversal and a replacement movement. The original movement is not changed.',
        'replacement_quantity' => 'Replacement Quantity',
        'reason' => 'Correction Reason',
    ],

    'show' => [
        'details' => 'Stock Movement Details',
        'audit' => 'Source and Audit',
        'branch' => 'Branch',
        'ingredient' => 'Ingredient',
        'type' => 'Type',
        'quantity' => 'Quantity',
        'unit_cost' => 'Unit Cost',
        'total_cost' => 'Total Cost',
        'currency' => 'Currency',
        'currency_rate' => 'Currency Rate',
        'note' => 'Note',
        'source_id' => 'Source ID',
        'source_type' => 'Source Type',
        'source_name' => 'Source Name',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
        'occurred_at' => 'Occurred at',
        'committed_at' => 'Committed at',
        'idempotency_key' => 'Idempotency Key',
        'accounting_status' => 'Accounting Status',
        'original_movement' => 'Original Movement',
        'reversals' => 'Reversals',
        'related_movements' => 'Related Movements',
        'reversal' => 'Reversal Movement',
    ],
];
