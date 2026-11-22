# Invoice module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns issued financial documents, numbering, seller/buyer snapshots, line/tax/discount snapshots,
credit-note relationships and payment/charge allocation to documents. Order owns operational sales
and refund intent; Payment owns tender records; Accounting owns ledger posting; Pdf owns rendering
infrastructure.

## Important source map

- `app/Services/CreateInvoice/CreateInvoiceService.php` — issue from persisted order context.
- `app/Services/CreateCreditNote/CreateCreditNoteService.php` — locked original-document selection
  and snapshot reversal.
- `app/Services/InvoiceNumberGenerator/InvoiceNumberGeneratorService.php` — transactional counter
  generation.
- `app/Services/InvoiceLineBuilder/InvoiceLineBuilderService.php` — product/charge document lines.
- `app/Services/InvoiceChargeAllocation/InvoiceChargeAllocationService.php` — charge allocations.
- `app/Services/OrderChargeInvoicePolicy/OrderChargeInvoicePolicyResolverService.php` — charge
  treatment at issuance.
- `app/Services/InvoicePDF` — document rendering adapter.
- `app/Models/Invoice.php` — issued document relations and monetary snapshots.
- `resources/assets/js/manifest.ts` — invoice administration routes.

## Local invariants and sensitive flows

- The current document status is issued; standard/credit-note kind and original/cancellation/return
  purpose are separate enums. Do not invent a draft/post lifecycle from Accounting states.
- Credit notes copy original lines, taxes, discounts, parties and frozen monetary context. Current
  menu prices, branch tax configuration or exchange rates must not regenerate historical values.
- Credit creation locks original/credit-related records, identifies eligible source products/charges
  and checks cumulative original balances. Preserve duplicate prevention and refund ceilings,
  including partial repeated reversals.
- Number counters are locked within transactions. Do not replace them with count-plus-one or a
  browser-generated number.
- Keep payment allocations, service-charge allocations and line-versus-order tax allocation
  consistent with issued totals; remainder ownership must remain deterministic.
- Optional gratuity/accounting integrations use source identities and failure reporting. A
  credit-note record must not imply a successful enabled posting when the bridge failed.
- PDF/receipt consumers must render stored snapshots. Keep Seller/Buyer identity and branding
  responsibilities distinct from live branch settings where snapshots exist.

## Testing and validation

Use `tests/Feature/InvoiceChargeHandlingTest.php`, `CreateCreditNoteMySqlConcurrencyTest.php`,
`InvoicePdfRenderTest.php`, `tests/Unit/ChargeRefundCalculatorTest.php` and PDF template/branding
tests. Credit races need MySQL validation on a disposable target; visual changes need actual
rendered output through the printing workflow.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[printing-change](../../.agents/skills/printing-change/SKILL.md),
[database-migration](../../.agents/skills/database-migration/SKILL.md) for the corresponding
specialized changes.
