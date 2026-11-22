---
name: optional-module-integration
description: Add or change Forkiva integrations involving optional modules such as Hr, Delivery, or Accounting. Preserve core boot, contract bindings, routes, schema, browser builds, persisted work, and historical data when an add-on is enabled, disabled, or unavailable.
---

# Optional module integrations

Apply the workspace and backend guides. Paths below are relative to `backend/`. Hr, Delivery, and Accounting are nested
repositories inside the same application; repository separation does not create a separate Laravel runtime or browser
build.

## Map the availability boundary

Identify which module owns the behavior, which is the optional provider, and which callers must continue working without
it.

Read:

- `module.json`;
- module `composer.json`;
- providers;
- `modules_statuses.json`;
- affected configuration;
- affected routes;
- integration contracts and null implementations;
- browser aliases/manifests when frontend code is involved.

Inspect parent and affected nested Git state independently.

Use these source anchors:

- `Modules/Core/app/Support/ModuleFeature.php` checks that a module exists and is enabled through the module registry.
- `Modules/Core/app/Providers/CoreServiceProvider.php` binds integration contracts to an enabled, available
  implementation or a Core null implementation. Current examples include delivery and financial posting/readers under
  `Modules/Core/app/Contracts/` and `Modules/Core/app/Services/`.
- `Modules/Core/tests/Feature/OptionalModuleBindingResolutionTest.php` tests enabled/disabled binding resolution.
- `Modules/Accounting/tests/Feature/OptionalDependencyIsolationTest.php` covers guarded routes, permission/export
  registration, source resolution, and schema independence from Hr/Delivery tables.
- `tsconfig.paths.json`, `scripts/frontend-modules.ts`, and `resources/js/app/bootstrap/collectModules.ts` govern
  browser aliases, source validation, and enabled manifest collection.

Treat these states separately when the integration promises support:

```text
enabled + source present
disabled + source present
source unavailable
```

An enabled module with missing source is a configuration/source-integrity error detected by source checks, not a reason
to quietly disable it.

Treat disablement and physical source removal as different operations. Do not design an uninstall or data-deletion
workflow unless the task explicitly requires one. Disabling an add-on must never imply dropping its tables or deleting
historical records.

## Keep the dependency optional throughout the flow

1. Reuse the existing bridge/interface when it owns the responsibility. Keep concrete add-on implementation in its
   module; keep a cross-domain contract/fallback in Core only when consistent with current ownership. Extend every
   implementation, including null implementations, when changing the contract.

2. Follow the provider's availability guards before resolving optional concrete classes. Do not introduce required
   constructor types, inheritance, eager static calls, model relations, configuration evaluation, or listener
   registration that loads unavailable add-on code during boot. Inspect the full resolution path, not only a guarded
   controller method.

3. Choose fallback semantics deliberately. An unavailable optional capability may return the established empty/no-op
   result or be inaccessible; it must not fabricate successful mandatory posting or suppress a failure from an enabled
   integration. Preserve the caller's expected return shape and failure behavior.

4. Keep provider lifetimes and deferred `provides()` lists correct. Availability and binding tests must resolve fresh
   instances when changing mocked module state; a previously resolved singleton can mask the wrong binding. Avoid
   request-specific mutable state in shared singletons.

5. Trace routes, permissions, exports, translations/public boot data, events, jobs, listeners, commands, and schedules.
   Hide or reject unavailable functionality through the existing server feature gates as well as browser navigation.

6. Inspect queued jobs, serialized events, outbox records, scheduled work, and persisted source references that may
   outlive a module state change. Disabling or removing an optional module must not make workers fail while
   deserializing historical work. Prefer stable Core-owned contracts or source identifiers for cross-module persisted
   work rather than serializing optional implementation classes.

7. Preserve safe handling of historical source references after a module becomes unavailable. Disabling a module may
   prevent new optional behavior according to the product contract, but it must not make historical financial, delivery,
   HR, audit, or source-linked records unreadable or unrecoverable when Core is responsible for displaying, reversing,
   or reconciling them.

