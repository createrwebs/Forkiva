# Cart module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns browser-local drafts, option validation, estimates and submit coordination shared by POS and
public table ordering. Order owns authoritative server pricing and persisted orders; Pos and
SeatingPlan own their channel UI and entry points. Cart has no server-side order persistence/API
service.

## Important source map

- `resources/assets/js/composables/cart.ts` — ownership-scoped draft orchestration and async
  mutation queue.
- `resources/assets/js/services/draftStorage.ts` — IndexedDB revision checks and atomic draft moves.
- `resources/assets/js/services/submitDraft.ts` — quote refresh and idempotent submission.
- `resources/assets/js/stores/drafts.ts` — monotonic committed snapshot projection.
- `resources/assets/js/services/estimate.ts` — local display estimate.
- `resources/assets/js/services/options.ts` — selection limits and option normalization.
- `resources/assets/js/contracts/draft.ts` — draft/channel/submission contract.

## Local invariants and sensitive flows

- All draft mutations commit through expected-revision checks in IndexedDB; update the Pinia
  projection after commit. A local store assignment is not durable persistence.
- Preserve account/channel ownership, recovery filtering, account cleanup and atomic source/target
  checks for moves. Never recover another account’s draft just because its key is present.
- Older broadcasts or quote responses must not replace newer revisions. Preserve the serialized
  mutation queue, scope cleanup and pending-submission guards.
- Indexed API errors map through the submitted snapshot to stable line IDs; a current array index
  can identify a different product after edits.
- Estimates are display hints, not permission to trust client totals. Submission uses a server quote
  and keeps its idempotency identity across the bounded quote-refresh retry.
- Persisted submission state must survive uncertain network outcomes; do not clear it and issue a
  new identity until the result is reconciled.
- Benefit/option rollback must not undo edits made after the request started. Preserve revision
  guards around rollback and delivery-quote errors.

## Testing and validation

Use `resources/assets/js/composables/cart.test.ts`, `resources/assets/js/services/estimate.test.ts`,
`options.test.ts` beside it, and `resources/assets/js/utils/taxSummary.test.ts`. Integration
coverage includes POS draft/checkout tests and SeatingPlan public table-order tests. Test races,
storage failure and recovery alongside ordinary product edits.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
