# Installer module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns the pre-installation web wizard, environment/configuration checks, database probing, resumable
installation tasks and initial administrator setup. It is a separate Vue entry inside the unified
build, not a normal authenticated admin module. Tool owns later database backup/restore.

## Important source map

- `routes/web.php` — session/CSRF-protected installer actions.
- `app/Services/State/InstallerState.php` — owner-bound encrypted state and locking.
- `app/Services/Installation/InstallationService.php` — ordered stages, task execution and
  finalization.
- `app/Services/Environment/InstallerEnvironment.php` — atomic environment writes.
- `app/Services/DatabaseProbe/InstallerDatabaseProbe.php` — engine capability probes and cleanup.
- `app/Http/Middleware/CheckInstallation.php` — installed-state access gate.
- `resources/assets/js/main.ts` — installer browser entry.
- `resources/assets/js/composables/useInstaller.ts` — wizard state and action orchestration.

## Local invariants and sensitive flows

- Preserve session ownership and locking of installer state; a second browser session must not adopt
  another setup or receive private state. Public snapshots must exclude connection
  fingerprints/credentials.
- Configuration/database validation, task processing, administrator creation and finish have ordered
  prerequisites. GET navigation and old bookmarks must never reset or finish installation.
- Database fingerprints bind validation to the effective connection. Do not reuse a successful probe
  after changing connection settings.
- Probes and installation tasks can mutate schema/data; test them only with the existing isolated
  fixtures. Preserve cleanup and resumability rather than restarting with destructive refreshes.
- Environment persistence uses safe encoding and atomic replacement. Recovery errors expose a
  support reference, not secrets or raw debug exceptions.
- Asset readiness handles built assets and development setup separately; do not require the regular
  authenticated app to boot before the installer.

## Testing and validation

`tests/Feature/InstallerTest.php` covers access/stage/public-state behavior;
`tests/Unit/InstallerFailureTest.php`, `InstallerFileCacheTest.php` and
`InstallerDependenciesTest.php` cover recovery and platform checks. Browser tests include
`resources/assets/js/InstallerApp.test.ts` and `resources/assets/js/api/installer.test.ts`. Preserve
the user’s initialized environment during validation.
