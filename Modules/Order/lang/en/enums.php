<?php

return [
    'order_types' => [
        'dine_in' => 'Dine-In',
        'delivery' => 'Delivery',
        'pick_up' => 'Pick-up',
        'takeaway' => 'Takeaway',
        'drive_thru' => 'Drive-Thru',
        'pre_order' => 'Pre-Order',
        'catering' => 'Catering',
    ],

    'order_statuses' => [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'served' => 'Served',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
        'merged' => 'Merged',
    ],

    'order_payment_statuses' => [
        'unpaid' => 'Unpaid',
        'paid' => 'Paid',
        'partially_paid' => 'Partially Paid',
    ],

    'reason_types' => [
        'cancellation' => 'Cancellation',
        'refund' => 'Refund',
    ],

    'order_product_statuses' => [
        'pending' => 'Pending',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'served' => 'Served',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ],

    'discount_types' => [
        'discount' => 'Discount',
        'voucher' => 'Voucher',
    ],

    'order_charge_codes' => [
        'delivery_fee' => 'Delivery Fee',
        'small_order_surcharge' => 'Small Order Surcharge',
        'peak_time_surcharge' => 'Peak Time Surcharge',
        'tip' => 'Tip',
        'service_charge' => 'Service Charge',
    ],

    'order_sources' => [
        'table_qr' => 'Table QR',
    ],
];
