# Option module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns reusable option definitions, option values and selection types. Product owns product-specific
option assignment; Order owns persisted selected-option snapshots and authoritative pricing.

## Important source map

- `app/Services/Option/OptionService.php` — global option CRUD and value synchronization.
- `app/Models/Option.php` — global/product distinction and saveValues behavior.
- `app/Models/OptionValue.php` — option value persistence.
- `app/Enums/OptionType.php` — supported selection types.
- `resources/assets/js/api/option.api.ts` — browser API contract.
- `routes/api/v1.php` — global option administration.

## Local invariants and sensitive flows

- The administration service scopes reads/writes/deletes through `globals()`. Do not accidentally
  expose or edit product-local options through global-option endpoints.
- Option values are saved through the model’s synchronization path after option attributes; do not
  treat the nested values array as a scalar model field.
- Selection type, value price type and product assignment jointly affect cart configuration.
  Coordinate changes with Product option builders and Cart selection validation.
- Catalog edits must not retroactively replace the options captured on existing order lines.

## Testing and validation

`resources/assets/js/api/option.api.test.ts` verifies the API builder. Selection behavior also has
coverage in `../Cart/resources/assets/js/services/options.test.ts`; pricing changes need Order
pricing regression coverage. No module-local PHP tests are currently present.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
