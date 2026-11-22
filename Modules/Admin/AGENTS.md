# Admin module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns shared administration labels and sidebar grouping containers. It has no PHP services,
controllers, routes, or database schema. Domain modules own their screens and permissions; Core owns
sidebar assembly and shared components.

## Important source map

- `resources/assets/js/manifest.ts` — sidebar-only registration.
- `resources/assets/js/sidebars/admin.sidebar.ts` — sales and promotions parent groups.
- `lang/en/permissions.php` — shared English permission labels.
- `lang/ar/permissions.php` — matching Arabic labels.

## Local invariants and sensitive flows

- Preserve stable sidebar keys and parent keys: feature modules attach their entries to these
  containers. Empty group permissions do not authorize any child endpoint.
- Keep shared `admin::` labels reusable; put domain-specific business behavior in its owner rather
  than adding backend services here.
- A label or grouping change can affect many manifests. Inspect Core sidebar assembly and matching
  child keys before renaming a container.

## Testing and validation

There is no local test suite. Validate sidebar changes through
`../Core/resources/assets/js/services/SidebarService.test.ts` and relevant feature sidebar tests;
use the parent browser checks for changed TypeScript. Translation-only changes require key/locale
comparison, not invented application tests.
