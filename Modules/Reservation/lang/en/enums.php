<?php

return [
    'reservation_statuses' => [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'arrived' => 'Arrived',
        'seated' => 'Seated',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
        'no_show' => 'No Show',
        'expired' => 'Expired',
    ],
    'reservation_types' => [
        'standard' => 'Standard',
        'walk_in' => 'Walk-In',
        'large_party' => 'Large Party',
        'private_room' => 'Private Room',
        'event' => 'Event',
        'vip' => 'VIP',
    ],
    'table_assignment_modes' => [
        'assign_later' => 'Assign Later',
        'assign_now' => 'Assign Now',
        'auto_assign' => 'Auto Assign',
    ],
    'table_assignment_statuses' => [
        'unassigned' => 'Unassigned',
        'assigned' => 'Assigned',
        'partially_assigned' => 'Partially Assigned',
        'not_required' => 'Not Required',
    ],
    'reservation_payment_statuses' => [
        'not_required' => 'Not Required',
        'unpaid' => 'Unpaid',
        'partially_paid' => 'Partially Paid',
        'paid' => 'Paid',
        'refunded' => 'Refunded',
        'failed' => 'Failed',
    ],
    'reservation_reminder_statuses' => [
        'pending' => 'Pending',
        'sent' => 'Sent',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ],
    'reservation_waitlist_statuses' => [
        'waiting' => 'Waiting',
        'notified' => 'Notified',
        'converted' => 'Converted',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
    ],
    'deposit_transaction_types' => [
        'opening' => 'Opening balance',
        'collection' => 'Collection',
        'application' => 'Order application',
        'refund' => 'Refund',
        'forfeiture' => 'Forfeiture',
        'reversal' => 'Reversal',
    ],
];
