# Forkiva backend agent guide

Read [the workspace guide](../AGENTS.md) first. This file adds repository-specific implementation rules; more specific
module instructions still apply. Paths below are relative to `backend/`, unless stated otherwise.

## Application and ownership

This is one Laravel 12 modular monolith with an integrated Vue 3/TypeScript browser application, using
`nwidart/laravel-modules`. Composer, Yarn Classic, Vite, PHP tests, and browser tests run from this directory.

| Location                               | Authority / responsibility                                                                                                                                                                                     |
|----------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Modules/<Owner>/`                     | Domain PHP, routes, configuration, migrations, tests, translations, and browser code. Module `composer.json` defines namespaces; normally `Modules\Owner\` maps to `app/`, without an `App` namespace segment. |
| `Modules/<Owner>/resources/assets/js/` | The owner's browser API, contracts, state, composables, pages, components, and manifest.                                                                                                                       |
| `resources/js/`                        | Shared browser entry `resources/js/app.ts` and infrastructure under `resources/js/app/`, including bootstrap, router, and plugins. Shared feature infrastructure also lives in Core's browser source.          |
| `tsconfig.paths.json`                  | Authoritative browser aliases and source ownership.                                                                                                                                                            |
| `scripts/frontend-modules.ts`          | Reads aliases/statuses, checks enabled module sources, and supplies Vite aliases and manifest paths.                                                                                                           |
| `modules_statuses.json`                | Enabled modules; not a replacement for module provider or manifest registration.                                                                                                                               |

Keep feature PHP and Vue in the owning module. Do not recreate standalone frontend roots or module-local Node
installations. Move code into Core only for an actual cross-domain responsibility or infrastructure, not hypothetical
reuse. Follow existing directory casing, including `Partials` and older module-specific layouts.

`Modules/Hr/`, `Modules/Delivery/`, and `Modules/Accounting/` are independent Git repositories ignored by the parent.
For changes in one, inspect status/diffs in both backend and that repository; a clean parent diff does not account for
nested changes.

The backend requires PHP `^8.4`. Use PHP 8.4 language features only when they are compatible with Laravel, installed
tooling, and the project's supported deployment environment. Node requirements and Yarn version are defined in
`package.json`.

## Laravel entry points and contracts

There is no root `routes/` directory. `bootstrap/app.php` configures application middleware, exceptions, and health
routing. `Modules/Core/app/Providers/RouteServiceProvider.php` loads module routes using
`Modules/Core/config/config.php`, normally `routes/web.php` and `routes/api/v1.php` with the `/api/v1` API prefix.
Inspect the actual route group and middleware before adding an endpoint.

Use the established flow:

```text
module route → Form Request → controller → service/interface
             → model/domain persistence → Resource/Transformer → ApiResponse
