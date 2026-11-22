# Forkiva backend agent guide

Read [the workspace guide](../AGENTS.md) first. This file adds repository-specific implementation rules; more specific module instructions still apply. Paths below are relative to `backend/`, unless stated otherwise.

## Application and ownership

This is one Laravel 12 modular monolith with an integrated Vue 3/TypeScript browser application, using `nwidart/laravel-modules`. Composer, Yarn Classic, Vite, PHP tests, and browser tests run from this directory.

| Location                               | Authority / responsibility                                                                                                                                                                                     |
| -------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Modules/<Owner>/`                     | Domain PHP, routes, configuration, migrations, tests, translations, and browser code. Module `composer.json` defines namespaces; normally `Modules\Owner\` maps to `app/`, without an `App` namespace segment. |
| `Modules/<Owner>/resources/assets/js/` | The owner's browser API, contracts, state, composables, pages, components, and manifest.                                                                                                                       |
| `resources/js/`                        | Shared browser entry `resources/js/app.ts` and infrastructure under `resources/js/app/`, including bootstrap, router, and plugins. Shared feature infrastructure also lives in Core's browser source.          |
| `tsconfig.paths.json`                  | Authoritative browser aliases and source ownership.                                                                                                                                                            |
| `scripts/frontend-modules.ts`          | Reads aliases/statuses, checks enabled module sources, and supplies Vite aliases and manifest paths.                                                                                                           |
| `modules_statuses.json`                | Enabled modules; not a replacement for module provider or manifest registration.                                                                                                                               |

Keep feature PHP and Vue in the owning module. Do not recreate standalone frontend roots or module-local Node installations. Move code into Core only for an actual cross-domain responsibility or infrastructure, not hypothetical reuse. Follow existing directory casing, including `Partials` and older module-specific layouts.

`Modules/Hr/`, `Modules/Delivery/`, and `Modules/Accounting/` are independent Git repositories ignored by the parent. For changes in one, inspect status/diffs in both backend and that repository; a clean parent diff does not account for nested changes.

`composer.json` currently declares PHP `^8.2`, although the workspace guide names PHP 8.4. Use syntax supported by the intended runtime; verify the supported deployment range before introducing PHP 8.4-only features. Do not silently change the manifest to resolve this discrepancy. Node requirements and Yarn version are in `package.json`.

## Laravel entry points and contracts

There is no root `routes/` directory. `bootstrap/app.php` configures application middleware, exceptions, and health routing. `Modules/Core/app/Providers/RouteServiceProvider.php` loads module routes using `Modules/Core/config/config.php`, normally `routes/web.php` and `routes/api/v1.php` with the `/api/v1` API prefix. Inspect the actual route group and middleware before adding an endpoint.

Use the established flow:

```text
module route → Form Request → controller → service/interface
             → model/domain persistence → Resource/Transformer → ApiResponse
