# Media module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns stored media/folders, polymorphic model-file links, download URLs and media picker UI.
Consuming modules own which files belong to their records; Media does not own invoice/PDF rendering
or backup downloads.

## Important source map

- `app/Services/Media/MediaService.php` — uploads, folders, downloads and SVG preview response.
- `app/Models/Media.php` — disk/path handling, signed URLs and descendant deletion.
- `routes/api/v1.php` — signed unauthenticated download/preview routes and admin operations.
- `resources/assets/js/components/SinglePicker.vue` — shared single-file selection.
- `resources/assets/js/composables/media.ts` — media browser behavior.

## Local invariants and sensitive flows

- Downloads/previews bypass bearer auth only because routes require a valid signature. Preserve
  expiry and tamper protection; do not expose raw disk paths as public URLs.
- SVG is served through the sandboxed preview response with restrictive headers, not an ordinary
  public image URL. Keep CSP, no-store and nosniff handling intact.
- Deleting a file removes its stored bytes. Folder descendant deletion calls model deletion
  individually; bulk SQL deletion would bypass storage cleanup.
- Preserve disk and raw path semantics separately from generated URL/name presentation. A renamed
  display name must not change storage ownership.
- Check `model_files` links and consuming feature behavior before changing deletion or picker return
  shapes.

## Testing and validation

Use `tests/Feature/MediaDownloadSecurityTest.php` for signed URLs, expiry and SVG/download response
changes. Exercise the affected picker consumer when selection payloads change.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
