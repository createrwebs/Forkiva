# Inventory module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns ingredients/units, operational stock movements and cost balances, purchase receipts, stock
transfers/counts, order recipe snapshots and persistent stock-sync work. Product owns recipe
configuration; Order triggers consumption/restoration; Accounting owns financial valuation/posting
workflows and supplier bill/payment accounting.

## Important source map

- `app/Services/StockMovement/StockMovementService.php` — authoritative movement
  record/correction/reversal.
- `app/Services/StockTransfer/StockTransferService.php` — transfer approval, dispatch, receipts and
  discrepancy closure.
- `app/Services/StockSync/StockSyncService.php` — order consumption/restoration.
- `app/Services/StockSyncJob/StockSyncJobService.php` — persistent stock/accounting processing
  state.
- `app/Jobs/ProcessInventoryStockSyncJob.php` — token-bound overlap/retry/failure handling.
- `app/Enums/StockTransferStatus.php` — transition matrix.
- `app/Models/OrderProductIngredientSnapshot.php` — sale-time ingredient evidence.
- `resources/assets/js/composables/stockTransfer.ts` — transfer workflow UI.
- `app/Providers/InventoryServiceProvider.php` — processor/reconciliation command scheduling.

## Local invariants and sensitive flows

- Never mutate ingredient stock independently of the movement service. A movement validates positive
  quantity/rate and trusted source/unit context, locks the ingredient, then rechecks idempotency
  after acquiring that serialization lock.
- Manual endpoints allow only supported adjustment/loss types; purchase, transfer and order-owned
  source identities must come from their owning services.
- Preserve committed movement evidence and cost/rate snapshots. Corrections/reversals create linked
  movements with reasons and deterministic keys; they do not rewrite original history.
- Transfers progress draft → approved → dispatched → partial/received or discrepancy closure.
  Cancellation is available before dispatch; dispatched void and partial receipt have different
  constraints. Terminal states cannot be reopened via direct updates. Use the enum/service matrix.
- Purchase receipt quantity and stock transfer receipt quantity are operational facts; do not infer
  supplier settlement or Accounting posting from receipt status.
- Stock-sync states distinguish pending stock, processing stock, pending accounting, completed and
  failed. A posting failure must not repeat already committed stock consumption. Preserve dispatch
  tokens, leases, backoff and terminal failure updates.
- Product recipes/options are snapshotted for sold lines. Later recipe edits must not change the
  ingredients restored for an earlier order.
- Reconciliation/remediation commands can alter historical balances: inspect their scope and mode
  instead of running them as a routine test.

## Testing and validation

Use `tests/Feature/ProductInventoryConsumptionTest.php`, `StockTransferWorkflowTest.php`,
`StockTransferMySqlIntegrityTest.php` and notification/analytics tests as relevant. Cross-module
reliability has `../Accounting/tests/Feature/Posting/AccountingStockSyncReliabilityTest.php` and
procurement concurrency coverage. Exercise retries after stock commit but before posting success on
the disposable engine target.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[database-migration](../../.agents/skills/database-migration/SKILL.md),
[optional-module-integration](../../.agents/skills/optional-module-integration/SKILL.md) for the
corresponding specialized changes.
