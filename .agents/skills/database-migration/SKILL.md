---
name: database-migration
description: Design and validate Forkiva schema migrations and data backfills for existing installations, preserving module ownership, financial history, database-engine behavior, rollout compatibility, and safe deployment order. Use for schema evolution or migration repairs.
---

# Database migrations

Apply the workspace and backend guides. Paths below are relative to `backend/`. This workflow authorizes no migration
against a live or shared database beyond the user's task scope.

## Locate ownership and migration behavior

Find the current table definition and every subsequent alteration under the owning module's `database/migrations/`.
Inspect models/casts, relationships, queries, factories, and tests that depend on it. Core owns shared schema such as
cache tables; root `database/migrations/` contains installation infrastructure. Do not place domain tables in the root
merely to make discovery easier.

Check `config/modules.php` and the relevant providers for automatic migration loading, module dependencies, and
ordering. Determine whether a nested repository owns the change.

Use a new timestamped migration for released schema evolution. Only edit migration history when the task explicitly
targets verified unreleased migrations. A recent filename or uncommitted copy alone does not prove a migration never ran
elsewhere.

Read the relevant test fixture before selecting a command. Some feature tests load only selected module migrations;
others use `RefreshDatabase`. `phpunit.xml` uses SQLite in memory, while `phpunit.mysql.xml` and
`tests/Concerns/InteractsWithAccountingMySql.php` support separate engine tests. Do not assume all fixtures represent a
complete installation.

## Design for existing rows and deployment

1. Identify the intended invariant, existing data that may violate it, affected row volume, and the compatibility window
   between old and new application code. For required columns or constraints, plan defaults/nullability, backfill, and
   enforcement in a safe order. Use expansion/backfill/contraction when a one-step change cannot support the rollout.

2. Assume application instances and queue workers may temporarily run different code versions during deployment. Design
   schema changes so old and new code can coexist during the rollout window. Add before requiring, backfill before
   enforcing, and remove obsolete columns/contracts only after dependent application and worker versions have been
   retired.

3. Include long-lived queue workers and scheduled processes in the compatibility window. A migration is not rollout-safe
   if an old worker can read or write the changed schema incorrectly before workers are drained or restarted.

4. Preserve decimal precision, currency/rate meaning, branch ownership, soft deletion, and audit/history semantics.
   Check foreign-key types and deletion behavior. `Modules/Support/app/Providers/SupportServiceProvider.php` defines
   Blueprint macros: `branch()` defaults to nullable with `nullOnDelete()`, while its non-nullable variant cascades. Do
   not apply that macro blindly to immutable financial history.

5. Use suitable indexes and unique constraints for query paths and idempotency, considering pre-existing duplicates and
   nullable uniqueness semantics on the target engine. Resolve bad data explicitly; do not delete financial records or
   invent ownership to satisfy a new constraint. Cross-table invariants still require transactional application
   enforcement.

6. Respect optional module boundaries. A foreign key, backfill query, or model import can make an otherwise optional
   module mandatory. Verify install and upgrade behavior without the optional tables and follow existing bridge/schema
   conventions instead of silently skipping required schema. Use the optional-module workflow when availability changes
   the design.

7. Keep historical backfills stable. Avoid current model events, global scopes, or changing business configuration
   unless explicitly intended. Prefer explicit persisted columns and query logic for historical transformations. Bound
   large updates and make resumable backfills deterministic and safe to retry. Do not hold an unbounded transaction or
   assume DDL is transactional on MySQL.

8. For large or hot tables, inspect row count, index creation strategy, lock duration, table-copy/rebuild behavior, and
   whether the target MySQL/PostgreSQL version supports the intended online DDL. Do not assume adding an index or
   altering a column is operationally cheap because the SQL is short.

9. Evaluate locking and table-rebuild implications on the intended engine. Inspect existing database compatibility
   helpers before adding raw SQL, enum changes, generated columns, or check constraints.
   `Modules/Core/tests/Feature/PostgresCompatibilityTest.php` documents existing PostgreSQL compatibility behavior; do
   not equate SQLite syntax success with MySQL/PostgreSQL support.

10. Define roll-forward recovery and whether `down()` is genuinely lossless. Do not describe dropping populated columns
    as a safe rollback. Keep posted financial history append-only; corrections must preserve source, snapshot, and
    reversal links.

11. For high-risk production schema changes, document the required backup/restore or snapshot prerequisite when the
    deployment process provides one. Do not claim rollback safety from a `down()` method when restoring data would
    require a database backup.

## Validate installation and upgrade separately

Use an isolated disposable database.

Confirm its effective driver and database name without exposing credentials before running a mutating test or migration.

Never use broad refresh, rollback, wipe, or seeding commands against development or shared data.

The accounting harness can wipe its target and requires a disposable name ending in `_test`; a test configuration's
default name is not sufficient evidence.

Cover both:

- the affected clean-install path;
- an upgrade from the preceding schema populated with representative existing rows.

Assert, where applicable:

- data preservation;
- backfill values;
- decimal precision;
- defaults/nullability;
- indexes and constraints;
- branch relationships;
- foreign-key deletion behavior;
- unique/idempotency behavior;
- compatibility with old and new code during the rollout window.

Exercise constraint rejection and idempotency where relevant.

Test reversal/reapplication only when supported and only in the disposable fixture. Do not require destructive rollback
as a production strategy.

Run focused migration or owning-module tests with:

```bash
php artisan test <target>
```

Run Pint on changed PHP.

For engine-specific changes, use the appropriate real-engine fixture. Accounting has examples under:

```text
Modules/Accounting/tests/Feature/ManagementAccounting/
```

and the dedicated gate:

```bash
composer test:accounting:mysql
```

A passing empty-schema creation test does not prove a populated upgrade is safe.

A skipped engine test is not a pass.

## Cross-contract impact

A schema change alone does not require browser or mobile changes.

If the migration changes an externally observable API or payload semantic, also apply the `api-contract-change`
workflow.

Examples include changes that alter:

- field presence or nullability;
- enum/status semantics;
- money precision or representation;
- date/time-zone behavior;
- serialized relationships;
- file/download metadata;
- public boot/settings data;
- notification payloads.

Do not modify `app_driver` or `app_employee` merely because the backend schema changed. First prove that an affected
external contract is consumed by them.

## Completion criteria

Done means:

- the owning module and repository are identified;
- the migration order is safe for existing installations;
- old/new application and worker coexistence was considered;
- clean install and populated upgrade paths were validated appropriately;
- data preservation and backfill behavior were tested;
- engine-specific risks were tested on the relevant engine where required;
- large-table locking/rebuild risk was assessed where applicable;
- optional-module boundaries remain valid;
- financial history and branch ownership are preserved;
- roll-forward recovery is defined;
- `down()` is not misrepresented as safe when it is destructive;
- backup/restore prerequisites are documented for high-risk production changes when applicable;
- externally observable contract changes invoke the API-contract workflow;
- the final result reports migration owner, deployment/backfill order, tested engines and schema/data states, remaining
  locking/data risks, and unexecuted checks.

Preparing migrations does not imply deploying them.
