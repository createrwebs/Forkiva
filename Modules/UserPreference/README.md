# UserPreference

This module stores JSON preferences by `(user_id, identifier)`. Server-side code uses
`UserPreferenceServiceInterface` with a persisted `Modules\User\Models\User`:

```php
$preferences = app(\Modules\UserPreference\Services\UserPreference\UserPreferenceServiceInterface::class);
$preferences->set($user, 'ui.theme', 'dark');
$theme = $preferences->get($user, 'ui.theme', 'system');
$preferences->setMany($user, ['ui.theme' => 'dark', 'ui.density' => 'compact']);
$preferences->set($user, 'private.signature', 'Hello', encrypted: true, translatable: true, locale: 'en');
```

Identifiers use lowercase letters and numbers separated by `.`, `_`, `:`, or `-`, up to 191 bytes.
Values are JSON encoded. Once a preference is encrypted or translatable, later plain writes preserve
those flags. A write locks the user row before upserting so first writes to the same key are serialized.
Single-key reads use the `(user_id, identifier)` unique index; listing streams rows from one query.

Authenticated HTTP routes under `/api/v1/preferences` expose only `ui.*` identifiers:

| Method | Path                        | Body                                     |
| ------ | --------------------------- | ---------------------------------------- |
| GET    | `/preferences`              | Map of the current user's UI preferences |
| GET    | `/preferences/{identifier}` | Stored value; absent values omit `body`  |
| PUT    | `/preferences/{identifier}` | `{ "value": ... }`                       |
| DELETE | `/preferences/{identifier}` | Empty success response                   |

HTTP values must be JSON compatible, at most 16 KiB and 16 nesting levels. Product-specific preference
defaults and validation should be added when a feature adopts a key; the LMS dashboard and quick-action
registries are specific to that product and are not copied into Forkiva.

## Browser preferences

The browser loads preferences after authentication and boot data, before mounting the app. UI controls
use `useUserPreference()` to update the current view and save the following keys:

| Key               | Value                        | Behavior                                |
| ----------------- | ---------------------------- | --------------------------------------- |
| `ui.language`     | Supported locale             | Updates the locale and reloads the page |
| `ui.theme_mode`   | `light`, `dark`, or `system` | Applies the selected theme              |
| `ui.sidebar_open` | Boolean                      | Opens or collapses the desktop sidebar  |
| `ui.dashboard.layout` | Dashboard widget layout | Restores visibility, order, and positions across browsers |
| `ui.dashboard.filters` | Dashboard date and branch filters | Restores the selected dashboard scope across browsers |
| `ui.gift_card_analytics`, `ui.inventory_analytics`, `ui.reservation_analytics`, `ui.accounting_overview`, `ui.delivery_dashboard`, `ui.financial_analytics`, `ui.payroll_analytics`, `ui.hr_dashboard` | Individual analytics/dashboard layout and filters | Keeps each view's visible widgets, positions, and filter scope independent across browsers |

The sidebar also uses local storage for its initial layout before the API response arrives.
