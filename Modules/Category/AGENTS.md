# Category module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns menu-scoped category hierarchy, ordering, slugs and catalog visibility. Menu owns the
containing menu/branch; Product owns sellable products and their category associations. Media
supplies category artwork.

## Important source map

- `app/Services/CategoryTreeUpdater/CategoryTreeUpdaterService.php` — menu-constrained reorder, tree
  repair and cache invalidation.
- `app/Models/Category.php` — nested-set scopes and ancestor-aware active visibility.
- `app/Http/Requests/Api/V1/SaveCategoryRequest.php` — category/menu/slug input contract.
- `resources/assets/js/composables/category.ts` — admin category actions.
- `routes/api/v1.php` — CRUD and menu tree update route.

## Local invariants and sensitive flows

- Tree updates must stay within the requested menu and preserve parent/order consistency. The
  updater applies changes transactionally, calls `fixTree()`, and flushes category cache. Do not
  replace it with independent parent assignments.
- Nested-set maintenance deliberately bypasses active filtering and accounts for soft deletion;
  filtering out inactive nodes corrupts tree maintenance.
- A category is publicly usable only when its ancestor chain is active. Do not expose a child merely
  because its own flag is true.
- Preserve PostgreSQL parameter typing in the bulk update when all parent values are null.
- Category tree/list caches feed POS and public menus; changing hierarchy or slugs must keep those
  views coherent.

## Testing and validation

`tests/Feature/CategoryTreeUpdaterTest.php` covers null parents and menu boundaries. Catalog
visibility changes also need the affected POS/public-menu tests, including inactive ancestors.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
