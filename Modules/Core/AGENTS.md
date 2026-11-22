# Core module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns module boot/route registration, shared HTTP request infrastructure, optional integration
contracts, governance primitives, and shared browser services/components. Support owns low-level
Eloquent/Money/response helpers; business modules own pricing, posting and other domain decisions.

## Important source map

- `app/Providers/CoreServiceProvider.php` — shared setup and optional contract bindings.
- `app/Providers/ModuleServiceProvider.php` — module registration.
- `app/Providers/RouteServiceProvider.php` — module route composition.
- `app/Services/Governance/IdempotencyService.php` — durable keyed replay and payload conflicts.
- `app/Services/Governance/ImmutableAuditLogger.php` — append-only evidence chain.
- `routes/web.php` — SPA shell, offline shell and service-worker routes.
- `resources/assets/js/services/AppBootstrapCacheService.ts` — public boot caching.
- `resources/assets/js/services/ModuleService.ts` — browser module composition.

## Local invariants and sensitive flows

- Core boot must not require concrete Hr/Delivery/Accounting classes when an add-on is unavailable.
  Preserve contract return shapes and null implementations, including failure semantics for enabled
  providers.
- Idempotency records distinguish processing/completed/failed state and bind a key to a
  deterministic payload. Do not collapse a mismatched payload into a successful replay or use a
  transient cache as the durable record.
- Audit evidence includes actor, reason, source, before/after metadata and previous hash. Do not
  update prior evidence or substitute operational ActivityLog records for this chain.
- The SPA catch-all is intentionally limited to known browser route prefixes. It must not swallow
  API failures, installer endpoints, downloads or missing assets. Only the account-free shell is
  eligible for offline precaching.
- Shared browser HTTP headers/auth handling, boot-cache versions, module manifest assembly and
  sidebar composition affect all owners. Preserve their extension points rather than importing
  domain pages into shared services.
- Database grammar/cache-lock compatibility code is shared infrastructure: a change that passes
  SQLite may still break supported engines or distributed coordination.

## Testing and validation

Choose `tests/Feature/CoreGovernanceFoundationTest.php`, `OptionalModuleBindingResolutionTest.php`,
or `WebApplicationTest.php` in the same directory for the changed boundary. Cache/engine changes
have `AlternativeFileCacheLockTest.php`, `PostgresCompatibilityTest.php` and
`tests/Unit/MySqlSchemaGrammarTest.php`. Browser coverage includes
`resources/assets/js/api/http.test.ts`,
`resources/assets/js/services/AppBootstrapCacheService.test.ts` and `ModuleService.test.ts` beside
it; use affected shared component/store tests as well.

Use [optional-module-integration](../../.agents/skills/optional-module-integration/SKILL.md),
[api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
