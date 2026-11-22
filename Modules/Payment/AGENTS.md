# Payment module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns persisted payment/refund records and allocations, payment enums, administration/export, and the
implemented surcharge-rule/evaluation/allocation primitives. Order currently orchestrates applying
payment to an order; Invoice owns document allocation; POS owns drawer movements. A tender model
alone does not establish a routed tender execution workflow.

## Important source map

- `app/Services/Payment/PaymentService.php` — read/export administration, not checkout execution.
- `app/Models/Payment.php` — branch/cashier/source financial record.
- `app/Models/PaymentAllocation.php` — invoice/payment association.
- `app/Services/PaymentSurchargeRuleAdministrationService.php` — versioned rule editing and
  activation.
- `app/Services/PaymentSurchargeEvaluator.php` — surcharge calculation.
- `app/Enums/PaymentMethod.php` — wire methods, including gift card and reservation deposit.
- `routes/api/v1.php` — actual registered payment and rule routes.
- `resources/assets/js/manifest.ts` — payment browser registration.

## Local invariants and sensitive flows

- Keep payment/refund type, payment status, method, amount and allocation meaning distinct. Do not
  update order payment status through this read/export service.
- Trace OrderPaymentService and invoice allocation before changing balances or methods. GiftCard and
  ReservationDeposit carry domain-specific liability/application behavior, not interchangeable cash
  tenders.
- Surcharge rules use expected revisions, row locks, immutable versions/reasons and branch/global
  authorization. Preserve global/branch epoch locking and activation-time reference validation.
- Rule changes cannot silently move branch ownership or apply inactive/out-of-branch tax references.
- Principal and surcharge allocation must conserve totals in the supported currency precision.
  Preserve frozen rule/tax/beneficiary context for later allocation/refund.
- PaymentAttempt, PaymentTender and related records exist in the current working tree, but inspect
  callers, routes and feature gates before describing or extending their lifecycle. Do not implement
  a direct-status shortcut based on a future specification.
- Read/export queries load cashier and branch; preserve historical record visibility and source
  links independently of present catalog configuration.

## Testing and validation

Use `tests/Feature/PaymentSurchargeRuleAdministrationTest.php`, `PaymentSurchargeEvaluatorTest.php`,
`tests/Unit/TenderPrincipalAllocatorTest.php` and `TenderSurchargeAllocatorTest.php`.
Recorded-payment changes also require `../Order/tests/Feature/OrderPaymentRoundingTest.php` and
relevant Invoice allocation/credit tests.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[api-contract-change](../../.agents/skills/api-contract-change/SKILL.md),
[database-migration](../../.agents/skills/database-migration/SKILL.md) for the corresponding
specialized changes.
