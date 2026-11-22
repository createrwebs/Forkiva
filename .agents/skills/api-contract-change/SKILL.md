---
name: api-contract-change
description: Change Forkiva Laravel API routes, validation, serialization, authentication, permissions, or shared payload semantics while preserving Vue and Flutter compatibility. Use when externally observable contracts change, including files, boot data, and notifications.
---

# API contract changes

Apply the workspace and backend guides. Paths below are relative to `backend/`; the Flutter applications are siblings. A
controller implementation change with no observable contract change does not require a cross-client migration.

## Establish the contract and consumers

Trace the owning module's route, middleware, Form Request, controller/service, Resource/Transformer, and tests. Routes
are loaded by `Modules/Core/app/Providers/RouteServiceProvider.php` using `Modules/Core/config/config.php`; there is no
root routes directory.

Record the current and proposed:

- HTTP method and path;
- input fields, types, defaults, and nullability;
- authentication and authorization behavior;
- status codes;
- response envelope and payload shape;
- field semantics;
- enum/status values;
- money/date/file semantics where applicable.

Search by route fragments, payload fields, enum values, permission keys, and domain names across all active clients, not
only the controller's name:

| Consumer                 | Starting points                                                                                                                                           |
|--------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------|
| Browser                  | `resources/js/` and `Modules/*/resources/assets/js/`; follow API builders into contracts, composables, stores, pages, and tests.                          |
| Shared browser transport | `Modules/Core/resources/assets/js/api/http.ts` owns Laravel headers, authentication handling, and the default `/api` base.                                |
| Driver                   | `../app_driver/lib/` and `../app_driver/test/`; inspect `../app_driver/lib/core/network/app_response_mapper.dart` and the feature's DTO/repository.       |
| Employee                 | `../app_employee/lib/` and `../app_employee/test/`; inspect `../app_employee/lib/core/network/app_response_mapper.dart` and the feature's DTO/repository. |

Inspect mobile error mapping, pagination, money parsing, null handling, and unknown enum handling when affected. A
parser that tolerates a missing field may still silently change behavior.

Search notification handlers, public boot/settings, translation consumers, PWA startup behavior, and file/download
consumers when the contract is not a conventional JSON endpoint.

Follow repository instructions and Git boundaries for every consumer that needs edits.

## Choose and implement compatibility deliberately

- Prefer additive fields and compatible defaults. A new required request field, newly nullable response, stricter
  validation, changed decimal/string representation, or added enum value can break existing clients even when the route
  stays the same. Do not silently reinterpret an existing field.
- Do not introduce a new API version merely to avoid designing a compatible migration. Add or increment a contract
  version only when the compatibility boundary is intentional and documented. When deprecating an existing field or
  behavior, keep the transition period explicit and search all active consumers before removal.
- If an incompatible change is required, make its impact explicit before implementation. Update affected active
  consumers in the same task unless a staged migration is specified.
- Account for installed mobile versions that cannot deploy atomically with Laravel. Do not remove compatibility merely
  because local Dart code has been updated. Follow the requested transition, or resolve a material release-sequencing
  decision with the user.
- Preserve the established Form Request contract and translated `availableAttributes()`. Whitelist nested keys and
  consume validated business input.
- The request base's permissive `authorize()` and branch existence rules do not establish permission or ownership. Apply
  the owning route/service authorization and branch/resource scope.
- Keep serialization in the owning Resource/Transformer and `Modules/Support/app/ApiResponse.php`.
- Successful payloads normally use `body`; paginated bodies include `data` and `pagination` and may carry
  filters/extras. Preserve null-versus-absent behavior, relation loading, enum presentation, date formats/time zones,
  and the endpoint's established error structure.
- Preserve HTTP status and error semantics used by clients, including validation, authentication, access denial, missing
  resources, and revision/idempotency conflicts.
- Downloads and streamed files must retain their content type, headers, filename, and error path rather than being
  forced into a JSON envelope.
- When the endpoint participates in an idempotent or replayable operation, preserve the idempotency-key scope, request
  fingerprint/hash semantics, replay status code, canonical response shape, and duplicate-prevention behavior. A
  response-only change can break retries even when the initial request succeeds.
- When changing boot capabilities, cached public settings, translations, or contracts consumed during application
  startup, inspect cache/version invalidation. A server-compatible field is not sufficient if an older PWA or cached
  boot payload can keep clients on the previous contract.
- For money fields, inspect `Modules/Support/app/Money.php` and the owning decimal/currency utilities. Keep currency,
  precision, converted/original amounts, and historical meaning explicit. Use the financial workflow when calculation,
  allocation, posting, settlement, or refund behavior changes.
- Update the existing browser/Dart contract at its ownership boundary. Do not conceal a changed server contract with
  `any`, broad casts, or duplicate payload types.
- Keep UI permission visibility consistent with server enforcement; it never replaces server-side authorization.
- If the affected contract is represented by any generated or maintained API schema in the repository, update and
  validate that schema in the same task. Do not create a new schema system solely for this change.

## Validate at both ends

Run focused backend feature tests that assert the wire contract and meaningful failure cases, not only the service
result.

Cover, where applicable:

- legacy input/default behavior;
- newly added optional or required fields;
- nested invalid input;
- unauthorized branch/resource access;
- validation status and error shape;
- enum additions or removals;
- null-versus-absent behavior;
- money precision/representation;
- date/time-zone representation;
- idempotent replay;
- conflict responses;
- file response headers/content;
- notification payload serialization;
- boot/settings cache-version behavior.

Exercise file and notification payloads through their actual serialization and consumption paths.

Run affected browser tests with:

```bash
yarn vitest run <target>
yarn type-check
```

Then run scoped lint/format checks for changed browser files.

For a changed Flutter consumer, run focused tests from that application's root, followed by:

```bash
flutter analyze
dart format --output=none --set-exit-if-changed lib test
```

Run broader auth/routing/transport checks or builds when shared infrastructure changes.

Use the backend guide's PHP/Pint and engine-specific checks as applicable.

## Completion criteria

Done means:

- the externally observable contract change is explicitly identified;
- every affected active consumer was searched;
- compatibility is preserved or the authorized migration/version boundary is implemented deliberately;
- installed mobile-client release sequencing was considered when relevant;
- request validation, authorization, and branch/resource scope remain correct;
- serialization and error semantics remain deliberate;
- idempotency/replay behavior remains correct where applicable;
- boot/PWA/cache invalidation is handled where applicable;
- file/notification contracts are validated through their real paths where applicable;
- tests exercise both serialization and consumption;
- any maintained/generated API schema is updated when applicable;
- the final result states which clients were checked, which changed, and which release/compatibility limits remain
  unverified.

Do not claim an old deployed client is compatible solely because the updated client passes.