8. Inspect migrations, foreign keys, and queries for assumptions about optional tables. Test the promised absence path
   without those tables. Do not “fix” a missing-table failure by creating another module's schema or moving its data
   ownership into Core.

9. Respect schema ownership. A foreign key, backfill query, or direct model dependency can make an otherwise optional
   module mandatory. Use the database-migration workflow when schema design is affected.

10. Inspect browser imports across modules. The manifest collector uses an eager glob before filtering by status:
    disabling navigation alone does not make static imports safe. Shared/Core browser code must not require missing
    add-on source. Keep routes, pages, contracts, and feature UI in their owner and use established integration
    boundaries instead of adding standalone frontend roots or Node installations.

11. Include deployment and cache lifecycle in availability changes. Inspect long-lived queue workers and any cached
    configuration, routes, manifests, boot settings, or frontend metadata that can retain the previous module state. Do
    not assume changing `modules_statuses.json` immediately changes already-running processes.

12. When an availability change affects a public API, response field, boot payload, notification contract, permission
    contract, or other externally observable behavior, also apply the `api-contract-change` workflow. Do not mention or
    modify mobile consumers unless the changed contract is actually consumed by them.

## Verify the supported configurations

Use mocks, dedicated test fixtures, or an isolated checkout for availability scenarios.

Do not remove nested repositories or toggle the user's working module configuration merely to simulate absence.

Disabled-state mocks do not establish missing-source boot or build safety; add isolation coverage when that promise is
affected.

Test, where applicable:

- enabled implementation success;
- enabled implementation failure;
- disabled fallback or access rejection;
- supported missing-module/class behavior;
- Core service resolution;
- route/config/provider registration;
- historical source handling after disablement;
- queued/persisted work after a module state change;
- schema behavior without optional tables;
- browser build/import safety;
- boot/cache/worker behavior after availability changes.

Start with:

```bash
php artisan test Modules/Core/tests/Feature/OptionalModuleBindingResolutionTest.php
```

For Accounting isolation changes, inspect and run applicable cases in:

```text
Modules/Accounting/tests/Feature/OptionalDependencyIsolationTest.php
```

Its schema cases use the disposable engine harness and must follow backend database safeguards.

For browser discovery/import changes, run:

```bash
yarn vitest run scripts/frontend-modules.test.ts
yarn check:modules
yarn type-check
yarn build
```

Also run affected manifest/router tests and scoped PHP/browser formatting checks for edited code.

Source-presence checks alone do not prove a disabled or absent module can compile.

Apply:

- `api-contract-change` when availability changes externally observable contracts;
- `database-migration` when schema ownership or optional-table assumptions change;
- `financial-code-change` when a bridge changes posting, settlement, refund, reconciliation, or financial source
  behavior.

## Completion criteria

Done means:

- the owning module, optional provider, and callers are identified;
- enabled, disabled, and unavailable-source states are distinguished where relevant;
- Core can boot and resolve the affected flow according to the intended availability contract;
- null/fallback behavior does not fabricate success or hide enabled-integration failures;
- optional concrete classes are not loaded through unguarded boot/resolution paths;
- provider lifetimes and deferred bindings remain correct;
- routes, permissions, exports, events, jobs, schedules, and browser manifests remain availability-safe;
- persisted jobs/events/outbox/source references remain safe across module state changes;
- historical records remain readable/recoverable according to Core responsibilities;
- optional tables are not made mandatory through accidental schema/query dependencies;
- disablement does not become an implicit uninstall or data-deletion workflow;
- long-lived workers and cached configuration/boot state were considered;
- browser imports/build behavior were validated where affected;
- public contract changes invoke the API-contract workflow when applicable;
- parent and affected nested repository changes are reported together;
- the final result states which availability configurations were actually exercised and which remain unverified.

Do not claim optional-module safety from an `isEnabled()` check alone.
