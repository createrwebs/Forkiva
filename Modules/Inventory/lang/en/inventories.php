<?php

return [
    'inventory' => 'Inventory',

    'analytics' => [
        'top_suppliers' => 'Top Suppliers by Purchase Amount',
        'ingredient_purchases' => 'Ingredient Purchases Summary',
        'stock_movements' => 'Stock Movement Summary',
        'wastage_and_spoilage' => 'Wastage & Spoilage',
        'purchase_status' => 'Purchase Order Status Summary',
        'purchase_amount' => 'Purchase Amount',
        'quantity' => 'Quantity',
        'no_data_available' => 'No data available',
        'from' => 'From',
        'to' => 'To',
        'branch' => 'Branch',
        'filters' => 'Filters',
        'low_stock_ingredients' => 'Low Stock Ingredients',
        'alert_quantity' => 'Alert Quantity',
        'purchased_quantity' => 'Purchased Quantity',
        'fast_moving_ingredients' => 'Fast Moving Ingredients',
        'most_wasted_ingredients' => 'Most Wasted Ingredients',
        'page' => [
            'title' => 'Inventory Analytics',
            'subtitle' => 'Stock health, purchasing, and movement at a glance',
        ],
        'date' => 'Date',
        'all_branches' => 'All branches',
        'assigned_branch' => 'Assigned branch',
        'last_updated' => 'Updated {time}',
        'previous_period' => 'vs previous period',
        'total' => 'Total',
        'reorder_level' => 'Reorder level',
        'top_count' => 'Top {count}',
        'periods' => [
            'last_7_days' => 'Last 7 days',
            'last_30_days' => 'Last 30 days',
            'last_90_days' => 'Last 90 days',
            'this_year' => 'This year',
            'custom' => 'Custom range',
        ],
        'actions' => [
            'refresh' => 'Refresh',
            'customize' => 'Customize Dashboard',
            'apply' => 'Apply range',
            'reset' => 'Reset layout',
            'done' => 'Done',
            'minimize' => 'Minimize widget',
            'expand' => 'Expand widget',
        ],
        'categories' => [
            'health' => 'Inventory health',
            'movement' => 'Stock movement',
            'purchasing' => 'Purchasing',
            'risk' => 'Stock risk',
        ],
        'customizer' => [
            'title' => 'Customize Inventory Analytics',
            'subtitle' => 'Choose, arrange, and minimize the widgets that matter to you.',
            'search' => 'Search widgets',
            'visible' => 'Visible',
            'hidden' => 'Hidden',
            'drag_hint' => 'Drag widgets to change their display order.',
            'empty' => 'No widgets match your search.',
            'drag_label' => 'Move {widget}. Drag it anywhere, or use the arrow, Home, and End keys.',
            'drop_hint' => 'Drop the widget in its new position.',
            'position' => '{widget} moved to column {column}, row {row}.',
        ],
        'empty' => [
            'title' => 'Your inventory dashboard is empty',
            'subtitle' => 'Choose the widgets you want to monitor.',
        ],
        'errors' => [
            'load' => 'Inventory analytics could not be loaded.',
            'load_filters' => 'Inventory analytics filters could not be loaded.',
        ],
        'widgets' => [
            'inventory_value' => [
                'title' => 'Inventory value',
                'description' => 'Current on-hand stock value.',
            ],
            'low_stock_items' => [
                'title' => 'Low stock items',
                'description' => 'Ingredients at or below reorder level.',
            ],
            'purchase_spend' => [
                'title' => 'Purchase spend',
                'description' => 'Purchase order value in the selected period.',
            ],
            'waste_rate' => [
                'title' => 'Waste rate',
                'description' => 'Waste and spoilage as a share of stock usage.',
            ],
            'fast_moving_ingredients' => [
                'title' => 'Fast moving ingredients',
                'description' => 'Top ingredients by issue and usage volume over time.',
            ],
            'stock_movement' => [
                'title' => 'Stock movement',
                'description' => 'Received, issued, adjusted, and transferred quantities.',
            ],
            'top_suppliers' => [
                'title' => 'Top suppliers',
                'description' => 'Suppliers ranked by purchase amount.',
            ],
            'low_stock_ingredients' => [
                'title' => 'Low stock ingredients',
                'description' => 'Items requiring replenishment attention.',
            ],
            'purchase_status' => [
                'title' => 'Purchase status',
                'description' => 'Purchase orders grouped by workflow status.',
            ],
            'most_wasted_ingredients' => [
                'title' => 'Most wasted ingredients',
                'description' => 'Ingredients ranked by waste and spoilage quantity.',
            ],
            'ingredient_purchases' => [
                'title' => 'Ingredient purchases',
                'description' => 'Purchased ingredients ranked by quantity.',
            ],
            'wastage_spoilage' => [
                'title' => 'Wastage & spoilage',
                'description' => 'Waste and spoilage movement mix.',
            ],
        ],
    ],
];
