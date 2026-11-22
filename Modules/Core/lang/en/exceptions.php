<?php

return [
    'unprocessable' => 'Some details are incorrect. Please check your inputs.',
    'model_not_found' => 'Record not found.',
    'idempotency_already_processing' => 'The idempotent operation is already processing.',
    'idempotency_payload_mismatch' => 'The idempotency key was reused with a different payload.',
    'audit_action_reason_required' => 'Sensitive audit actions require an action and reason.',
    'immutable_audit_update_forbidden' => 'Immutable audit events cannot be updated.',
    'immutable_audit_delete_forbidden' => 'Immutable audit events cannot be deleted.',
];
