# Notification module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns configurable notification workflows/templates, channel resolution and rendering, user
notification-center records, and push-token registration. Domain modules own event eligibility and
recipients supplied to dispatch; Service supplies provider configuration and Sms supplies SMS
transport. Driver consumes the notification-center API; both mobile apps register push tokens.

## Important source map

- `app/Services/NotificationDispatcher/NotificationDispatcherService.php` — workflow, recipient,
  channel and locale orchestration.
- `app/Services/NotificationCenter/NotificationCenterService.php` — recipient-scoped reads, counts
  and read state.
- `app/Services/NotificationPushToken/NotificationPushTokenService.php` — device-token ownership.
- `app/Services/NotificationTemplate/NotificationTemplateRendererService.php` — template context
  rendering.
- `app/Support/NotificationPayload.php` — stored/client payload contract.
- `app/Channels/ResilientMailChannel.php` — mail failure handling.
- `routes/api/v1.php` — personal center versus administrative workflow/template routes.
- `resources/assets/js/stores/notificationCenterStore.ts` — browser center state.

## Local invariants and sensitive flows

- Preserve workflow enabled state, recipient-role selection, channel availability and per-recipient
  locale. Guest recipients have different supported channels; do not assume every recipient is an
  authenticated User.
- Notification-center operations must remain scoped to the authenticated notifiable and app context.
  Administrative template permissions do not grant access to another user's personal notification
  records.
- Push-token registration identifies a token by provider plus token and associates it with the
  current authenticated user, allowing device account changes. Deletion is explicitly scoped to that
  user's token ID. Keep client/platform context compatible with mobile routing.
- Notification payloads are version-sensitive client data. Preserve context, target/action
  information and translated display fields through database, broadcast and push paths; inspect
  consumers before renaming them.
- Domain event retries and channel delivery failures are distinct. Do not move business transitions
  into templates or assume a successful queued dispatch proves external delivery. Preserve resilient
  mail handling and avoid logging sensitive rendered variables.

## Testing and validation

Use `tests/Feature/NotificationCenterTest.php`, `NotificationWorkflowDispatchTest.php`,
`NotificationWorkflowTest.php`, `NotificationTemplateTest.php` and `EmailDesignTest.php` as
appropriate. Payload, localized search, renderer and mail failure coverage lives under
`tests/Unit/`. Changes to push payloads require inspection of the driver notification router and
employee notification router, plus their token-registration repositories.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
