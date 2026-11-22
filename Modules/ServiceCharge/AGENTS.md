# ServiceCharge module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns service-charge rules, immutable rule revisions, beneficiaries, condition evaluation, exact
allocation helpers and frozen pricing-context construction. Order owns applying/persisting charges
on orders; Payment owns tender surcharge rules; Invoice owns document allocations; Service owns
unrelated external-provider settings. This guide describes implemented working-tree code, not a
claim of production rollout.

## Important source map

- `app/Services/RuleAdministrationService.php` — revision-checked rule changes and epochs.
- `app/Services/RuleResolver.php` — deterministic precedence and condition evaluation.
- `app/Services/BeneficiaryAdministrationService.php` — branch beneficiary lifecycle.
- `app/Services/SavedOrderPricingContextFactory.php` — historical order context.
- `app/Services/ChargeFeatureFlags.php` — branch feature gates.
- `app/Services/MinorUnitAllocator.php` — conserving amount allocation.
- `app/Services/ChargeTaxSnapshotResolver.php` — validated tax snapshots.
- `app/Console/BackfillLegacyChargesCommand.php` — legacy projection backfill.
- `resources/assets/js/manifest.ts` — currently a name-only manifest; no feature pages registered.

## Local invariants and sensitive flows

- Resolution sorts active candidates by priority descending, branch-specific before global at equal
  priority, then ID. It chooses the first matching rule. Do not change this into additive stacking
  accidentally.
- Effective-time bounds, original order source, branch-local time conditions,
  guest/location/customer and eligible catalog context belong to the resolver. Unsupported condition
  schemas fail explicitly.
- Monetary thresholds/calculation bases use exact decimal values and frozen rates; distinguish
  before-general-discount, after-discount and eligible-item bases.
- Rule mutations lock scope epochs and the rule, enforce expected revision, retain a reason/version
  snapshot and increment the epoch. Preserve conflict responses and branch ownership; global
  management requires its explicit permission.
- Activation validates beneficiary, active tax references and condition catalog references.
  Beneficiaries used by active rules cannot be modified/deleted through a shortcut.
- Reconstruct saved-order context from stored catalog/source/financial snapshots, not current
  category or product membership. Keep Order’s quote fingerprint and charge application in sync.
- Treat legacy backfill as a controlled historical migration, not permission to rerun it against
  live records while editing rules. The current browser manifest does not prove an administration UI
  exists.

## Testing and validation

Use `tests/Feature/RuleAdministrationTest.php`, `RuleResolutionTest.php`,
`SavedOrderPricingContextFactoryTest.php`, `LegacyChargeBackfillTest.php` and the allocator/math
tests under `tests/Unit/`. Integration changes require Order charge application and Invoice charge
handling tests.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[database-migration](../../.agents/skills/database-migration/SKILL.md),
[api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
