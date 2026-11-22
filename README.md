# Forkiva — unified Laravel application

This directory is the application root for both the Laravel API and Vue administration/POS web UI. The separate `../forkiva/` checkout is retained only as the pre-integration recovery/reference copy; it is not used to install, build, run, or deploy this application.

The source consolidation is implemented; full production readiness is not certified. See the known release blockers below before attempting a fresh deployment.

## Requirements

- PHP 8.2+ and the extensions listed in `composer.json`.
- Node satisfying `package.json#engines`, Yarn Classic 1.22.22, and Composer.
- A configured database and the services required by your deployment.
- Puppeteer's matching Chrome runtime for PDF/receipt generation.

## Install and run

Run everything below from this directory:

```bash
composer install
yarn install --frozen-lockfile
yarn check:modules
yarn build
composer run dev
```

Open the Laravel server address (normally `http://127.0.0.1:8000`), not Vite's asset-server address. `composer run dev` delegates to `yarn dev`, which starts Laravel, Vite, a queue listener, and the scheduler. Stopping the command stops its child processes. The existing database and service configuration still need to be valid; starting the application is not a database-installation step.

For a fresh installation only, create the local `.env` from `.env.example`, configure it, and generate the application key. Do not overwrite an existing environment or rotate an existing application key. Use the installer or the approved database setup workflow. Laravel discovers enabled module migrations, so `php artisan migrate` runs their migrations in timestamp order. Never run destructive migration or seed commands against shared data.

Optional tools:

```bash
yarn dev:assets          # Vite only, useful with Laravel Herd or another PHP server
yarn icons:build         # Regenerate the shared icon stylesheet from all module UI sources
yarn browser:install     # Install Puppeteer's matching Chrome if download was skipped
composer run ide-helper # Development-only IDE helpers, not part of production installation
```

## Source ownership

- `resources/js/app.ts`: the single Vue entry point.
- `resources/js/app/`: shared bootstrap, layout, router, and plugins.
- `resources/js/assets/`: shared styles, fonts, and images.
- `resources/views/app.blade.php`: public HTML shell loaded through Laravel's `@vite`.
- `Modules/<Module>/resources/assets/js/`: feature-owned API functions, contracts, pages, composables, routes, sidebars, and tests.
- `Modules/User/resources/assets/js/auth/`: authentication UI; User owns the backend authentication API.
- `Modules/ActivityLog/resources/assets/js/`: the former `activity` frontend feature.
- `Modules/{User,Currency,Support}/resources/data/`: existing PHP resource data, moved without changing its contents to avoid mixed-case `Resources` directories.
- `tsconfig.paths.json`: authoritative logical import mapping, shared by TypeScript, Vite, and Vitest. Existing `@/modules/...` imports remain valid.
- `modules_statuses.json`: backend module activation and browser-manifest registration. Rebuild after activation changes.
- `public/build/`: generated, versioned assets, Vite manifest, and service worker. Never edit by hand.

There is one root `package.json`, one Yarn lockfile, one `node_modules/`, and one Composer installation into `vendor/`. Module Composer manifests remain part of the existing Composer merge-plugin setup; do not run separate module dependency installations.

### Nested repositories

HR, Delivery, and Accounting retain their existing Git repositories under `Modules/`. Their frontend source now belongs to those same backend module repositories. The parent repository ignores these directories; a parent-only clone or parent-only commit is not a complete release.

Restore the required module checkouts before installing Composer dependencies, and record/release their revisions together with the parent revision. `yarn check:modules` and Vite fail when enabled module sources are absent. Inspect, commit, and package all affected repositories explicitly. No Git histories were deleted or rewritten during this integration.

## URLs, authentication, and mobile compatibility

The browser defaults to same-origin `/api`; feature calls keep their existing `/v1/...` paths. `VITE_API_URL` can explicitly override that base for an intentional separate API host. When backend route-domain mode is enabled, configure that override for the actual API domain/version layout and test both origins.

