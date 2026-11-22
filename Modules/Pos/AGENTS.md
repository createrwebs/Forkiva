# Pos module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns registers, open/closed cash sessions, drawer movements, POS/kitchen/customer-display
projections and operational browser UI. Cart owns local drafts; Order owns server
quote/submit/pricing and payments applied to orders; Printer owns dispatch/rendering. POS cash
movements are not the Accounting general ledger.

## Important source map

- `app/Services/PosSession/PosSessionService.php` — register session open/close and closing summary.
- `app/Services/PosCashMovement/PosCashMovementService.php` — cash movement validation, idempotency
  and optional posting.
- `app/Services/PosViewer/PosViewerService.php` — branch/session/catalog initialization.
- `app/Services/KitchenViewer/KitchenViewerService.php` — kitchen projection/actions.
- `resources/assets/js/composables/usePosDraftForm.ts` — draft-to-form synchronization.
- `resources/assets/js/composables/useCustomerDisplay.ts` — customer display lifecycle.
- `resources/assets/js/services/orderPrintQueue.ts` — browser print queue.
- `routes/api/v1.php` — operational API entry points.

## Local invariants and sensitive flows

- Sessions are open or closed. Opening checks for an existing open register session; closing
  computes/stores declared cash and summary through the session workflow and emits the existing
  events. Do not replace closure with a status update or claim its transaction alone proves race
  safety.
- Resolve branch, register and active session coherently before checkout/cash actions. Preserve
  cashier permissions and foreign-branch rejection in the actual entry services.
- Cash direction/reason, sale/refund links, idempotency and correction semantics determine closing
  totals. Keep original movement evidence and optional Accounting/Hr side effects consistent.
- Catalog visibility uses menu/category/product activity and branch context; public menu consumers
  reuse this projection.
- Keep customer-display snapshots separate from authoritative orders. Browser form hydration and
  async responses must not overwrite a newer Cart draft.
- Kitchen item progression is not automatically order settlement. Call the established Order
  behavior and preserve event-driven notifications.
- Print jobs are device-bound side effects; preserve queue sequencing, completion/failure handling
  and retry identity. Do not print twice after an uncertain submission.

## Testing and validation

Choose `tests/Feature/PosActiveSessionTest.php`, `PosCartCheckoutFlowTest.php`,
`PosProductVisibilityTest.php`, `KitchenViewerServiceTest.php` or `CustomerDisplayTest.php` in that
directory. Browser regressions include `resources/assets/js/composables/usePosDraftForm.test.ts`,
`useCustomerDisplay.test.ts`, `useKitchenBoard.test.ts` and
`resources/assets/js/services/orderPrintQueue.test.ts`. Cash/posting changes also need affected
financial-owner tests.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[printing-change](../../.agents/skills/printing-change/SKILL.md),
[api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