```

- Keep controllers thin: consume validated business inputs, apply the owning flow's authorization, invoke services, and
  return the existing Resource/Transformer and envelope. Large queries, pricing, accounting, and persistence workflows
  belong outside controllers.
- Extend the established request base in `Modules/Core/app/Http/Requests/Request.php` where appropriate. Implement
  `availableAttributes()` and use its translation helpers. Consume `$request->validated()`; do not bypass a validated
  contract with `all()` or unchecked `input()`. Explicitly whitelist nested keys. Derive or authorize branch, prices,
  taxes, totals, statuses, permissions, and source references server-side.
- The base request's `authorize()` returns true. Validation, including `getBranchRule()`, is not permission or ownership
  authorization.
- `Modules/Support/app/ApiResponse.php` owns success/error envelopes. Paginated results can include `data`,
  `pagination`, `filters`, `default_filters`, and `extras` inside `body`; preserve the endpoint's existing shape. Retain
  enum serialization, Money objects, conditional relations, and date formatting used by its Resource. Eager-load
  required relations; do not replace Resources with raw models.
- API changes affect Vue and potentially both Flutter clients. Apply the workspace consumer search to routes,
  request/response fields, enums, permissions, money, dates, files, and notifications. Prefer additive changes without
  changing existing field meaning; handle an authorized break across consumers or through the task's staged migration.

## Services, models, and module integration

- Use the owner's service/interface layer and constructor or method injection. Register bindings in the module's
  providers; keep deferred providers' `provides()` lists and `module.json` provider registration consistent. Module
  configuration is commonly merged through Core's `WithModuleRegistration` trait.
- Choose lifetime intentionally. Order's mutable `SaveOrder` and pricing context are scoped; a singleton must not retain
  a request's branch, user, cart, or pricing state. Do not add interfaces mechanically to every helper or create a
  service solely because a method is long.
- Models own relationships, casts, persistence scopes, and established domain persistence behavior. Follow
  `Modules/Support/app/Eloquent/Model.php` and the owner's custom builders/traits where used. Preserve soft deletion,
  auditing, model events, branch scope, and transaction semantics. Keep unrelated application workflows out of models.
- Prefer existing enums, typed value objects, relations, and stable contracts to repeated magic strings or loosely
  shaped arrays. Name responsibilities by domain, such as `PaymentAttempt` or `BranchAccess`; avoid `Stage1Service`,
  `NewManager`, and generic `Helper`/`Utils` containers.
- Core's provider binds optional integrations through contracts and null implementations, guarded by
  `ModuleFeature::enabled()` and class availability. Preserve these bridges for Hr, Delivery, and Accounting. Enabled
  local add-ons are not evidence that core may require their classes unconditionally. Cover the disabled/unavailable
  path when changing an integration.

## PHP formatting and style

Pint is the PHP formatting authority. There is currently no repository `pint.json`; use the installed default Laravel
preset, together with `.editorconfig`'s four-space PHP indentation. Follow Laravel where this guide has no more specific
rule. Do not change formatter configuration or fight its output for a local task.

- Declare parameter, return, and property types where framework/inheritance contracts permit. Prefer strict comparisons,
  for example `$status === OrderStatus::Paid`.
- Put each logical item of a multiline array on its own line. Expand arrays with several domain fields, including
  `$fillable`, validation/configuration arrays, and casts. Do not compress unrelated entries onto one line.
- Use cohesive methods and guard clauses when they clarify state. Extract meaningful domain operations, reusable
  behavior, separately testable decisions, or complex named transformations; do not split mechanically by line count.
- Use normal imports, matching namespace/filesystem casing, and remove unused imports. Let Pint handle import order and
  spacing.

```php
protected function casts(): array
{
    return [
        'revision' => 'integer',
        'snapshot' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
```

These are rules for new and touched code, not a claim that every legacy file already complies. Avoid unrelated style
rewrites.

## PHPDoc and comments

- Explain why, a contract, an invariant, or a non-obvious compatibility/security/financial constraint. Do not narrate
  fetching a model or returning a response.
- Use multiline narrative method PHPDoc. For CRUD methods, describe the operation or important condition, such as
  updating only the expected revision. Omit tags that merely repeat native parameter/return types; retain useful shapes,
  generics, and exceptions.
- When an interface already documents the contract, use `/** {@inheritDoc} */` instead of copying its documentation.
  This single-line exception is valid for an actual implementation/inherited contract; Pint removes inheritance markers
  from classes that do not inherit a contract. Do not restore markers removed by the formatter without checking their
  purpose.
- Do not add a narrative docblock to every class. Keep necessary structural annotations, including Eloquent `@property`/
  `@property-read`, templates/generics, and framework/tooling annotations.
- Comment non-obvious lock order, idempotency, rounding conservation, refund ceilings, immutable snapshots, and optional
  integrations at the invariant. Avoid a comment for each statement.
- A necessary TODO must state what remains and why it cannot be completed now, with an issue/constraint reference when
  available. No vague TODO/FIXME/TEMP/HACK markers or comments substituting for requested implementation.

```php
/**
 * Update the rule only when its stored revision matches the submitted revision.
 */
```

## Logging

- Use structured logs for operationally significant failures, retries, recovery paths, and state mismatches.
- Include stable diagnostic context when useful, such as branch, order, payment attempt, source, job, or module
  identifiers.
- Never log access tokens, passwords, Gift Card secrets, private keys, signing credentials, PAN/CVV, complete payment
  credentials, session secrets, or full sensitive request/response bodies.
- Do not use logs as the source of truth for financial, audit, idempotency, or reconciliation state. Persist required
  business evidence in the owning domain records.
- Avoid noisy informational logging in hot loops, pricing evaluation, polling, high-frequency jobs, or frequently
  executed model events.
- When logging an exception, preserve enough context to diagnose the failing operation without duplicating sensitive
  payloads.
- Use the existing logging channels and conventions before introducing a new channel or external logging dependency.

## Database, concurrency, and money

Domain migrations and factories live under the owner's `database/migrations/` and `database/factories/`; root
`database/migrations/` also contains installation infrastructure. Follow nearby timestamped anonymous migrations and
model/factory conventions. Add migrations for released schema evolution, supporting existing rows and roll-forward
deployment. Preserve decimal precision, branch ownership, and appropriate foreign keys/indexes; name indexes explicitly
when necessary rather than inventing a naming scheme.

Inspect Blueprint macros in `Modules/Support/app/Providers/SupportServiceProvider.php` before using them. In particular,
`branch()` changes deletion behavior depending on its nullable argument; do not accidentally introduce cascading
deletion of financial history. Use local constraints for local invariants, unique constraints for durable idempotency,
and transactions for cross-table invariants. Correct posted history with reversal/correction records.

Before changing orders, payments, refunds, invoices, POS sessions, gift cards, delivery settlements, accounting, or HR
payouts, trace the existing transaction, locking, and retry contract. Lock related rows in deterministic order, re-check
mutable conditions after locking, preserve replay semantics, and retain revision/conflict responses rather than
overwriting newer state. Keep external network calls outside transactions unless the established architecture requires
otherwise. Do not introduce a global locking framework for a local change.

Inspect `Modules/Support/app/Money.php` and the owning decimal utilities before implementation. `Money` currently wraps
numeric amounts and uses floating-point arithmetic; it is not proof of exact calculation safety. Preserve decimal
strings for exact arithmetic, using the established `Brick\Math\BigDecimal` patterns where decisions require precision.
Never use binary floating-point comparisons for financial decisions. Use currency subunit precision, not a universal two
decimals. Name net, tax, gross, base, due, refund, and collected amounts distinctly. Preserve historical
currency/rate/tax/pricing snapshots and deterministic allocation so totals reconcile.

Use [financial-code-change](.agents/skills/financial-code-change/SKILL.md) for changes to financial calculations,
posting, settlement, refunds, or concurrent financial mutations; it contains the focused source map and validation
workflow.

## Branch access and permissions

Use the established `Modules/Branch/app/Services/BranchAccess/BranchAccessService.php`, resource queries, and
route/service permission checks. Check both actor permission and resource ownership; submitted `branch_id` or a hidden
UI action proves neither. Consult `Modules/User/app/Enums/PermissionAction.php` and the owning route group when changing
permissions.

`Modules/Branch/app/Traits/HasBranch.php` applies its permission scope only when an authenticated user exists.
Background execution does not automatically inherit an HTTP branch boundary. Check scope bypasses, soft-deleted
resources, and explicit branch context in jobs/commands; do not assume an `exists` validator authorizes access.

## Jobs and scheduled work

Keep jobs, listeners, commands, and schedules in the owning module. Inspect its retry/backoff/timeout and failure-state
conventions rather than applying universal values. Inventory's `ProcessInventoryStockSyncJob` demonstrates per-record
overlap protection, dispatch tokens, configurable backoff, and terminal failure handling. Retries must not duplicate
business mutations; align worker timeout, retry interval, and overlap-lock expiry.

`config/queue.php` does not enable `after_commit` globally. Dispatch after commit where a job requires committed state;
do not assume worker timing protects it. Carry necessary actor/branch/locale context explicitly. Export's
`GenerateExportJob` restores user context and clears guards in `finally`; preserve cleanup in long-lived workers.
Persist/report failures and rethrow when needed for queue retries instead of silently leaving partial business state.

Schedules are registered by module providers and may use `onOneServer()`/`withoutOverlapping()`. Preserve those
coordination guarantees. Do not move synchronous critical financial persistence, including invoice creation from
order-paid flows, into a queue merely for speed.

## Vue and TypeScript

Use the existing Vue 3 Composition API with TypeScript, Vuetify, Pinia, Vue Router, Vue I18n, and Vitest. Follow
adjacent `<script setup lang="ts">` components and the owner's directory structure:

| Area                    | Responsibility                                      |
|-------------------------|-----------------------------------------------------|
| `api/`                  | Thin typed HTTP request builders.                   |
| `contracts/`            | Owning module's API/domain payload and state types. |
| `composables/`          | Feature orchestration and reusable behavior.        |
| `stores/`               | Shared or persistent application state.             |
| `pages/`, `components/` | Route-level UI and reusable presentation.           |
| `manifest.ts`           | Routes/navigation and module integration.           |

Use `Modules/Core/resources/assets/js/api/http.ts` for Laravel feature requests. It owns the `/api` default, bearer
token, locale/client headers, and authentication error handling. Follow the endpoint's existing `response.data.body`
unwrapping boundary. Do not add another Axios instance for feature requests. Explicit external transports, such as the
Qintrix printing agent, have different contracts and remain separate.

`resources/js/app/bootstrap/collectModules.ts` collects enabled manifests. The app router assembles module routes and
auth/permission/feature middleware; editing a page alone does not register a route. Vite's router declaration generator
is not the authority for feature routing. Use module translation keys and maintain supported locale files; do not
hard-code user-facing text or assume browser locale equals receipt locale.

### TypeScript and component style

- Use stable, owned contracts instead of `any`; narrow untrusted data and caught errors from `unknown`. Model nullable
  state explicitly, use strict equality, and use discriminated unions/enums when they clarify valid states. Avoid broad
  assertions and `@ts-ignore` for representable contracts. Do not duplicate API payload types without a real
  transformation boundary.
- Keep large HTTP/business workflows in existing composables/services/stores, with UI components focused on presentation
  and interaction. Reuse shared Core components and Vuetify patterns. Preserve reactive disposal, revision guards, and
  protection from stale asynchronous responses, especially in cart/checkout flows.
- ESLint uses `eslint.config.ts` and includes nested module code despite Git ignore rules. Prettier owns browser
  formatting: two spaces, no semicolons, single quotes, trailing commas, print width 100, and one template attribute per
  line. Format only changed files; existing `any` and loose comparisons are not the target standard.
- Comments should explain non-obvious state or timing constraints, such as ignoring an older quote response. Do not
  narrate function calls or retain large commented-out obsolete implementations. Functional fixes do not authorize
  unrelated visual redesigns.

## Validation and generated output

Run commands from `backend/`. Begin with affected tests, then broaden for shared HTTP/auth/routing, manifests, global
state, money contracts, and build configuration.

| Change                                | Checks                                                                                                                                                                              |
|---------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| PHP behavior                          | `php artisan test <target>`; `./vendor/bin/pint --test <changed-paths>`; `composer test` when broader coverage is warranted. Full PHP formatting check: `./vendor/bin/pint --test`. |
| Browser behavior                      | `yarn vitest run <target>`; `yarn type-check`; scoped ESLint/Prettier through the installed tools. Repository checks: `yarn test`, `yarn lint`, `yarn format:check`.                |
| Module registration/aliases           | `yarn check:modules`, relevant manifest/router tests, and `yarn build`. The module check verifies source presence, not business contracts or every disabled-module path.            |
| Browser compilation/bundling          | `yarn build` runs Vite and type-checking; `build-only` omits type-checking.                                                                                                         |
| Accounting/engine-sensitive mutations | Applicable focused tests plus `composer test:accounting:mysql` under the disposable database requirements below.                                                                    |

`phpunit.xml` discovers module Unit/Feature tests and uses SQLite `:memory:`, array-backed test services, and
synchronous queues. Module feature fixtures often load selected migrations explicitly; extend the relevant fixture
instead of imposing a new universal test bootstrap. Browser tests are colocated `*.test.ts` files in shared/module
source and `scripts/`, using Vitest/jsdom. Test observable behavior and changed invariants.

`phpunit.mysql.xml` and `tests/Concerns/InteractsWithAccountingMySql.php` support the dedicated accounting gates. They
can wipe/rebuild the target database. Verify the effective connection is disposable and its name ends in `_test` before
running; the file's default name alone is insufficient. Fork-based concurrency tests need `pcntl`. SQLite success cannot
establish MySQL locking or constraint behavior. Report unrun gates and blockers precisely.

Do not hand-edit generated dependencies/assets. In addition to workspace exclusions, this includes
`resources/js/auto-imports.d.ts`, `resources/js/components.d.ts`, `resources/js/typed-router.d.ts`,
`.eslintrc-auto-import.json`, generated Iconify `resources/js/app/plugins/iconify/icons.css`, and TypeScript build info
under `node_modules/.tmp/`. Update their source/configuration and regenerate only as needed (`yarn icons:build` for
icons). Re-check parent and affected nested diffs after tooling.

For receipt, PDF, or print-agent behavior, use [printing-change](.agents/skills/printing-change/SKILL.md); ordinary
API/UI changes do not need that workflow.
