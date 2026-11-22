# Tool module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns administrative database backup/restore and driver capability reporting. Installer owns initial
provisioning; domain modules own migrations. The browser database tool is an operationally
destructive surface, not ordinary CRUD.

## Important source map

- `app/Services/Database/DatabaseService.php` — operation lock, preflight, backup manifest/checksum
  and restore orchestration.
- `app/Services/Database/DatabaseRestoreGuard.php` — maintenance and queue pause/resume
  preservation.
- `app/Services/Database/Drivers` — MySQL/PostgreSQL/SQLite adapters.
- `app/Services/Database/DatabaseBinaryLocator.php` — native binary discovery.
- `routes/api/v1.php` — separate index/download/backup/restore permissions.
- `resources/assets/js/composables/databaseTool.ts` — browser operation state.

## Local invariants and sensitive flows

- Preserve preflight capability checks and the operation lock. A database-backed lock can disappear
  during restore; do not replace the configured coordination mechanism with the database being
  restored.
- Finalize complete backup files/manifests atomically, verify managed checksums, and exclude
  partial/manifest files from selectable backups. Preserve engine mismatch and path validation.
- Restore creates the established safety backup and runs under the restore guard. The guard only
  reverses maintenance/queue changes it activated; do not resume a queue that was already paused.
- Native command failures must be redacted and surfaced accurately. Do not substitute shell
  interpolation of database credentials or unvalidated filenames.
- Actual restore and retention deletion affect stored data/files. Test only against disposable
  fixtures; reading a capability screen is not authorization to restore the user’s database.

## Testing and validation

Use `tests/Unit/DatabaseRestoreGuardTest.php`, `CommandDatabaseDriverTest.php`,
`tests/Feature/DatabaseServiceSqliteTest.php`, and the MySQL/PostgreSQL integration tests in that
Feature directory only on their disposable targets. Browser regressions include
`resources/assets/js/api/database.api.test.ts` and
`resources/assets/js/pages/admin/database/Index.test.ts`.
