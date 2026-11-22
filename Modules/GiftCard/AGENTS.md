# GiftCard module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns stored-value card identity, lifecycle, immutable financial transactions, batches, liability
context and breakage. Payment/Order own sale-linked redemption/refund orchestration; Accounting owns
journal posting. Card balances are distinct from loyalty points and voucher discounts.

## Important source map

- `app/Services/GiftCardTransaction/GiftCardTransactionService.php` — locked recording, replay
  matching and reversal.
- `app/Services/GiftCard/GiftCardService.php` — issuance and card lifecycle.
- `app/Services/GiftCardBreakage/GiftCardBreakageService.php` — draft/review/post/reverse breakage
  batches.
- `app/Services/GiftCardCode/GiftCardCodeService.php` — card code generation/handling.
- `app/Models/GiftCardTransaction.php` — financial ledger evidence.
- `app/Console/ExpireGiftCardsCommand.php` — expiration entry point.
- `resources/assets/js/composables/giftCardTransaction.ts` — financial action UI contract.
- `resources/assets/js/composables/giftCardBreakage.ts` — breakage workflow UI.

## Local invariants and sensitive flows

- Balance changes go through the transaction service with a card lock, exact amount/rate handling,
  durable idempotency key and request fingerprint. Reuse of a key with different financial inputs
  fails; a retry must not create another ledger row.
- Preserve liability branch, source/default currency values, frozen rate and source
  payment/transaction links. Branch scope and liability ownership are separate checks.
- Reversals create linked records; they do not mutate original transactions. Expiry is not directly
  reversible, purchase reversal uses the issue-void path, and payment-owned redeem/refund reversals
  must remain payment-owned.
- Breakage requires expired eligible cards, consistent currency/liability branch, reviewed snapshots
  and revalidation at posting. Draft → reviewed → posted is not replaceable by a generic status
  field; reversal is linked and idempotent.
- Operational expiration does not itself mean Accounting breakage recognition succeeded. Preserve
  bridge event identity and enabled-integration failure visibility.
- Treat card secrets as credentials: preserve code masking and generation rules in
  Resources/UI/logging. Do not expose full codes in generic analytics or exports.
- Browser financial actions must retain their attempt identity on retry and distinguish lifecycle
  actions from ordinary card editing.

## Testing and validation

Use `tests/Feature/GiftCardFinancialLedgerTest.php`, `GiftCardLifecycleFinancialTest.php`,
`GiftCardProductionReadinessTest.php` and notification/analytics tests as affected. Precision/code
coverage includes `tests/Unit/GiftCardCurrencyConversionTest.php`, `GiftCardCodeFormatTest.php` and
ledger Money attribute tests. Posting changes need Accounting operational-finance tests and the
relevant engine gate.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[optional-module-integration](../../.agents/skills/optional-module-integration/SKILL.md) for the
corresponding specialized changes.
