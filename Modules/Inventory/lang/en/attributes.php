<?php

return [
    'suppliers' => [
        'name' => 'Name',
        'phone' => 'Phone',
        'email' => 'Email',
        'address' => 'Address',
        'vat_tin' => 'Tax ID',
        'tax_branch_code' => 'Tax Branch Code',
        'branch_id' => 'Branch',
    ],

    'units' => [
        'name' => 'Name',
        'symbol' => 'Symbol',
        'type' => 'Type',
    ],

    'ingredients' => [
        'name' => 'Name',
        'branch_id' => 'Branch',
        'unit_id' => 'Unit',
        'cost_per_unit' => 'Cost Per Unit',
        'alert_quantity' => 'Alert Quantity',
        'is_returnable' => 'Returnable',
    ],

    'stock_movements' => [
        'branch_id' => 'Branch',
        'ingredient_id' => 'Ingredient',
        'supplier_id' => 'Supplier',
        'type' => 'Type',
        'quantity' => 'Quantity',
        'note' => 'Note',
        'reason' => 'Reason',
    ],

    'purchases' => [
        'branch_id' => 'Branch',
        'supplier_id' => 'Supplier',
        'notes' => 'Notes',
        'expected_at' => 'Expected Date',
        'discount' => 'Discount',
        'tax' => 'Tax',
        'items' => 'Items',
        'items.*' => 'Item',
        'items.*.ingredient_id' => 'Ingredient',
        'items.*.quantity' => 'Quantity',
        'items.*.received_quantity' => 'Received Quantity',
        'items.*.unit_cost' => 'Unit Cost',
        'items.*.id' => 'ID',
        'reference' => 'Reference No',
    ],

    'inventory_counts' => [
        'branch_id' => 'Branch',
        'count_date' => 'Count Date',
        'notes' => 'Notes',
        'reason' => 'Cancellation Reason',
        'items' => 'Count Items',
        'items.*.ingredient_id' => 'Ingredient',
        'items.*.counted_quantity' => 'Counted Quantity',
    ],
    'stock_transfers' => [
        'source_branch_id' => 'Source Branch',
        'destination_branch_id' => 'Destination Branch',
        'expected_at' => 'Expected Arrival',
        'currency' => 'Currency',
        'notes' => 'Notes',
        'reason' => 'Reason',
        'items' => 'Transfer Items',
        'items.*.source_ingredient_id' => 'Source Ingredient',
        'items.*.destination_ingredient_id' => 'Destination Ingredient',
        'items.*.quantity' => 'Quantity',
    ],
    'stock_transfer_receipts' => [
        'idempotency_key' => 'Receipt Request ID',
        'notes' => 'Notes',
        'items' => 'Receipt Items',
        'items.*.stock_transfer_item_id' => 'Transfer Item',
        'items.*.quantity' => 'Received Quantity',
    ],
];
