# Order module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns authoritative persisted order/line state, pricing context, server checkout confirmation and
order-level payment orchestration. Cart estimates local drafts; Pos supplies sessions/UI; Payment
stores payment records; Invoice issues documents; Inventory applies stock effects; SeatingPlan owns
tables/merges and public QR context. Delivery state belongs to its optional bridge.

## Important source map

- `app/Services/Checkout/CheckoutPricing.php` — validated catalog/context reconstruction.
- `app/Services/Checkout/PosCheckout.php` — quote, edit revision and locked submission.
- `app/Services/Checkout/CheckoutConfirmation.php` — quote confirmation and durable scoped replay.
- `app/Services/SaveOrder/SaveOrderService.php` — persisted lines, tables and lifecycle effects.
- `app/Services/OrderPayment/OrderPaymentService.php` — single/merged order tender allocation.
- `app/Pricing` — mutable request-scoped pricing implementation.
- `app/Support/OrderRevision.php` — editable order concurrency fingerprint.
- `app/Providers/EventServiceProvider.php` — invoice, inventory, loyalty and notification listeners.
- `app/Models/Order.php` — edit/payment eligibility and state relationships.
- `resources/assets/js/api/order.api.ts` — browser order endpoints.

## Local invariants and sensitive flows

- Keep operational order status, line preparation status and payment status distinct. Existing
  statuses include pending/confirmed/preparing/ready/served/completed and cancelled/refunded/merged;
  they are not freely assignable transitions. Inspect model eligibility and the authoritative
  service for the requested action.
- Pricing and SaveOrder retain mutable request context and are scoped. Rebuild catalog, options,
  branch, benefits and permitted adjustments server-side; never accept Cart estimates as final
  money.
- Edit submissions require the current `edit_version`; quotes bind the economic context. Preserve
  quote invalidation and 409 responses when revision, pricing or charge configuration changes.
- Submit locks table IDs deterministically, then resolves the order/session and benefits under the
  transaction. Re-check table occupancy, session ownership/open state and edit eligibility after
  locking. Preserve lock order shared with table merge/QR paths.
- Checkout receipts bind scope, idempotency key and request hash. Valid replay returns the original
  result; changed payloads conflict. Do not issue another order after a timeout merely because the
  client did not see success.
- Order-payment allocation uses currency minor units and distinguishes full/partial payment.
  Preserve merged-order conservation, supported branch methods, GiftCard redemption identity and
  Reservation deposit handling.
- Paid/refunded/cancelled events drive invoices, credit notes, inventory and loyalty. Direct row
  updates can omit these effects; trace synchronous listener failures before changing transaction
  boundaries.
- Existing ServiceCharge projections/snapshots are in the working source. Follow their feature gates
  and frozen context. The legacy add-payment path explicitly rejects surcharge-enabled operation; do
  not infer a complete new tender workflow from model names.
- Preserve optional Delivery interfaces and null behavior; do not import a concrete add-on into core
  pricing/serialization.
- Browser order screens coexist with POS and public QR consumers; a changed order wire shape must be
  checked at those entry points.

## Testing and validation

Use `tests/Feature/CheckoutSecurityTest.php`, `CheckoutConcurrencyMySqlTest.php`,
`PricingCalculationTest.php`, `OrderEditAllowedTest.php`, `OrderPaymentRoundingTest.php` and
`tests/Unit/CheckoutPricingFingerprintTest.php` as applicable. Charge projections have
`tests/Feature/OrderChargeProjectionTest.php` and `ServiceChargeApplicationTest.php`. Include
Invoice/Inventory/SeatingPlan regressions when lifecycle listeners or shared locks change; MySQL
races require the disposable engine target.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[api-contract-change](../../.agents/skills/api-contract-change/SKILL.md),
[optional-module-integration](../../.agents/skills/optional-module-integration/SKILL.md) for the
corresponding specialized changes.
