# Discount module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns reusable discount definitions and the application checker pipeline for both discounts and
vouchers. Voucher owns code-based offers; Loyalty owns gift entitlement; Order Pricing owns the
resulting persisted discount and totals.

## Important source map

- `app/Services/DiscountApplyService/DiscountApplyService.php` — ordered discount/voucher
  eligibility pipeline.
- `app/Checkers` — branch/customer/catalog/day/spend/usage checks.
- `app/Models/Discount.php` — validity windows, usage limits and gift association.
- `app/Services/Discount/DiscountService.php` — administration/export.
- `resources/assets/js/manifest.ts` — discount administration registration.

## Local invariants and sensitive flows

- Apply benefits through the checker pipeline; finding an active definition is insufficient.
  Preserve item, branch, customer, order-type, product/category, day, spend and usage checks.
- Loyalty-backed discounts require the entitled customer/branch. Existing-order edits retain a
  previously used gift only under the explicit same-order/same-customer rule.
- Usage calculations accept an excluded current order; removing that distinction can reject valid
  edits or count the same benefit twice.
- Preserve fixed-versus-percentage interpretation, maximum discount and spend limits; do not copy
  formatted Money strings into arithmetic.
- Catalog/admin updates do not authorize rewriting discounts captured on issued orders/invoices.

## Testing and validation

No module-local test suite exists. Application behavior is exercised through
`../Order/tests/Feature/CheckoutSecurityTest.php` and
`../Order/tests/Feature/PricingCalculationTest.php`; add focused eligibility/usage cases there or in
this module when behavior changes. Include Loyalty gift consumers when relevant.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md) for the
corresponding specialized changes.
