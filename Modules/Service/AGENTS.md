# Service module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns the external-service settings registry assembled from module configuration, validation metadata
and admin editing. Setting owns persisted values/encryption and cache bindings; Sms, Currency, Pdf
and notification integrations own execution of their providers. This is not the sellable
service-charge domain.

## Important source map

- `app/Services/ServiceRegistry/ServiceRegistryService.php` — registry discovery, dynamic rules,
  conditional fields and persistence.
- `app/Http/Controllers/Api/V1/ServiceController.php` — registry administration.
- `routes/api/v1.php` — category/service settings routes and permissions.
- `resources/assets/js/manifest.ts` — provider configuration UI registration.
- `database/seeders/ServiceDatabaseSeeder.php` — provider setting initialization.

## Local invariants and sensitive flows

- Preserve category/service keys, settings base keys and module-provided definitions; do not
  hard-code a second provider registry in the UI.
- Enabled-state and selected-option conditional fields determine validation and which values are
  saved. Disabling a service must not blindly overwrite unrelated provider credentials.
- Sensitive field metadata routes values through encrypted settings; keep it distinct from ordinary
  settings and preserve registry presentation semantics.
- Refresh the settings binding after writes so subsequent provider resolution sees the saved
  configuration.
- Service settings permission and domain capability are separate: exposing a provider does not grant
  the actor access to notification/payment/domain operations.

## Testing and validation

Use `tests/Feature/ServiceRegistryServiceTest.php` for discovered/dynamic definitions and
`tests/Feature/ServiceSettingsTest.php` for validation, conditional and sensitive settings. Exercise
the affected provider’s configuration tests without sending live requests.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
