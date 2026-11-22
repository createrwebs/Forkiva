# Tax module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns tax definitions, inclusive/exclusive types, branch-scoped administration and reusable tax
display components. Order pricing applies taxes; Invoice and financial ledgers own historical tax
snapshots; Accounting owns compliance/reporting workflows.

## Important source map

- `app/Models/Tax.php` — tax definition relationships and scope.
- `app/Enums/TaxType.php` — inclusive/exclusive semantics.
- `app/Http/Requests/Api/V1/SaveTaxRequest.php` — branch-aware definition validation.
- `app/Services/Tax/TaxService.php` — definition CRUD and form metadata.
- `resources/assets/js/components/TaxAmountWithDetails.vue` — tax amount presentation.
- `resources/assets/js/components/TaxSummaryRows.vue` — shared summary rows.

## Local invariants and sensitive flows

- Keep inclusive and exclusive tax semantics distinct through serialization and UI. A type label
  change must not change the calculation meaning.
- Definition uniqueness and selection are branch-sensitive; inspect request/model behavior before
  broadening a list to all branches.
- Editing a current tax definition must not recalculate issued invoices or rewrite order tax
  snapshots. Trace Order/Invoice builders when changing application behavior.
- Shared tax components are used outside administration, including carts and receipts. Preserve
  line/summary shape and currency presentation instead of introducing a competing tax calculator.

## Testing and validation

Local regression coverage is `resources/assets/js/components/TaxAmountWithDetails.test.ts` and
`TaxSummaryRows.test.ts` beside it. No local PHP tests exist; calculation changes need Order pricing
and Invoice tax/charge tests, while print changes need Printer tax-summary tests.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
