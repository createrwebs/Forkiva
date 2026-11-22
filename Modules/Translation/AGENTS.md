# Translation module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns merged translation overrides, public web/driver/employee bundles and translation import/export
administration. Modules retain their own source language files; this module does not own domain
behavior described by a translated string.

## Important source map

- `app/Services/Translation/TranslationService.php` — public filtering, retrieval and versioning.
- `app/Services/Translation/Import/TranslationImportService.php` — preview and
  fill-missing/overwrite application.
- `app/Services/Translation/Export/TranslationExportService.php` — locale JSON and all-locale ZIP
  output.
- `app/Services/Translation/Support/TranslationBulkSupportService.php` — flat payload/ZIP validation
  and placeholder support.
- `app/Models/Translation.php` — persisted locale overrides.
- `routes/api/v1.php` — public bundle versus protected bulk-edit endpoints.
- `resources/assets/js/composables/language.ts` — browser locale behavior.

## Local invariants and sensitive flows

- Preserve module-qualified keys and locale fallback/merge rules. Database overrides must not become
  a second source tree that silently discards newly added module keys.
- Web and each mobile app have explicit public-key filtering; do not expose the entire admin bundle
  through every unauthenticated endpoint.
- Version/cache invalidation accounts for settings/locales and source-file edits as well as model
  changes. Bundle caches must remain compatible with the database cache’s payload limits.
- JSON imports require flat scalar values; ZIP entries are interpreted as locale files. Preserve
  preview reporting, placeholder checks and fill-missing versus overwrite semantics before applying
  bulk edits.
- Driver and employee applications consume their corresponding translation endpoints; key/filter
  changes require checking those actual clients.

## Testing and validation

Use `tests/Unit/AppTranslationsCacheTest.php` and `tests/Unit/TranslationServiceBulkToolsTest.php`.
Cover locale/fallback/source-version changes, public filtering, placeholder preservation and import
modes.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
