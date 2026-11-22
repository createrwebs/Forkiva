# Voucher module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns code-based voucher definitions, validity/usage tracking and voucher-specific checkers.
DiscountApplyService executes the voucher pipeline; Loyalty can mint a voucher-backed gift; Order
stores the accepted discount snapshot. Vouchers are discounts, not stored-value GiftCards.

## Important source map

- `app/Models/Voucher.php` — code lookup, validity, spend and usage checks.
- `app/Checkers` — voucher-specific eligibility.
- `app/Services/Voucher/VoucherService.php` — voucher administration/export.
- `routes/api/v1.php` — administrative API.
- `resources/assets/js/manifest.ts` — voucher browser registration.

## Local invariants and sensitive flows

- A matching code alone is not authorization to apply the offer. Route application through
  Discount’s voucher pipeline so branch/customer/catalog/date/order-type/spend and usage limits all
  run.
- Preserve per-voucher and per-customer limits and the current-order exclusion used during edits.
  Coordinate use/unuse with Order’s transaction instead of adjusting counters from the browser.
- Gift-backed vouchers retain Loyalty ownership and same-order reuse restrictions; they must not
  become transferable by submitting another customer ID.
- Keep pricing snapshots on historical orders and invoices independent from subsequent
  code/definition changes.
- Preserve code lookup behavior and uniqueness rules; avoid introducing a second
  lookup/normalization path in public QR code handling.

## Testing and validation

No local PHP/browser tests are present. Use the changed paths in
`../Order/tests/Feature/CheckoutSecurityTest.php`,
`../Order/tests/Feature/PricingCalculationTest.php`, and
`../SeatingPlan/tests/Feature/TableQrOrderingTest.php` for public voucher behavior.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
