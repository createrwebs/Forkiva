# Report module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns the operational report registry, report query/render contracts and report-specific export
sources. Source modules own transactions and historical snapshots; Accounting owns accounting
statements and ledger truth. Export owns asynchronous file generation and download authorization.
Browser report routes render the registered report contract.

## Important source map

- `config/` — registered reports and configuration.
- `app/Services/Report/ReportService.php` — report resolution, authorization and export context.
- `app/Reports/` — individual source queries and calculations, including Loyalty and GiftCard
  reports.
- `app/Http/Controllers/Api/V1/ReportController.php` — API rendering/export entry point.
- `routes/api/v1.php` — report route permissions.
- `resources/assets/js/` — report API, pages, registry and sidebar.

## Local invariants and sensitive flows

- Resolve report keys through the configured registry. Both report-area access and the resolved
  report's own permission matter; never instantiate a class from an unchecked request string.
- Keep branch/date/currency filters aligned between on-screen rendering and export. Export context
  includes report identity and filter data, so queued execution must reconstruct the same report
  scope.
- Aggregation must preserve the source grain. Joining orders, payments, line items or rewards can
  multiply totals; inspect each report's existing joins and normalization before adding dimensions.
- Loyalty reports distinguish points transactions, redeemed rewards, and generated/used/expired
  gifts. Do not collapse these into one conversion metric. Gift-card liability is not sales revenue
  or the sum of card face values.
- Historical money and cost reports must use the relevant persisted source fields and rates. Report
  queries do not repair source records or repost accounting. Preserve database-portable grouping and
  date behavior covered by query compatibility tests.

## Testing and validation

Use `tests/Feature/ReportQueryCompatibilityTest.php` for query changes and
`tests/Feature/LoyaltyReportSemanticsTest.php` for loyalty measures.
`tests/Unit/ReportControllerTest.php` and `ReportExportSourceTest.php` cover resolution/export
contracts. Browser checks include `resources/assets/js/api/report.api.test.ts` and
`resources/assets/js/sidebars/admin.sidebar.test.ts`. Validate totals against source fixtures at the
same grain, including empty and multi-branch results.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
