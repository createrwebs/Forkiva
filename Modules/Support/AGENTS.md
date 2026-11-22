# Support module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns shared Eloquent behavior, response/Money/date representations, filtering/sorting helpers, enums
and schema macros. Core owns boot and module integration; domain services own operational and
financial policy. There is no browser application or public route surface in this module.

## Important source map

- `app/Eloquent/Model.php` — shared model behavior and builder integration.
- `app/ApiResponse.php` — response envelope and pagination structure.
- `app/Money.php` — numeric money wrapper and serialization.
- `app/Providers/SupportServiceProvider.php` — Blueprint and framework macros.
- `app/Traits/HasFilters.php` — shared filter application.
- `helpers.php` — globally loaded helpers.

## Local invariants and sensitive flows

- Preserve response field omission/null behavior and paginated extras used across clients; changing
  this class is a contract change even if no controller changes.
- Money is a numeric wrapper with floating-point operations, not an exact decimal ledger. Preserve
  negative-zero normalization and serialization while using domain decimal implementations for exact
  decisions.
- Shared date filters use half-open calendar bounds; replacing them with timestamp equality or
  inclusive next-day bounds changes results and index behavior across modules.
- Bulk deletes on activity-logged models preserve per-model logging behavior. Do not optimize them
  into raw deletion that skips evidence.
- Schema macros encode real foreign-key deletion behavior, especially `branch()`; changes affect
  every migration consumer.
- Adding a domain dependency here expands the shared dependency graph. Keep domain policy in its
  owner and inspect existing callers before changing helper semantics.

## Testing and validation

Focused regressions include `tests/Unit/MoneyTest.php`, `tests/Unit/DateRangeTest.php`,
`tests/Feature/HasFiltersDateRangeTest.php`, `tests/Feature/ActivityLogBulkDeleteTest.php`, and
`tests/Unit/PasskeyConfigurationValidatorTest.php`. Run affected consuming-domain tests when a
shared helper’s behavior changes.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md),
[database-migration](../../.agents/skills/database-migration/SKILL.md) for the corresponding
specialized changes.
