# User module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns user accounts, roles/permissions, Sanctum authentication, two-factor challenges, passkeys,
password recovery, and browser account switching. Hr owns employee employment/profile rules;
Delivery owns driver eligibility and driver workflows. Those identities are checked during
authentication rather than becoming independent authentication stores. Both Flutter applications
consume this module's login, two-factor, and logout contracts.

## Important source map

- `app/Services/Auth/AuthService.php` — login identity checks and token issuance.
- `app/Services/PasswordRecovery/` — recovery identity, configuration and reset workflow.
- `app/Services/TwoFactor/` — challenge verification, delivery and attempt limits.
- `app/Services/Auth/PasskeyChallengeService.php` — login/registration challenge and credential
  lifecycle.
- `routes/api/v1.php` — public challenge routes, throttles and authenticated account administration.
- `resources/assets/js/auth/` — dedicated auth manifest, flows and multi-account store.
- `resources/assets/js/manifest.ts` — user administration registration.

## Local invariants and sensitive flows

- Preserve active-user and linked employee/driver eligibility checks before issuing a token,
  including completion of a deferred two-factor login. A valid password or cached challenge alone is
  insufficient.
- Challenges are purpose-specific, expiring and single-use. Registration additionally binds account
  state and session scope. Keep fingerprint checks, challenge consumption, attempt limits and resend
  cooldowns in their services; do not replace them with frontend timers.
- Password recovery separates identity selection, code verification and reset authorization.
  Preserve cache locks and account-row checks across these steps. Do not expose recipient details or
  turn recovery responses into account enumeration.
- Passkey registration belongs to the authenticated account and session. Preserve
  current-password/two-factor requirements and challenge binding when changing security settings.
  Keep WebAuthn configuration validation separate from browser availability detection.
- Token lifetime and revocation affect browser and both mobile clients. The browser auth store can
  hold multiple accounts: token replacement, logout and storage cleanup must target the intended
  account and update its authenticated state.
- Role permissions are server authorization data. Default-role seed changes must preserve
  permissions needed by optional modules without forcing those modules to load. Authentication
  events feed ActivityLog; redact codes, credentials and token-bearing payloads.

## Testing and validation

Use `tests/Feature/AuthIdentityTest.php`, `AuthTokenSecurityTest.php`, `TwoFactorAuthTest.php`,
`PasskeyAuthTest.php`, `PasskeyRegistrationSecurityTest.php` and `PasswordRecoveryTest.php` for the
affected flow. Account fingerprint/challenge coverage is under `tests/Unit/`.
`tests/Feature/TwoFactorMySqlConcurrencyTest.php` exercises real concurrency and needs a disposable
MySQL target. Browser regressions belong beside the auth store and two-factor/passkey pages in
`resources/assets/js/auth/`. Check actual consumers in both Flutter auth repositories for contract
changes.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md),
[optional-module-integration](../../.agents/skills/optional-module-integration/SKILL.md) for the
corresponding specialized changes.
