# Data imports

The Import module owns the shared upload, mapping, staged preview, validation,
history, file, and queue workflow. Domain modules implement `ImportDefinitionInterface`
under `import_resources` in their module config. Definitions currently cover
`menus`, `categories`, `products`, `units`, `ingredients`, `suppliers`, `customers`, and `users`.

## Operations

Imports keep the application's default queue connection. The browser sends
`X-Import-Batches: 1` for validation, confirmation, and retry. With a `sync`
connection, the server saves a continuation and the browser calls
`POST /api/v1/imports/{id}/advance` to process one bounded batch per request.
A shared browser processor continues when the dialog closes or the user changes app routes.
Keep the browser tab open and stay signed in; closing/reloading the tab or switching accounts
stops browser processing. Reopen the import from Recent imports to resume. Completion and
failure notifications include the file name. A persistent task panel appears when an import
dialog closes, shows progress across app routes, and reopens the exact task with its original
resource context. Finished tasks remain visible until dismissed. Requests are serialized
across imports in one tab; asynchronous imports are monitored without advancing their jobs.
The additive `requires_processing` response flag tells the browser when to advance.
Ownership and resource authorization apply to every continuation request, which has
an independent 120-per-minute rate limit. The cursor and batch changes commit together.
Older clients that do not send the header retain the existing queue behavior.

With an asynchronous default connection, supervised workers process the same jobs;
keep `retry_after` longer than worker timeouts. No dedicated import queue is required.
Staging reads only the first worksheet, including when a different worksheet is active.
XLSX/CSV staging uses queued read chunks; legacy XLS is chunked inside its staging job
because its binary reader cannot be serialized in a queue payload.
Staging and error-workbook generation still require server limits appropriate to the
maximum supported upload (50 MiB, 100 columns). The private disk must be available to
both web and queue processes.

Deploy the Import migrations before these application changes and browser assets.
After enabling product imports, run `php artisan permission:sync-permissions`
and grant `admin.products.import` to the intended roles.
The initial Import migration creates `created_by` ownership. The continuation migration
is additive. Do not remove its columns while imports are awaiting browser processing.

`imports:cleanup` retains history metadata but removes files and staged rows
30 days after a terminal status by default. The command is scheduled daily;
`import.retention_days`, `import.disk`, `import.path`, `import.chunk_size`,
`import.validation_batch_size`, `import.apply_batch_size`, and
`import.finalization_batch_size` are configurable in `config/config.php`.

## Domain behavior

- Menu imports use an explicit ID when present, otherwise the default-locale
  name within a branch. Branch-assigned users are locked to their branch.
- Category imports target one menu selected before upload. Slugs identify
  categories within that menu; `parent_slug` may refer to an existing category
  or another row in the same file. Parent links are applied after all rows are
  created or updated.
- Product imports target the selected menu. Rows match by explicit ID, then SKU,
  then default-locale name. The template includes catalog, special pricing,
  availability dates, category slugs, and tax IDs.
  Separate multiple category slugs or tax IDs with `|` or `,`. Quote cells that
  contain commas in CSV files. Use `[]` in either cell to remove all existing
  categories or taxes during an upsert; a blank cell keeps existing values.
  References must belong to the selected menu or its branch. Blank optional
  cells retain existing values in upsert mode; replace-existing clears only mapped
  optional fields. Unmapped categories, taxes, options, recipes, media, and
  inventory configuration remain unchanged. New products default to direct
  recipe consumption. Inventory consumption settings and recipes are managed
  in the product editor.
  Older drafts using `tax_codes` must remap that column to `tax_ids` and replace
  code values with IDs before validation.
- `name` and `description` use the configured default locale. Additional
  supported locales can be mapped with fields such as `name_ar` and
  `description_ar`. Unmapped locales are retained during updates.
- Unit imports match an explicit ID or the default-locale symbol; translated
  names and symbols can be mapped per locale. Ingredient and supplier imports
  match an explicit ID or a name within the branch. Branch-assigned users
  cannot import into another branch. Ingredients require an existing unit;
  imports never set current stock, and units cannot change after stock
  movements exist.
- Customer imports match a Customer-role account by ID or normalized phone
  number. Phone, username, and email uniqueness applies across all users.
  Imports do not set passwords or change account roles.
- User imports match a non-customer account by ID or username and validate the
  chosen role and branch for the importing administrator. New users receive a
  random password that is never staged in the import; an administrator must set
  their password in account editing or the user must use password recovery.
- `create_only` rejects existing records, `update_only` rejects missing records,
  `upsert` creates or updates while retaining blank optional values,
  `skip_duplicates` skips existing records, and `replace_existing` clears
  mapped optional values when blank.

Validation must finish without errors before import can be queued. The error
workbook preserves the uploaded column order and row numbers, highlights invalid
cells, and lists messages on a second worksheet. Each history item and download
is scoped to its initiating user and current menu or branch access.

## API presentation

Import `status` and `mode`, and row `status` and nullable `operation`, use the
shared translated enum shape `{ id, name }`. Requests still send enum IDs as
strings. Workflow decisions use `id`; display labels use `name`.

`created_at`, `started_at`, and `finished_at` use `dateTimeFormat()` with the
application date/time settings and applicable branch timezone. Unset timestamps
remain null. Clients display these strings directly rather than parsing them as
ISO timestamps. Deploy the updated browser assets with these resource changes;
older import UIs expecting string enums are incompatible.
