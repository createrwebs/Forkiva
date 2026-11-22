# Branch module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns branch identity, regional/order/payment configuration, branch-access resolution and reusable
branch traits/scopes. Consumers such as POS, seating, analytics and HR own their resource-specific
permissions and records.

## Important source map

- `app/Services/BranchAccess/BranchAccessService.php` — assigned/global-user branch resolution.
- `app/Traits/HasBranch.php` — conditional authenticated-user scope and creation context.
- `Scopes/BranchPermissionScope.php` — scope implementation outside app; preserve this existing
  path.
- `app/Models/Branch.php` — branch configuration, currency and relationships.
- `app/Http/Requests/Api/V1/SaveBranchRequest.php` — supported currencies, time zones, order and
  payment methods.
- `resources/assets/js/pages/admin/branch/Partials/Form.vue` — branch configuration UI.

## Local invariants and sensitive flows

- An assigned user cannot override their branch; a mismatched requested branch resolves as 404. A
  global user may omit the branch only when the caller permits it.
- Resolution intentionally can find inactive/soft-deleted branches for historical contexts. Do not
  turn that into permission to create new operational records there.
- `HasBranch` depends on an authenticated user; jobs/commands need deliberate branch context.
  Preserve explicit resource checks in consumers.
- Branch currency, payment methods, order types and quick-pay configuration affect POS/pricing
  initialization. Do not change these as cosmetic settings or rewrite historic monetary values.
- Preserve main/active branch and installation currency behavior when modifying defaults or seeders.

## Testing and validation

Use `tests/Feature/BranchAwareFormMetaIntegrationTest.php`,
`tests/Feature/BranchInstallationCurrencyTest.php`, and
`tests/Unit/BranchAwareFormMetaArchitectureTest.php`. Exercise assigned and global users, foreign
branches, and branch-specific form metadata.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
