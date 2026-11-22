# Menu module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns branch menus and public online-menu publication by slug. Category/Product own catalog contents;
PosViewer supplies the public catalog projection. SeatingPlan owns QR table-order submission, which
is distinct from browsing an online menu.

## Important source map

- `app/Services/Menu/MenuService.php` — current-menu selection and administrative operations.
- `app/Services/OnlineMenu/OnlineMenuService.php` — public slug lookup and branch-aware metadata.
- `app/Models/Menu.php` — active menu selection and branch relationship.
- `app/Http/Requests/Api/V1/SaveOnlineMenuRequest.php` — publication/menu inputs.
- `resources/assets/js/manifest.ts` — admin and public browser registration.
- `routes/api/v1.php` — menu and online-menu API routes.

## Local invariants and sensitive flows

- Current-menu selection distinguishes explicit menu IDs, assigned users, and the main branch.
  Preserve this resolution instead of selecting the first menu globally.
- An online-menu slug resolves its configured menu or the branch’s active menu and delegates
  categories/products to PosViewer. Do not fork a second visibility/pricing catalog here.
- Branch-aware form metadata must use BranchAccess; menu options must remain consistent with the
  selected branch.
- Public browsing must not acquire order-edit/payment capabilities merely because it shares POS
  catalog components.

## Testing and validation

Use `tests/Feature/OnlineMenuTest.php` for public publication and branch behavior. Changes to shared
catalog projection also require `../Pos/tests/Feature/PosProductVisibilityTest.php`.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
