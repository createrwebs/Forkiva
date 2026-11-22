# Reservation module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns booking/waitlist lifecycle, availability/time windows, table assignments/blocks, reminders and
deposit ledger. SeatingPlan owns live table state; Order owns the linked sale; Invoice allocates
applied deposits; Accounting owns liability/posting. Reservation completion does not imply an active
order is finished.

## Important source map

- `app/Services/ReservationStatus/ReservationStatusService.php` — locked transitions and table
  synchronization.
- `app/Enums/ReservationStatus.php` — allowed transitions and target permissions.
- `app/Services/ReservationAvailability` — time-window availability.
- `app/Services/ReservationTableAssignment` — assignment coordination.
- `app/Services/ReservationDeposit/ReservationDepositService.php` — exact deposit
  collection/application/refund/reversal.
- `app/Jobs/SendWaitlistNotificationJob.php` — queued waitlist notification failure recovery.
- `app/Providers/ReservationServiceProvider.php` — reminder/expiry/no-show scheduling.
- `resources/assets/js/utils/depositActions.ts` — UI action eligibility.

## Local invariants and sensitive flows

- Pending can confirm/cancel/reject/expire; confirmed can arrive/seat/cancel/no-show; arrived can
  seat/cancel/no-show; seated can complete. Other terminal states do not transition. Enforce the
  exact target permission and cancellation policy/reason through the status service.
- Availability uses time windows, branch-wide/table blocks and current assignments. Future
  reservations must not overwrite occupied tables. Removing/completing a reservation must keep a
  table occupied when an active order remains.
- Deposit collection, application, refund and reversal are separate immutable ledger transactions.
  Preserve exact source/base balances, frozen rates, idempotency fingerprints and reversal links; a
  reversal cannot itself be reversed.
- Applying a deposit requires an eligible open, uninvoiced order in the same branch/currency, with
  compatible reservation ownership and remaining due. Subsequent invoicing allocates the deposit;
  reversal must refresh affected order/invoice balances.
- Terminal booking state restricts collection. Refund permission/policy is separate from the
  presence of a positive balance.
- Waitlist conversion is terminal for that waitlist action; notification failure restores the
  supported retryable state instead of duplicating conversion. Preserve after-commit notification
  behavior and scheduler coordination.
- Browser deposit controls must reflect server-supported actions and cannot authorize them.

## Testing and validation

`tests/Feature/ReservationIntegrityTest.php` covers deposit immutability/replay, status permission,
table occupancy, blocks and queue recovery. Use `ReservationSchedulerRegistrationTest.php`,
`ReservationAnalyticsTest.php` and `resources/assets/js/utils/depositActions.test.ts` for their
boundaries. Monetary/posting changes also need Invoice/Accounting consumers.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[api-contract-change](../../.agents/skills/api-contract-change/SKILL.md),
[database-migration](../../.agents/skills/database-migration/SKILL.md) for the corresponding
specialized changes.
