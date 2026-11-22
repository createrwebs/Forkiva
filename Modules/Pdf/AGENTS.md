# Pdf module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns PDF driver configuration/verification, PDF generation, Arabic/RTL rendering helpers and shared
document templates, including receipts, invoices and payroll. Printer owns print-content preparation
and device/agent delivery; Invoice and Hr own document facts. Setting exposes PDF settings through
its administration flow.

## Important source map

- `app/Services/Generator/PdfGenerator.php` — PDF generation facade and driver selection.
- `app/Services/Driver/` — driver configuration and verification.
- `app/Services/Settings/PdfSettingsManager.php` — persisted settings, secret handling and
  verification state.
- `app/Services/Rendering/` — Arabic shaping and Dompdf line wrapping.
- `app/Data/PdfRenderRequest.php` — render input contract.
- `app/Console/DiagnosePdfCommand.php` — explicit driver diagnostics.
- `resources/views/` — receipt, invoice and payroll templates.
- `resources/assets/js/pages/admin/PdfSettings.vue` — browser driver settings.

## Local invariants and sensitive flows

- Driver selection is configuration, not a reason to duplicate document calculations. Render
  supplied historical facts; keep invoice/payroll pricing and totals in their owning domain.
- Preserve the driver's actual capabilities. Dompdf Arabic shaping/line wrapping is a distinct path
  from browser-based rendering; a Chromium preview does not validate Dompdf output.
- Settings responses mask private provider credentials and report whether a secret exists. Preserve
  retain, replace and clear semantics; masked/empty display values must not accidentally overwrite
  stored secrets.
- Verification belongs to the effective candidate configuration and its fingerprint. A previous
  successful verification must not silently validate changed driver credentials/options. Diagnostics
  should target the selected driver rather than contact every external provider.
- Shared receipt templates also serve Printer. Paper dimensions, direction, fonts, amount labels and
  repeated page content must be checked in the actual target renderer. Keep errors actionable
  without exposing provider secrets.

## Testing and validation

Use `tests/Feature/DomPdfGenerationTest.php`, applicable driver/settings tests under `tests/Unit/`,
and `resources/assets/js/pages/admin/PdfSettings.test.ts`. Arabic changes need
`ArabicHtmlProcessorTest.php` and downstream `../Printer/tests/Feature/DompdfReceiptArabicTest.php`.
Document-template changes also require the owning Invoice/Hr PDF fixtures and visual output
inspection via the printing workflow.

Use [printing-change](../../.agents/skills/printing-change/SKILL.md),
[api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
