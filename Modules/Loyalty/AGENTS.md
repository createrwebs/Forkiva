# Loyalty module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns programs, customer points/tier state, promotions, rewards, gifts and point transactions. User
owns customer identity; Discount/Voucher own minted discount artifacts; Order lifecycle calls
earning, gift use and reversal. A loyalty gift is not a GiftCard liability.

## Important source map

- `app/Services/Loyalty/LoyaltyService.php` — order earning, summaries and point lifecycle.
- `app/Services/LoyaltyGift/LoyaltyGiftService.php` — reward redemption, artifact minting and order
  use.
- `app/Models/LoyaltyTransaction.php` — point history.
- `app/Models/LoyaltyGift.php` — gift state and source links.
- `app/Providers/LoyaltyServiceProvider.php` — expiration scheduling.
- `app/Console` — point/gift expiration commands.
- `resources/assets/js/composables/loyaltyGift.ts` — browser redemption/gift actions.

## Local invariants and sensitive flows

- Earning is linked to the order and existing earn transactions; replay must not award points twice.
  Preserve customer/program context, order currency conversion, tier multipliers and promotion
  eligibility.
- Redemption checks program, date/day, branch, tier, minimum spend, points and mint limits. Keep
  consumed point transactions and issued gift/artifact records consistent; a reduced balance alone
  is insufficient evidence.
- Gift use records its order identity and validates the entitled customer. Order edits may retain an
  already-used gift only through the explicit same-order path.
- Expiration changes point/gift lifecycle through the existing services/commands and notifications.
  Do not delete point history or silently resurrect expired benefits.
- Locking must be evaluated in the caller’s transaction: the presence of `lockForUpdate()` before a
  transaction does not itself prove concurrent redemption safety.
- Keep operational reward state distinct from cash/refund/accounting state.

## Testing and validation

Local coverage includes `tests/Feature/LoyaltyNotificationWorkflowTest.php` and
`LoyaltyInstallationSeederTest.php`. Earning/redeeming changes also need Order checkout/pricing
cases; report semantics have `../Report/tests/Feature/LoyaltyReportSemanticsTest.php`. Add a focused
concurrency regression when changing redemption coordination.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md) for the
corresponding specialized changes.
