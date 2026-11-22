# Printer module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns printer/print-agent administration, order print-content preparation, receipt layout and
rendering, and its browser print adapter. Pdf owns shared Blade templates and PDF driver generation;
Pos owns its UI print queue and display workflow. Printer does not persist order pricing or prove
physical printer delivery.

## Important source map

- `app/Services/Printer/PrinterService.php` — printer configuration and branch-aware metadata.
- `app/Services/Agent/PrintAgentService.php` — agent configuration.
- `app/Factories/PrintContentFactory.php` — document-kind content dispatch.
- `app/Factories/OrderResourceFactory.php` — order data prepared for print.
- `app/Services/Render/PrintRenderService.php` — HTML, image and PDF render paths.
- `app/Services/Render/ReceiptPaperHeight.php` — receipt sizing.
- `app/Services/Render/Templates/TemplateBuilder.php` — template selection.
- `resources/assets/js/composables/qintrix.ts` — browser print adapter.

## Local invariants and sensitive flows

- Bill, invoice, kitchen, waiter and delivery outputs have different audiences and content. Change
  the correct content factory rather than adding universal fields to every printout.
- Printed money/tax/charge details must remain consistent with the persisted source document. A
  receipt formatter is not an independent price calculator; credit notes must retain their document
  semantics.
- Respect configured locale/direction, printer width and receipt height. HTML, Chromium image
  rendering and Pdf generation are separate paths; validating one cannot establish the others.
- Device/agent selection must retain branch access and configuration constraints. The shared browser
  Qintrix integration handles transport; server render success is not a delivery acknowledgement and
  must not indiscriminately replay Pos queue entries.
- Templates used here live in `../Pdf/resources/views/`. Changes spanning template, print data and
  device rendering must be reviewed together, particularly Arabic text, tax summaries and long
  receipts.

## Testing and validation

Use `tests/Feature/OrderPrintLayoutTest.php`, `TaxSummaryPrintTemplateTest.php` and
`DompdfReceiptArabicTest.php`. Render representative receipt fixtures for the affected width, locale
and output type; include downstream Pos print behavior when changing dispatch/retry semantics.

Use [printing-change](../../.agents/skills/printing-change/SKILL.md),
[financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
