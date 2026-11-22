# Export module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns asynchronous export requests, source resolution, file lifecycle and completion/failure
notifications. Domain modules own export queries, permission names and column definitions; Report
can supply its own file generator. Export does not own domain financial calculations.

## Important source map

- `app/Services/Export/ExportService.php` — queueing, user-scoped lookup and download eligibility.
- `app/Support/ExportSourceResolver.php` — registered source resolution.
- `app/Jobs/GenerateExportJob.php` — actor restoration, generation and terminal states.
- `app/Support/ExportValueNormalizer.php` — Money/enum/date/array normalization.
- `app/Models/Export.php` — persisted request/file lifecycle.
- `resources/assets/js/composables/tableExport.ts` — shared export polling/actions.
- `resources/assets/js/components/TableExportDrawer.vue` — shared history/download UI.

## Local invariants and sensitive flows

- Resolve the registered domain source and enforce its export permission; retaining permission only
  at queue time is insufficient for later lookup/download. Preserve user and context scoping of
  history.
- Queue only after commit. The worker restores the requesting user, records
  processing/completed/failed state, emits the matching event and clears auth guards in `finally`.
  Do not leave another user’s context in a reused worker.
- A download requires completed state and an actual disk/path. Preserve retention, expiration and
  downloaded-at behavior rather than exposing arbitrary storage paths.
- Sources implementing custom file generation must keep that path; do not force their output through
  generic model exports.
- Preserve normalizer semantics and source-owned relations/columns. Exported totals must not be
  recomputed with different financial rules from the source.

## Testing and validation

Use `tests/Feature/ExportNotificationWorkflowTest.php`, `tests/Unit/ExportValueNormalizerTest.php`,
and `resources/assets/js/components/TableExportDrawer.test.ts`. Include actor/context isolation and
failed generation for worker changes.

Use [api-contract-change](../../.agents/skills/api-contract-change/SKILL.md) for the corresponding
specialized changes.
