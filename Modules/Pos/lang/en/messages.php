<?php

return [
    'closing_review_changed' => 'Session totals or closing rules changed after your review. Refresh the data and review the cash count again.',
    'counted_cash_precision' => 'Counted cash must use no more than :precision decimal places for this currency.',
    'cash_movement_idempotency_conflict' => 'This request key was already used with different cash-movement details.',
    'session_already_opened' => 'This register already has an open session. Close it before opening a new one.',
    'session_closed' => 'This POS session is already closed.',
    'session_close_not_responsible' => 'Only the responsible cashier or someone authorized to close other employees’ sessions can close this session.',
    'closing_reason_required' => 'Explain why you are closing another employee’s session (3–1000 characters).',
    'variance_reason_required' => 'Explain the cash shortage or surplus (3–1000 characters).',
    'success_opened_session' => 'POS session opened successfully. You can now start processing orders and payments.',
    'success_closed_session' => 'POS session closed successfully. All sales and transactions have been recorded.',
    'amount_must_be_positive' => 'Amount must be positive.',
    'invalid_payment_method' => 'Each payment method can only be used once.',
    'no_active_session' => 'You cannot perform :action because there is no active POS session.',
    'cash_payment' => 'a cash payment',
    'cash_over_short_note_over' => 'Over: declared more cash than expected',
    'cash_over_short_note_short' => 'Short: declared less cash than expected',
    'cannot_close_session_cash_difference' => 'Cannot close session: Declared cash (:declared) vs Expected (:expected). Difference: :difference exceeds threshold: :threshold.',
    'menu_is_not_active' => 'You no have any menu active',
];
