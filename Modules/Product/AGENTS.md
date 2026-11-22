# Product module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns sellable catalog products, category/option assignment and recipe/option-ingredient definitions.
Option owns reusable option templates; Inventory owns stock quantities/movements and cost balances;
Order owns sold-line snapshots and pricing execution.

## Important source map

- `app/Services/Product/ProductService.php` — catalog persistence.
- `app/Services/ProductOption/ProductOptionService.php` — product-local option synchronization.
- `app/Services/ProductIngredient/ProductIngredientService.php` — branch-safe recipe and option
  modifier synchronization.
- `app/Enums/IngredientOperation.php` — add/subtract/replace/multiply modifiers.
- `app/Enums/ProductInventoryConsumptionMode.php` — direct_recipe versus production_output.
- `app/Models/Product.php` — catalog relationships and properties.
- `resources/assets/js/manifest.ts` — product administration routes.

## Local invariants and sensitive flows

- Product-local options and global Option definitions are distinct. Preserve synchronization and
  deletion-candidate rules rather than editing shared templates through a product form.
- Recipe ingredient IDs must belong to the product’s branch. Modifier operations have owner-specific
  compatibility rules; preserve validation for both product and option-value recipes.
- Consumption mode determines whether a sale consumes a direct recipe or production output. Do not
  decrement stock from Product CRUD; Inventory resolves the movement using the saved order context.
- Catalog changes must not replace recipe, option, price or service-category snapshots already
  captured on orders.
- Product visibility depends on menu/category activation as well as its own flag. Check the POS
  projection when changing catalog relations.

## Testing and validation

Use `tests/Feature/ProductSeederTest.php`,
`../Inventory/tests/Feature/ProductInventoryConsumptionTest.php`, and
`../Pos/tests/Feature/PosProductVisibilityTest.php`. Option/price changes also need Cart option and
Order pricing tests.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
