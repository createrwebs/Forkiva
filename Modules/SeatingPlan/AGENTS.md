# SeatingPlan module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns floors/zones/tables, live seating status, table merge evidence and public QR order context.
Order owns persisted sales/pricing/payment lifecycle; Reservation owns future booking availability
and deposits; Pos owns operational table-view UI and register sessions.

## Important source map

- `app/Services/Table/TableService.php` — table administration and QR-token operations.
- `app/Services/TableViewer/TableViewerService.php` — live table actions, merge and split mutations.
- `app/Services/TableMerge/TableMergeService.php` — merge history queries.
- `app/Models/TableMerge.php` — merge state and relationships.
- `app/Models/MergeSnapshot.php` — preserved merge evidence.
- `app/Services/TableQrOrder/TableQrOrderService.php` — public context/quote/submit/tracking.
- `app/Enums/TableStatus.php` — available/occupied/reserved/cleaning/merged values.
- `routes/api/v1.php` — admin and public QR route boundaries.
- `resources/assets/js/pages/public/tableOrder/Index.vue` — public ordering UI.

## Local invariants and sensitive flows

- Live table state must agree with active orders, reservations and merge membership. Do not mark a
  table available solely because one linked record completed.
- Capacity, billing and order merges have different effects on tables and orders. Preserve
  membership/snapshots and the workflow locks; split rejects merges with active orders. The merge
  list service is not the mutation authority; follow TableViewerService and its controller before
  changing the workflow.
- QR access requires the table UUID plus token and enabled branch/table settings. Public queries
  intentionally bypass authenticated branch scopes only after deriving authorized table/branch
  context. Do not accept a public branch ID as the scope.
- QR quote/submit verifies sale availability, dine-in support, active menu and open POS session;
  merged/unavailable tables and conflicting active orders fail explicitly. Guest-count and voucher
  policy remain server-enforced.
- QR submission uses Order CheckoutConfirmation and server pricing, locks the table/session/order
  context, and preserves scoped idempotent replay. Do not create a separate public pricing engine or
  trust local Cart estimates.
- Tracking exposes the public progress projection, not unrestricted administrative order details.
  Preserve token handling and public error reasons used by the browser.
- Table/waiter/status notifications and historical status logs remain tied to the authoritative
  mutation.

## Testing and validation

Use `tests/Feature/TableOrderMergeTest.php`, `TableQrOrderingTest.php`,
`BranchAwareFormMetaTest.php` and notification tests. Public UI has
`resources/assets/js/pages/public/tableOrder/Index.test.ts` and adjacent partial tests. Shared
occupancy changes also require Reservation integrity and Order checkout concurrency tests.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md),
[financial-code-change](../../.agents/skills/financial-code-change/SKILL.md) for the corresponding
specialized changes.