Browser navigation remains rooted at `/`, independently of Vite's `/build/` asset base. Laravel serves the shell for `/`, `/auth/*`, `/admin/*`, `/employee/*`, `/driver/*`, `/online-menu/*`, and `/table-order/*`. API, installer, health, invoice/download, and asset paths are not caught by the SPA route.

Authentication remains bearer-token based, including the existing multiple-account IndexedDB behavior. No API response envelopes, permissions, or business rules were changed by the source migration. Employee and driver Flutter apps retain their own Pub dependencies/builds and the same API contracts.

Keep the production browser hostname stable where possible. A hostname/origin change affects browser storage and passkey relying-party/origin configuration; it may require signing in again and a deliberate passkey migration. If browser storage encryption is configured, preserve its public client configuration through the deployment's environment management. Never expose Laravel server secrets through `VITE_*` variables.

## PWA behavior

The generated worker remains under `public/build/sw.js`, but Laravel serves it at `/sw.js` with root scope and revalidation headers. Its runtime is inlined, so it does not depend on root-level Workbox sidecar files.

Only static frontend assets and the generic `/offline` shell are precached. The worker's navigation fallback is restricted to browser application paths; API responses, uploaded/private files, installer pages, and invoices are not added to that cache. Offline rendering does not make mutations or authenticated data available without the existing application's API/cache behavior. PWA registration is disabled during Vite development and remains controlled by the existing PWA setting in production builds.

Deploy assets and their Laravel manifest together. Existing tabs can still hold earlier lazy chunks, so retain previous release assets long enough for open sessions/service workers to update. Do not replace `public/` wholesale; it also contains backend assets and storage links.

## Validation

```bash
yarn check:modules
yarn test
yarn lint
yarn format:check
yarn build
composer test
```

Frontend tests are discovered in both `resources/js/` and every module's `resources/assets/js/`, including nested repositories. Generated declarations are produced by Vite; a fresh checkout should build before running TypeScript-only checks. The build runs Vite followed by TypeScript so declarations are available.

Backend shell/routing regressions are covered by `Modules/Core/tests/Feature/WebApplicationTest.php`. Run the existing MySQL accounting/concurrency gates against an explicitly disposable `_test` database before approving engine-sensitive releases; SQLite is not a substitute.

## Production deployment

### Known release blockers

The September 3, 2026 validation found existing backend issues that also reproduce on the unchanged pre-integration Git baseline:

- A fresh MySQL 8.0.35 installation fails in Delivery's `2026_05_05_130200_create_delivery_driver_assignments_table` migration while adding its `order_delivery_id` foreign key (error 1215). Review migration compatibility before installing an empty MySQL database; do not bypass constraints or rewrite released history without an explicit migration strategy.
- The full SQLite backend test suite has 47 failures and one error: branch-accounting role availability, date-sensitive fixed-asset-policy cases, installation-state leakage between tests, and an unregistered ActivityLog redaction command. The targeted integration/authentication suite passes independently.
- HR dashboard queries require MySQL functions and cannot be validated fully using SQLite. MySQL-backed HR and accounting/concurrency validation remain blocked by the fresh-schema issue above.

### Release procedure

Build in a complete release checkout with development JavaScript dependencies installed. Deploy the Laravel application and `public/build/` as one release. Composer runtime dependencies can be installed with `composer install --no-dev --prefer-dist --optimize-autoloader`; IDE helpers are no longer invoked by production installation.

Point the web server document root at `public/` and route non-file requests to `public/index.php`. Run managed queue workers and Laravel's scheduler. Do not run Vite or `composer run dev` in production.

Puppeteer is a runtime dependency: retain/install production Node dependencies and its Chrome runtime on workers that render PDFs or receipts. Normal application HTTP requests do not require a Node web server. Apply approved migrations and cache-building steps as part of your deployment procedure; database migration, credentials, DNS, and production cutover are not performed automatically by the build.
