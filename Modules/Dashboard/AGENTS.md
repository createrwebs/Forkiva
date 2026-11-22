# Dashboard module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns operational dashboard aggregation and per-user widget layout. Order, Payment, POS, Inventory
and catalog modules remain the source of their underlying records. Dashboard metrics are not
accounting posting or a replacement for Report definitions.

## Important source map

- `app/Services/Dashboard/DashboardService.php` — filter normalization, widget permission checks,
  aggregation and caching.
- `app/Enums/DashboardWidget.php` — widget catalog and permission mapping.
- `app/Http/Requests/Api/V1/DashboardRequest.php` — date ranges and widget option whitelist.
- `resources/assets/js/composables/dashboardLayout.ts` — versioned user-specific layout/filter
  persistence.
- `resources/assets/js/composables/dashboardGridEngine.ts` — grid placement behavior.
- `resources/assets/js/pages/admin/dashboard/Index.vue` — dashboard orchestration.

## Local invariants and sensitive flows

- Resolve branch scope before aggregation and authorize each widget independently. A dashboard
  permission is not blanket access to every metric.
- Keep normalized filters, time granularity, currency conversion and cache keys consistent; cached
  results must not leak between branch/filter contexts. Preserve cache-lock fallback behavior.
- Request memoization is reset for each dashboard request. Do not let the service retain another
  request’s metrics.
- Browser layout/filter storage is keyed by user and version. Preserve migration/normalization,
  collision-free placement and widget availability checks when changing saved layouts.
- Keep comparison ranges and series bucket alignment consistent; avoid showing a missing bucket as a
  shifted date.

## Testing and validation

`tests/Feature/DashboardApiTest.php` covers API behavior. Browser regressions live in
`resources/assets/js/composables/dashboardLayout.test.ts`, `dashboardGridEngine.test.ts` in the same
directory, and `resources/assets/js/pages/admin/dashboard/Index.test.ts`. Run the tests matching
changed aggregation or layout behavior.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
