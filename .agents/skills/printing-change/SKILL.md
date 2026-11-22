---
name: printing-change
description: Change Forkiva receipts, invoice or payroll PDFs, printer rendering, or Qintrix print dispatch. Use when paper layout, Arabic output, PDF-driver behavior, or physical print payloads are affected.
---

# Printing and PDF changes

Apply the workspace and backend guides. Paths below are relative to `backend/`. Identify the actual output route and renderer before changing shared templates; HTML, raster output, PDF, and physical dispatch take different paths.

## Find the output owner

- `Modules/Printer/app/Factories/PrintContentFactory.php` and `OrderResourceFactory.php` choose content/resource handling. Follow the selected PrintContent into `Modules/Printer/app/Services/Render/PrintRenderService.php` and `Templates/TemplateBuilder.php`.
- Receipt templates are in **`Modules/Pdf/resources/views/print/`**, including `templates/` and partials, even when the request originates in Printer. Do not create a second template tree in Printer.
- Invoice and payroll document views live under `Modules/Pdf/resources/views/invoices/` and `payroll/`. Trace their own callers; receipt changes do not automatically cover full-page documents.
- `Modules/Pdf/app/Services/Generator/PdfGenerator.php` and `Modules/Pdf/config/config.php` own PDF generation/driver configuration, including Dompdf Arabic handling. Receipt PDF requests specify paper dimensions through the PDF request and receipt-height logic.
- `PrintRenderService`'s image path uses Browsershot directly. A working Dompdf PDF does not prove that raster path works, and Chromium is not required for every PDF driver.
- `Modules/Printer/resources/assets/js/composables/qintrix.ts` and `resources/js/app/plugins/qintrix/` own transport to the external print agent. Keep this transport separate from Core's Laravel Axios client; preserve target-device binding, content type, options, references, and job-creation semantics.

Determine whether the task affects content, serialization, layout, renderer configuration, or dispatch. Keep the change in the existing layer, preserving stored financial snapshots and printer-selected locale.

## Preserve layout and rendering contracts

1. Inspect the current driver's supported HTML/CSS and existing shared layout/partials. Receipt templates intentionally avoid flex/grid and CSS variables in their Dompdf-compatible output. Do not assume browser layout support implies PDF support.
2. Preserve Arabic shaping, embedded fonts, RTL flow, and mixed-direction text. Avoid applying Arabic processing twice or globally reversing strings. Printer locale can differ from UI/user locale; test the selected printing locale explicitly.
3. Check configured receipt profiles, especially 58 mm and 80 mm, along with dynamic height and margins. For invoice/payroll documents, check pagination, repeated headers, and totals on the correct page instead of applying receipt sizing.
4. Reuse shared tax/totals partials and the existing document resource. Do not recalculate historical invoice totals from current menu, tax, currency, or branch configuration just to render output.
5. If adjusting dispatch, preserve the captured job creator's printer/device target and retry/error behavior. A render preview is not a physical job; do not send to an actual printer merely to verify HTML.

## Verify output at the affected layer

Start with relevant tests from `Modules/Printer/tests/Feature/` and `Modules/Pdf/tests/`:

- `OrderPrintLayoutTest.php` covers receipt HTML, locales, profiles, and PDF paper requests.
- `DompdfReceiptArabicTest.php` and `TaxSummaryPrintTemplateTest.php` cover Arabic receipt and shared tax layout behavior.
- `Modules/Pdf/tests/Feature/DomPdfGenerationTest.php` and `Modules/Pdf/tests/Unit/ArabicHtmlProcessorTest.php` exercise PDF generation and Arabic processing.

Run each applicable target with `php artisan test <target>` from backend, then scoped Pint. For Vue dispatch changes, run affected Vitest tests and browser type/lint checks; build when compilation/integration changes.

For a rendering/layout change, also generate a fixture document with the actual affected driver and inspect its rendered pages/image. Use supported locales and representative long labels, discounts/taxes, mixed-direction numbers, and multipage content as relevant. Check clipping, glyphs, column alignment, paper width, page breaks, and totals. Mocked PDF request tests alone cannot verify visual output. Use test fixtures and temporary outputs rather than real customer documents.

If the renderer fails, inspect `Modules/Pdf/app/Console/DiagnosePdfCommand.php` and run `php artisan pdf:diagnose dompdf` (or the specific affected driver) when appropriate. Omitting the driver probes all configured drivers, which can involve external services. Do not change environment topology or install Chromium to repair an unrelated driver; `yarn browser:install` is the existing installation command when the task actually requires Browsershot/Chrome.

Use a mock/fake agent or an explicitly designated test printer for dispatch validation. Do not print to a live device or replay real jobs without task authorization. Report which content, locale, profile, driver, and transport were verified and any unavailable rendering/agent dependency. Done requires correct rendered output for the affected path and preserved job/paper/locale contracts, not merely an HTML snapshot match.
