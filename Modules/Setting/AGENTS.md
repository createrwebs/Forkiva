# Setting module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns persisted application settings, section-based administration, public app settings and boot
metadata/versioning. Service owns the configurable provider registry; individual domains own
interpretation of their settings. Core consumes public settings for browser startup.

## Important source map

- `app/Services/Setting/SettingService.php` — public whitelist, section metadata/update and
  versions.
- `app/Models/Setting.php` — cached/encrypted persistence.
- `app/Listeners/ClearSettingCache.php` — cache invalidation.
- `app/Enums/SettingSection.php` — editable sections.
- `routes/api/v1.php` — unauthenticated public boot versus protected settings administration.
- `resources/assets/js/composables/setting.ts` — browser settings updates.

## Local invariants and sensitive flows

- `/api/v1/app/settings` and `/api/v1/app/boot-meta` are public. Keep an explicit safe field
  whitelist; encrypted server settings and credentials must never leak into it.
- Public payloads include locale/RTL/time zone, branding/PWA, currency and optional feature
  availability. Preserve their version/cache behavior when changing values or supported features.
- Updating persisted settings must refresh the cached setting repository; a successful write with
  stale boot/provider state is incomplete.
- Section validation is specialized for passkeys, password recovery, mail/branding and other
  domains. Do not accept arbitrary setting keys through a generic update.
- Feature flags describe module availability; changing one is not a database uninstall or permission
  grant.

## Testing and validation

Use `tests/Unit/AppSettingsVersionTest.php`, `SettingModelTest.php`,
`PasskeySettingValidationTest.php`, `PasswordRecoverySettingValidationTest.php` and
`EmailLogoSettingTest.php` in the same directory as relevant. Browser behavior has
`resources/assets/js/composables/setting.test.ts` and settings-page/sidebar tests.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md),
[optional-module-integration](../../.agents/skills/optional-module-integration/SKILL.md) for the
corresponding specialized changes.
