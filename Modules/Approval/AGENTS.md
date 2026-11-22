# Approval module agent guide

Extends [the backend guide](../../AGENTS.md). Paths below are relative to this module.

## Ownership and boundaries

Owns reusable, versioned maker-checker requests and ordered reviewer steps. Core supplies
deterministic fingerprints and immutable audit evidence; User supplies reviewer permissions. Domain
callers own the underlying business mutation and its branch/resource authorization. There is no
standalone browser/API application here.

## Important source map

- `app/Services/ApprovalWorkflow/ApprovalWorkflowService.php` — request, approve/reject, and
  material-change invalidation.
- `app/Models/ApprovalRequest.php` — subject, payload and request version.
- `app/Models/ApprovalStep.php` — ordered threshold/reviewer evidence.
- `app/Enums/ApprovalRequestStatus.php` — pending/approved/rejected/invalidated states.
- `database/migrations/2026_08_03_000110_create_approval_tables.php` — version/step uniqueness and
  restricted ownership relations.

## Local invariants and sensitive flows

- Decisions run through the row-locked workflow service. Only pending requests can be decided; the
  maker cannot be the checker, the next pending step must satisfy its configured reviewer
  permission, and a nonblank reason is mandatory.
- A rejection rejects the request. Approval completes only when no pending steps remain;
  threshold-inapplicable steps are skipped. Do not directly update the request status to bypass
  steps.
- Request definitions require valid levels and thresholds and at least one applicable level. Invalid
  definitions must not leave partial evidence.
- Preserve payload fingerprints, versions, subject links, actor/time/reason evidence and audit
  writes. Material payload changes invalidate prior pending/approved decisions instead of silently
  reusing approval.
- Approval of a request does not itself post a journal, pay an employee, or complete its subject
  workflow.

## Testing and validation

`tests/Feature/ApprovalWorkflowTest.php` covers thresholds, maker-checker separation, reviewer
permission, invalidation, required reasons and atomic invalid definitions. Also exercise the changed
domain caller: generic approval tests do not prove its business action remains authorized.

Use [financial-code-change](../../.agents/skills/financial-code-change/SKILL.md),
[database-migration](../../.agents/skills/database-migration/SKILL.md) for the corresponding
specialized changes.
