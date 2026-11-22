<?php

return [
    'scopes' => [
        'branch' => 'Branch',
        'global' => 'Global',
    ],
    'statuses' => [
        'inactive' => 'Inactive',
        'active' => 'Active',
        'used' => 'Used',
        'expired' => 'Expired',
        'disabled' => 'Disabled',
    ],
    'breakage_batch_statuses' => [
        'draft' => 'Draft',
        'reviewed' => 'Reviewed',
        'posted' => 'Posted',
        'reversed' => 'Reversed',
    ],
    'transaction_types' => [
        'purchase' => 'Purchase',
        'redeem' => 'Redeem',
        'refund' => 'Refund',
        'recharge' => 'Recharge',
        'adjustment' => 'Adjustment',
        'expire' => 'Expired',
        'cash_out' => 'Cash-out',
        'issue_void' => 'Issue void',
        'adjustment_decrease' => 'Adjustment decrease',
        'breakage' => 'Breakage',
        'reversal' => 'Reversal',
    ],
    'funding_types' => [
        'paid' => 'Paid',
        'promotional' => 'Promotional',
    ],
];
