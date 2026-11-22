# Currency module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns configured exchange rates, provider refresh, and default-currency conversion. Setting chooses
default currency/provider configuration; individual financial domains own their frozen rates and
historical amounts. This module does not revalue issued documents automatically.

## Important source map

- `app/Services/DefaultCurrencyConverter.php` — exact conversion direction, positive-rate validation
  and same-currency handling.
- `app/Models/CurrencyRate.php` — configured rate persistence and refresh.
- `app/Services/CurrencyRate/CurrencyRateService.php` — admin editing and configured-provider
  refresh.
- `app/Providers/CurrencyServiceProvider.php` — conditional refresh command/schedule.
- `app/Providers/CurrencyExchangeRateServiceProvider.php` — Swap/provider configuration.
- `resources/assets/js/composables/currencyRate.ts` — admin rate refresh/edit actions.

## Local invariants and sensitive flows

- A frozen foreign rate is source-currency units per default-currency unit; conversion to default
  divides by the rate. Preserve same-currency handling and positive-rate validation in the
  converter.
- Currency subunits and stored rate scale are different contracts. Inspect the later precision
  migration instead of copying the original rate column scale.
- Refresh requires a configured exchange service. Scheduled refresh is conditional on settings and
  supported frequency; do not register an unconditional external call.
- Preserve historical rates in consuming orders/invoices/ledgers. Refreshing the configured rate
  must not rewrite their snapshots.

## Testing and validation

No local PHP tests are present. Conversion consumers have coverage in
`../GiftCard/tests/Unit/GiftCardCurrencyConversionTest.php` and financial owner suites. Add focused
regression coverage for changed conversion/provider behavior; use fakes instead of live exchange
requests.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md) for the
corresponding specialized changes.
