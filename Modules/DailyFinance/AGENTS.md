# DailyFinance module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership

Owns the restaurant's daily finance interface, overview aggregation, module permissions, cash custody
handovers and branch day closing. Overview and source-record queries are read-only. The session drawer
reuses POS session details and
its authorized closing dialog. Cash handovers have their own custody records and permissions, and
never rewrite the closed POS snapshot or post accounting entries. Day closing saves an immutable
managerial snapshot and does not freeze source transactions. Manual electronic settlements reserve
source collections and retain bank-credit evidence without moving money or posting ledger entries.
Financial-account handover posting remains a roadmap item. See `docs/CASH_HANDOVER.md`,
`docs/DAY_CLOSING.md` and `docs/PAYMENT_SETTLEMENTS.md`.

POS remains the authority for cashier sessions and drawer movements. Payment owns receipts/refunds;
Order owns sales. Accounting owns financial accounts, transfers, journals, and bank reconciliation.
Do not create parallel source transactions or duplicate accounting recognition.

## Contracts and authorization

- `GET /api/v1/daily-finance` owns the overview. `GET /api/v1/daily-finance/entries` supplies
  paginated source records for the same branch and day, enforcing the corresponding source permission.
- `admin.daily_finance.index` grants access to the workspace. Each source section still requires its
  existing source permission; module access alone must not expand access to financial data.
- Detail links and source mutations retain their original permissions. Add permissions for future
  module actions only with their implementations.
- Resolve branch scope before aggregation. Keep branch calendar-day boundaries, currency precision,
  unknown counted amounts, and the latest-session-per-register rule intact.
- Read optional Accounting data through Core's contracts and null implementations. Never require
  Accounting classes or browser imports to load this module.

## Source and validation

- `app/Services/DailyFinance/` owns aggregation and its interface.
- `resources/assets/js/` owns the page, API client, contracts, manifest, routes, and sidebar.
- `tests/Feature/DailyFinanceTest.php` covers API access, branch scope, and financial semantics.
- `tests/Feature/DailyFinancePermissionTest.php` covers permission registration and default grants.
- `app/Services/DayClosing/` owns readiness, reviewed-source hashes, idempotent approval and history.
- `tests/Feature/DayClosingTest.php` and `DayClosingConcurrencyMySqlTest.php` cover immutable snapshots,
  source/branch access, blocking items, exact source totals and concurrent approvals.
- `app/Services/PaymentSettlement/` owns electronic collection reservations and append-only bank
  credits. All actions lock the branch first; creation locks selected payments in ascending ID order.
  `PaymentSettlementTest.php` and `PaymentSettlementConcurrencyMySqlTest.php` cover reviewed-source
  hashes, exact subunits, branch/permission boundaries, replay and competing writes.

Use the backend API-contract and optional-module-integration skills when changing those boundaries.
For registration changes, run the module discovery tests, `yarn check:modules`, and `yarn build` in
addition to focused PHP and browser tests. Financial mutations require the appropriate financial
workflow and disposable MySQL validation; a successful read-only overview does not establish those
guarantees.