```

- Keep controllers thin: consume validated business inputs, apply the owning flow's authorization, invoke services, and return the existing Resource/Transformer and envelope. Large queries, pricing, accounting, and persistence workflows belong outside controllers.
- Extend the established request base in `Modules/Core/app/Http/Requests/Request.php` where appropriate. Implement `availableAttributes()` and use its translation helpers. Consume `$request->validated()`; do not bypass a validated contract with `all()` or unchecked `input()`. Explicitly whitelist nested keys. Derive or authorize branch, prices, taxes, totals, statuses, permissions, and source references server-side.
- The base request's `authorize()` returns true. Validation, including `getBranchRule()`, is not permission or ownership authorization.
- `Modules/Support/app/ApiResponse.php` owns success/error envelopes. Paginated results can include `data`, `pagination`, `filters`, `default_filters`, and `extras` inside `body`; preserve the endpoint's existing shape. Retain enum serialization, Money objects, conditional relations, and date formatting used by its Resource. Eager-load required relations; do not replace Resources with raw models.
- API changes affect Vue and potentially both Flutter clients. Apply the workspace consumer search to routes, request/response fields, enums, permissions, money, dates, files, and notifications. Prefer additive changes without changing existing field meaning; handle an authorized break across consumers or through the task's staged migration.

For an externally observable contract change, use [api-contract-change](.agents/skills/api-contract-change/SKILL.md) to trace consumers and validate compatibility at both ends.

## Services, models, and module integration

- Use the owner's service/interface layer and constructor or method injection. Register bindings in the module's providers; keep deferred providers' `provides()` lists and `module.json` provider registration consistent. Module configuration is commonly merged through Core's `WithModuleRegistration` trait.
- Choose lifetime intentionally. Order's mutable `SaveOrder` and pricing context are scoped; a singleton must not retain a request's branch, user, cart, or pricing state. Do not add interfaces mechanically to every helper or create a service solely because a method is long.
- Models own relationships, casts, persistence scopes, and established domain persistence behavior. Follow `Modules/Support/app/Eloquent/Model.php` and the owner's custom builders/traits where used. Preserve soft deletion, auditing, model events, branch scope, and transaction semantics. Keep unrelated application workflows out of models.
- Prefer existing enums, typed value objects, relations, and stable contracts to repeated magic strings or loosely shaped arrays. Name responsibilities by domain, such as `PaymentAttempt` or `BranchAccess`; avoid `Stage1Service`, `NewManager`, and generic `Helper`/`Utils` containers.
- Core's provider binds optional integrations through contracts and null implementations, guarded by `ModuleFeature::enabled()` and class availability. Preserve these bridges for Hr, Delivery, and Accounting. Enabled local add-ons are not evidence that core may require their classes unconditionally. Cover the disabled/unavailable path when changing an integration.

Use [optional-module-integration](.agents/skills/optional-module-integration/SKILL.md) when changing add-on dependencies, bridges, or availability behavior across PHP, schema, and browser code.

## Code style rules

Apply these rules directly to new and touched code throughout this application, including nested module repositories. Existing files may contain exceptions; do not copy accidental blank lines, loose comparisons, redundant comments, or untyped payloads. Avoid unrelated style rewrites.

Formatting authority is `.editorconfig` for base whitespace, Pint's installed Laravel preset for PHP, and `.prettierrc` for files formatted by Prettier. `eslint.config.ts` supplies browser lint rules and disables conflicting stylistic rules. Formatter output takes precedence over hand-alignment or a legacy example. The grouping and comment-writing rules below also require human review; formatters do not enforce every one of them. Do not change configuration or add formatter-ignore directives to make a local edit pass.

### File whitespace

- Use UTF-8, LF line endings, spaces rather than tab characters, and exactly one terminating newline at the end of a text file. Do not leave additional empty lines at EOF.
- Blank lines contain zero spaces or tabs. Remove trailing whitespace from code. `.editorconfig` exempts Markdown from automatic trailing-whitespace removal because two trailing spaces can express a deliberate hard break.
- Indent PHP by **4 spaces** per level. Indent JS, MJS, TS, Vue, SCSS, CSS, YAML, and YML by **2 spaces** per level. The explicit `docker-compose.yml` override is **4 spaces**.
- Other files use `.editorconfig`'s **4-space** fallback unless their owning formatter overrides it. Prettier explicitly uses **2 spaces**, including frontend JSON and `package.json`; do not run it over Composer manifests just to normalize their existing four-space layout.
- Use **one blank line** to separate logical sections; do not add two or more consecutive blank lines. Keep related statements together rather than placing an empty line after every statement.
- Do not use repeated spaces to align assignments, array arrows, object values, imports, or end-of-line comments into columns. Indentation and formatter-controlled PHPDoc spacing are exceptions.

## PHP formatting and style

There is currently no repository `pint.json`; use the installed default Laravel preset. Do not describe this as a generic PSR-12 configuration: Laravel's spacing for `!`, string concatenation, and empty bodies is significant. Pint does not impose the browser's 100-column print width on PHP.

### PHP spaces and punctuation

- Begin a PHP-only file with `<?php`, then **one blank line**. Omit the closing `?>`. Follow the file's existing strict-types declaration policy; adding `declare(strict_types=1)` is a behavior decision, not formatting.
- Write one namespace import per statement. Pint sorts imports alphabetically within its `const`, class, and function groups, with **one blank line** between groups. Use **one blank line** after the namespace and after the final import, before the declaration or its PHPDoc. Remove unused imports; match namespace/filesystem casing.
- Use **one space** after control keywords: `if ($ready)`, `foreach ($items as $item)`, `catch (Throwable $exception)`. Use **zero spaces** inside parentheses, inside single-line array brackets, or between a method/function name and `(`.
- Use **one space** after commas, around assignment/comparison/binary operators and `=>`, and around ternary `?` / `:`. Named arguments use **zero spaces before** `:` and **one after**: `body: $resource`.
- PHP-specific exceptions: **one space** after logical negation (`! $ready`, `! isset($data['id'])`) and casts (`(int) $id`); **zero spaces** around concatenation (`'branch-'.$id`), `->`, `?->`, and `::`.
- Write types as `?int`, `int|string`, and `array $data`. Use **zero spaces before** a return-type colon and **one after**: `public function resolve(?int $id = null): ?int`.
- Prefer single-quoted literal strings; use double quotes for interpolation or required escape sequences. End PHP statements with `;`, with **zero spaces before** it. Use short arrays `[]`.
- Multiline arrays have one logical entry per line and a trailing comma on the last entry. Single-line arrays have no trailing comma. The installed Pint preset enforces trailing commas for multiline **arrays**, not every multiline parameter or argument list; preserve the surrounding convention for calls/signatures.

### PHP braces, wrapping, and blank lines

- Class/interface/trait/enum bodies and nonempty named methods/functions normally open `{` on the next line. For a multiline signature ending on its own line, Pint uses `) {`. Control structures and closures open on the same line: `if (...) {`, `function () use ($data) {`. Continue with `} else {`, `} elseif (...) {`, `} catch (...) {`, and `} finally {`.
- Empty method bodies stay compact: `public function __construct(private Pricing $pricing) {}` or `) {}` after multiline promoted parameters. Do not expand an empty constructor just to add blank lines.
- Indent body contents **4 spaces** beyond their enclosing declaration/block. Align the closing brace with its opening declaration. In new code, leave **zero blank lines** immediately inside a body at its beginning or end.
- Separate methods, properties, and constants with **one blank line**. Keep a member's PHPDoc and attributes attached to that member with **zero blank lines** between them. Keep related trait imports together, following the surrounding trait declaration form.
- Put **one blank line** before `return` or `continue` when another statement precedes it in the same block. Do not add one when it is the first statement in the block; an attached explanatory comment stays with its statement. Do not insert a blank line after `throw` before its closing brace.
- Separate distinct phases such as loading, validation, mutation, event dispatch, and the final result by **one blank line** where they are separate logical groups. This is a writing convention, not a requirement to separate every assignment or every `if`.
- Break long fluent queries at method boundaries: keep the root expression on its first line and indent each following `->method()` by **4 additional spaces**. Nested callback bodies add their own indentation level.
- Expand arrays with several domain fields, including `$fillable`, validation/configuration arrays, and casts. A short list such as `['name']` or a compact repeated descriptor may stay inline; do not pack separate outer entries onto one line.
- When a signature/call is expanded, put each argument on its own line, indent it one level, and align the closing `)` with the start of the call/declaration. Let Pint handle nested expressions; do not add spaces to align argument values.

### PHP implementation conventions

- Use PascalCase class/interface/trait/enum names, camelCase methods/local variables, and UPPER_SNAKE_CASE constants. Preserve existing Eloquent/database/request field names such as `branch_id`; naming rules do not authorize wire-contract renames. PHPUnit test methods follow Pint's snake_case convention.
- Declare parameter, return, and property types where framework/inheritance contracts permit. Prefer strict comparisons, for example `$status === OrderStatus::Paid`. Do not rewrite an existing comparison without checking its value types and behavior.
- Use cohesive methods and guard clauses when they clarify state. Extract meaningful domain operations, reusable behavior, separately testable decisions, or complex named transformations; do not split mechanically by line count.

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

## PHPDoc and comments

- Explain why, a contract, an invariant, or a non-obvious compatibility/security/financial constraint. Do not narrate fetching a model or returning a response.
- Use multiline narrative method PHPDoc. For CRUD methods, describe the operation or important condition, such as updating only the expected revision. Omit tags that merely repeat native parameter/return types; retain useful shapes, generics, and exceptions.
- When an interface already documents the contract, use `/** {@inheritDoc} */` instead of copying its documentation. This single-line exception is valid for an actual implementation/inherited contract; Pint removes inheritance markers from classes that do not inherit a contract. Do not restore markers removed by the formatter without checking their purpose.
- Do not add a narrative docblock to every class. Keep necessary structural annotations, including Eloquent `@property`/`@property-read`, templates/generics, and framework/tooling annotations.
- Comment non-obvious lock order, idempotency, rounding conservation, refund ceilings, immutable snapshots, and optional integrations at the invariant. Avoid a comment for each statement.
- A necessary TODO must state what remains and why it cannot be completed now, with an issue/constraint reference when available. No vague TODO/FIXME/TEMP/HACK markers or comments substituting for requested implementation.
- Align the opening `/**` with the declaration. Indent each interior `*` and the closing `*/` **one additional space**, so the asterisks line up. Write **one space** after an interior `*` before text; an empty PHPDoc line ends at `*`. Put **one empty PHPDoc line** between the narrative and tags; order useful tags as `@param`, `@return`, then `@throws`, allowing Pint to separate tag groups.
- Pint left-aligns PHPDoc tags and uses **two spaces** for `@param` separators, for example `@param  array<string, mixed>  $data`. Do not manually column-align types or variable names. Preserve useful one-line type hints such as `/** @var Branch $branch */` directly above the expression they describe.
- Write `// Explanation.` with **one space** after `//`, aligned with the following statement. Put explanatory comments on their own line immediately above the relevant code. Use clear English and sentence case; no decorative separator banners, repeated punctuation headings, author/date logs, or commented-out obsolete code.

```php
/**
 * Update the rule only when its stored revision matches the submitted revision.
 */
```

## Database, concurrency, and money

Domain migrations and factories live under the owner's `database/migrations/` and `database/factories/`; root `database/migrations/` also contains installation infrastructure. Follow nearby timestamped anonymous migrations and model/factory conventions. Add migrations for released schema evolution, supporting existing rows and roll-forward deployment. Preserve decimal precision, branch ownership, and appropriate foreign keys/indexes; name indexes explicitly when necessary rather than inventing a naming scheme.

Inspect Blueprint macros in `Modules/Support/app/Providers/SupportServiceProvider.php` before using them. In particular, `branch()` changes deletion behavior depending on its nullable argument; do not accidentally introduce cascading deletion of financial history. Use local constraints for local invariants, unique constraints for durable idempotency, and transactions for cross-table invariants. Correct posted history with reversal/correction records.

Before changing orders, payments, refunds, invoices, POS sessions, gift cards, delivery settlements, accounting, or HR payouts, trace the existing transaction, locking, and retry contract. Lock related rows in deterministic order, re-check mutable conditions after locking, preserve replay semantics, and retain revision/conflict responses rather than overwriting newer state. Keep external network calls outside transactions unless the established architecture requires otherwise. Do not introduce a global locking framework for a local change.

Inspect `Modules/Support/app/Money.php` and the owning decimal utilities before implementation. `Money` currently wraps numeric amounts and uses floating-point arithmetic; it is not proof of exact calculation safety. Preserve decimal strings for exact arithmetic, using the established `Brick\Math\BigDecimal` patterns where decisions require precision. Never use binary floating-point comparisons for financial decisions. Use currency subunit precision, not a universal two decimals. Name net, tax, gross, base, due, refund, and collected amounts distinctly. Preserve historical currency/rate/tax/pricing snapshots and deterministic allocation so totals reconcile.

Use [database-migration](.agents/skills/database-migration/SKILL.md) for schema evolution and backfills, including populated upgrades and engine-specific validation. Use [financial-code-change](.agents/skills/financial-code-change/SKILL.md) for changes to financial calculations, posting, settlement, refunds, or concurrent financial mutations; it contains the focused source map and validation workflow.

## Branch access and permissions

Use the established `Modules/Branch/app/Services/BranchAccess/BranchAccessService.php`, resource queries, and route/service permission checks. Check both actor permission and resource ownership; submitted `branch_id` or a hidden UI action proves neither. Consult `Modules/User/app/Enums/PermissionAction.php` and the owning route group when changing permissions.

`Modules/Branch/app/Traits/HasBranch.php` applies its permission scope only when an authenticated user exists. Background execution does not automatically inherit an HTTP branch boundary. Check scope bypasses, soft-deleted resources, and explicit branch context in jobs/commands; do not assume an `exists` validator authorizes access.

## Jobs and scheduled work

Keep jobs, listeners, commands, and schedules in the owning module. Inspect its retry/backoff/timeout and failure-state conventions rather than applying universal values. Inventory's `ProcessInventoryStockSyncJob` demonstrates per-record overlap protection, dispatch tokens, configurable backoff, and terminal failure handling. Retries must not duplicate business mutations; align worker timeout, retry interval, and overlap-lock expiry.

`config/queue.php` does not enable `after_commit` globally. Dispatch after commit where a job requires committed state; do not assume worker timing protects it. Carry necessary actor/branch/locale context explicitly. Export's `GenerateExportJob` restores user context and clears guards in `finally`; preserve cleanup in long-lived workers. Persist/report failures and rethrow when needed for queue retries instead of silently leaving partial business state.

Schedules are registered by module providers and may use `onOneServer()`/`withoutOverlapping()`. Preserve those coordination guarantees. Do not move synchronous critical financial persistence, including invoice creation from order-paid flows, into a queue merely for speed.

## Vue and TypeScript

Use the existing Vue 3 Composition API with TypeScript, Vuetify, Pinia, Vue Router, Vue I18n, and Vitest. Follow adjacent `<script setup lang="ts">` components and the owner's directory structure:

| Area                    | Responsibility                                      |
| ----------------------- | --------------------------------------------------- |
| `api/`                  | Thin typed HTTP request builders.                   |
| `contracts/`            | Owning module's API/domain payload and state types. |
| `composables/`          | Feature orchestration and reusable behavior.        |
| `stores/`               | Shared or persistent application state.             |
| `pages/`, `components/` | Route-level UI and reusable presentation.           |
| `manifest.ts`           | Routes/navigation and module integration.           |

Use `Modules/Core/resources/assets/js/api/http.ts` for Laravel feature requests. It owns the `/api` default, bearer token, locale/client headers, and authentication error handling. Follow the endpoint's existing `response.data.body` unwrapping boundary. Do not add another Axios instance for feature requests. Explicit external transports, such as the Qintrix printing agent, have different contracts and remain separate.

`resources/js/app/bootstrap/collectModules.ts` collects enabled manifests. The app router assembles module routes and auth/permission/feature middleware; editing a page alone does not register a route. Vite's router declaration generator is not the authority for feature routing. Use module translation keys and maintain supported locale files; do not hard-code user-facing text or assume browser locale equals receipt locale.

### TypeScript and component style

- Use stable, owned contracts instead of `any`; narrow untrusted data and caught errors from `unknown`. Model nullable state explicitly, use strict equality, and use discriminated unions/enums when they clarify valid states. Avoid broad assertions and `@ts-ignore` for representable contracts. Do not duplicate API payload types without a real transformation boundary.
- Keep large HTTP/business workflows in existing composables/services/stores, with UI components focused on presentation and interaction. Reuse shared Core components and Vuetify patterns. Preserve reactive disposal, revision guards, and protection from stale asynchronous responses, especially in cart/checkout flows.
- ESLint uses `eslint.config.ts` and includes nested module code despite Git ignore rules. Prettier owns browser formatting as specified below. Format only changed files; existing `any` and loose comparisons are not the target standard.
- Comments should explain non-obvious state or timing constraints, such as ignoring an older quote response. Do not narrate function calls or retain large commented-out obsolete implementations. Functional fixes do not authorize unrelated visual redesigns.

### Browser spaces, wrapping, and blank lines

- `.prettierrc` sets `tabWidth: 2`, `semi: false`, `singleQuote: true`, `trailingComma: 'all'`, `printWidth: 100`, `singleAttributePerLine: true`, and `htmlWhitespaceSensitivity: 'ignore'`. Use the installed version and repository configuration. The 100-column width is a wrapping target, not a requirement to split every long string or URL.
- Use **2 spaces** per block/object/array continuation level. Use **one space** around `=`, `===`, `=>`, binary operators, and union `|`; after commas and type/property colons; and inside object/destructuring/import braces: `{ id, name }`. Use **zero spaces** inside call parentheses and array brackets: `show(id)`, `[id, name]`.
- Unlike PHP, JavaScript negation has **zero spaces** after `!`: `!loading.value`. Use **zero spaces** around `.`, `?.`, and before type/property colons: `id: number`, `id?: number`. Arrow parameters retain parentheses: `(value) => value.id`.
- Use single-quoted JS/TS strings unless Prettier chooses another quote to avoid escapes; use backticks for interpolation. Omit statement-ending JS/TS semicolons, but retain any leading semicolon Prettier needs for automatic-semicolon-insertion safety. Multiline arrays, objects, parameters, and arguments receive trailing commas where the syntax allows them.
- JS/TS braces open on the same line as functions and control statements. Empty bodies are `{}`. Use braces for new multiline control bodies; existing compact single-line guards may remain. ESLint currently disables `curly`, so do not claim all brace choices are enforced.
- Keep imports at the top, then **one blank line** before declarations or executable code. Follow nearby import grouping and type-import usage; do not invent a sorting rule or reorder side-effect imports. Prettier does not sort imports.
- Separate top-level functions/types and distinct setup sections (props/emits, injected services, state, derived state, handlers, watchers/hooks) with **one blank line**. Keep related declarations contiguous. Do not reorder initialization or reactive work merely to match a section list.
- Leave **zero blank lines** immediately inside a function/block. Use **one blank line** between distinct phases inside a function, without padding every guard or return. Prettier preserves many authored blank lines; these grouping rules need review in addition to `--check`.
- Use PascalCase component/type names, camelCase variables/functions, and the owner's existing file names (`*.api.ts`, `use*.ts`, `*.test.ts`, `Partials/`, etc.). Preserve snake_case API fields. Do not rename files, routes, or payload keys for style alone.

### Vue templates and CSS/SCSS

- Use this SFC order: `<script lang="ts" setup>`, `<template>`, then an optional `<style lang="scss" scoped>`. Preserve an existing equivalent `<script setup lang="ts">` attribute order. Separate top-level SFC blocks with exactly **one blank line**.
- Script statements begin at column 1 inside `<script>`; do not indent the entire script body. Template content starts **2 spaces** inside `<template>` and gains **2 spaces** for each nested element. CSS selectors start at column 1 inside `<style>`, with declarations indented **2 spaces**.
- Use double quotes for HTML/Vue attribute values and **one space** inside interpolation delimiters: `{{ label }}`. Use Vue shorthand `:prop`, `@event`, and `#slot` consistent with surrounding code, and PascalCase for imported/Vuetify components.
- A short element with one attribute can stay on one line. With multiple attributes, put each attribute on its own line, **2 spaces** beyond the opening tag; put the closing `>` or `/>` on its own line aligned with `<Tag`. An inline self-closing tag has **one space** before `/>`, as in `<slot />`. Let Prettier handle long attribute expressions.
- Attribute sorting is disabled in ESLint; preserve logical/existing order, especially where bindings can affect behavior. Do not claim an enforced directive/prop/event order.
- Keep related child elements together. Use at most **one blank line** between distinct template sections; do not pad every tag. Avoid unnecessary blank lines immediately after an opening tag or before its closing tag.
- CSS/SCSS uses **one space** before `{`, **zero spaces before** a declaration colon and **one after**, and a terminating semicolon on each declaration. Put **one blank line** between separate rule blocks. The JS `semi: false` setting does not remove CSS semicolons or alter JSON's required double quotes.
- Use `// Explanation.` for JS/TS/SCSS, `/* Explanation. */` for CSS, and `<!-- Explanation. -->` for template comments when needed. Use one space inside comment delimiters. Short useful JSDoc may stay on one line; PHP's multiline narrative PHPDoc rule is PHP-specific. Never add a comment to every declaration or template wrapper.

```vue
<script lang="ts" setup>
interface Props {
  label: string
  value?: string | null
}

defineProps<Props>()
</script>

<template>
  <VChip
    class="branch-label"
    color="default"
    size="small"
  >
    {{ value ?? label }}
  </VChip>
</template>

<style lang="scss" scoped>
.branch-label {
  max-width: 100%;
}
</style>
```

### Exact formatting commands

Run from `backend/`, using the installed tools. Replace the placeholder paths below with the actual changed files; list paths explicitly to keep formatting within scope. Read-only checks use `--test` / `--check`; the commands under “Apply formatting” write files.

Check formatting and lint:

```bash
./vendor/bin/pint --test path/to/changed-file.php
yarn prettier --ignore-path .prettierignore --check path/to/changed-file.ts path/to/changed-component.vue
yarn eslint path/to/changed-file.ts path/to/changed-component.vue
```

Apply formatting to the changed files, then repeat the checks above:

```bash
./vendor/bin/pint path/to/changed-file.php
yarn prettier --ignore-path .prettierignore --write path/to/changed-file.ts path/to/changed-component.vue
```

Use scoped `yarn eslint --fix <changed-files>` only when needed, inspect its changes, then run Prettier and the checks again. Do not use repository-wide `yarn format`, `yarn lint:fix`, or bare `./vendor/bin/pint` for a focused change. Appending a file to `yarn format` does not narrow its existing broad globs.

For explicitly needed repository-wide read-only validation, use `./vendor/bin/pint --test`, `yarn format:check`, and `yarn lint`. `yarn format:check` targets the browser/config/script paths listed in `package.json`; it does not check PHP or every Markdown file. For an intentionally edited Markdown guide, a separate `yarn prettier --check AGENTS.md` can check Markdown layout, but avoid unrelated table/prose reformatting. Finish with `git diff --check` and review each affected repository's diff. Formatting/lint checks supplement, rather than replace, the behavior tests below.

## Validation and generated output

Run commands from `backend/`. Begin with affected tests, then broaden for shared HTTP/auth/routing, manifests, global state, money contracts, and build configuration.

| Change                                | Checks                                                                                                                                                                              |
| ------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PHP behavior                          | `php artisan test <target>`; `./vendor/bin/pint --test <changed-paths>`; `composer test` when broader coverage is warranted. Full PHP formatting check: `./vendor/bin/pint --test`. |
| Browser behavior                      | `yarn vitest run <target>`; `yarn type-check`; scoped ESLint/Prettier through the installed tools. Repository checks: `yarn test`, `yarn lint`, `yarn format:check`.                |
| Module registration/aliases           | `yarn check:modules`, relevant manifest/router tests, and `yarn build`. The module check verifies source presence, not business contracts or every disabled-module path.            |
| Browser compilation/bundling          | `yarn build` runs Vite and type-checking; `build-only` omits type-checking.                                                                                                         |
| Accounting/engine-sensitive mutations | Applicable focused tests plus `composer test:accounting:mysql` under the disposable database requirements below.                                                                    |

`phpunit.xml` discovers module Unit/Feature tests and uses SQLite `:memory:`, array-backed test services, and synchronous queues. Module feature fixtures often load selected migrations explicitly; extend the relevant fixture instead of imposing a new universal test bootstrap. Browser tests are colocated `*.test.ts` files in shared/module source and `scripts/`, using Vitest/jsdom. Test observable behavior and changed invariants.

`phpunit.mysql.xml` and `tests/Concerns/InteractsWithAccountingMySql.php` support the dedicated accounting gates. They can wipe/rebuild the target database. Verify the effective connection is disposable and its name ends in `_test` before running; the file's default name alone is insufficient. Fork-based concurrency tests need `pcntl`. SQLite success cannot establish MySQL locking or constraint behavior. Report unrun gates and blockers precisely.

Do not hand-edit generated dependencies/assets. In addition to workspace exclusions, this includes `resources/js/auto-imports.d.ts`, `resources/js/components.d.ts`, `resources/js/typed-router.d.ts`, `.eslintrc-auto-import.json`, generated Iconify `resources/js/app/plugins/iconify/icons.css`, and TypeScript build info under `node_modules/.tmp/`. Update their source/configuration and regenerate only as needed (`yarn icons:build` for icons). Re-check parent and affected nested diffs after tooling.

For receipt, PDF, or print-agent behavior, use [printing-change](.agents/skills/printing-change/SKILL.md); ordinary API/UI changes do not need that workflow.
