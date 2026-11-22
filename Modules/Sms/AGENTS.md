# Sms module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns SMS provider selection/configuration and construction of provider-specific notification
messages. Notification owns workflow dispatch, User owns authentication/recovery policy, and
Service/Setting own configuration storage. There are no module-owned routes, browser pages or
tables.

## Important source map

- `app/Services/SmsProvider/SmsProviderConfiguratorService.php` — active provider and runtime
  configuration.
- `app/Services/SmsMessage/SmsMessageService.php` — recipient routing and nullable message
  construction.
- `app/Enums/SmsProvider.php` — supported provider identifiers.
- `app/Providers/DeferredSmsServiceProvider.php` — service bindings.
- `config` — module/provider configuration definitions.

## Local invariants and sensitive flows

- Message construction returns null when provider, recipient or message is absent. Preserve caller
  handling of unavailable delivery rather than fabricating a successful send.
- Resolve `routeNotificationForSms()` before falling back to the notifiable phone field. Respect
  configured sender name and provider identifiers.
- Keep provider credentials in existing encrypted settings/configuration boundaries; do not put them
  in notification payloads or browser settings.
- Changing the adapter must not bypass User’s OTP expiration/retry policy or Notification’s
  workflow/channel selection.

## Testing and validation

Use `tests/Unit/SmsModuleTest.php` and the affected Notification/User workflow tests. Fake delivery;
unit configuration validation does not authorize sending SMS to real recipients.
