# ActivityLog module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns operational activity and authentication-history queries, presentation, and export definitions.
Depends on Spatie activity logging, authentication logging, User identities, and Export contracts.
Core owns the separate immutable governance audit chain; these records are not a substitute for
financial ledgers.

## Important source map

- `app/Services/ActivityLog/ActivityLogService.php` — activity queries and export source.
- `app/Services/AuthenticationLog/AuthenticationLogService.php` — login-history queries.
- `app/Models/ActivityLog.php` — subject presentation and cached filter metadata.
- `app/Transformers` — API serialization and redaction boundary.
- `routes/api/v1.php` — read-only history endpoints.
- `resources/assets/js/manifest.ts` — admin history routes through the activity alias.

## Local invariants and sensitive flows

- Preserve separate activity and authentication permissions and export definitions; do not add
  write/delete administration through the read-only controllers.
- Keep polymorphic causer type checks when joining User records: a matching numeric ID alone is
  insufficient.
- Historical subjects may no longer exist. Preserve fallback subject descriptions rather than
  requiring the current domain record.
- Authentication secrets must be excluded when logging and redacted when reading legacy entries.
  Inspect the serializer as well as model logging configuration.
- Activity filter metadata is cached with activity/user tags; changes to identities and logs must
  invalidate the appropriate cache.
- Browser API names use the `activity` alias while translation namespaces use `activitylog`; do not
  normalize them into a new module name.

## Testing and validation

Use `tests/Feature/ActivityLogListTest.php` for list/cache changes and
`tests/Feature/AuthenticationSecretRedactionTest.php` for logging/serialization. Bulk deletion
logging also has coverage in `../Support/tests/Feature/ActivityLogBulkDeleteTest.php`.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
