<?php

return [
    'unit_types' => [
        'mass' => 'Mass',
        'volume' => 'Volume',
        'count' => 'Count',
        'custom' => 'Custom',
    ],

    'stock_movement_types' => [
        'in' => 'In',
        'out' => 'Out',
        'spoil' => 'Spoil',
        'adjust_add' => 'Adjust Add',
        'adjust_subtract' => 'Adjust Subtract',
        'transfer_in' => 'Transfer In',
        'transfer_out' => 'Transfer Out',
        'return_supplier' => 'Return Supplier',
        'sample' => 'Sample',
        'waste' => 'Waste',
        'staff_meal' => 'Staff Meal',
    ],

    'purchase_statuses' => [
        'draft' => 'Draft',
        'pending' => 'Pending',
        'partially_received' => 'Partially Received',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ],

    'inventory_count_statuses' => [
        'draft' => 'Draft',
        'approved' => 'Approved',
        'cancelled' => 'Cancelled',
    ],

    'stock_transfer_statuses' => [
        'draft' => 'Draft',
        'approved' => 'Approved',
        'dispatched' => 'Dispatched',
        'partially_received' => 'Partially Received',
        'received' => 'Received',
        'closed_with_discrepancy' => 'Closed With Discrepancy',
        'cancelled' => 'Cancelled',
        'voided' => 'Voided',
    ],

    'inventory_stock_sync_operations' => [
        'deduct' => 'Deduct Stock',
        'restore' => 'Restore Stock',
    ],

    'inventory_stock_sync_statuses' => [
        'pending_stock' => 'Pending Stock',
        'processing_stock' => 'Processing Stock',
        'pending_accounting' => 'Pending Accounting',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],
];
