# P2-002B — Define Ingestion Compensation and Retry Contract

## Status

DONE

## Ownership

Implementation Owner: Codex
Reviewer: Claude Code

## Authorized Phase

Phase 2 checkpoint follow-up; prerequisite for any future upload workflow. This follow-up does not authorize P2-003.

## Objective

Define an executable contract for upload-attempt identity, promotion/persistence failure compensation, retry behavior, and staging-cleanup races before real ingestion is implemented.

## Context

Originating independent review: `reviews/P2-001-P2-002-independent-review.md` (MEDIUM: failure compensation and retry identity need an executable contract).

## Scope

- Specify behavior when durable promotion succeeds but database persistence fails.
- Specify retry identity and response-retry behavior.
- Specify reconciliation of orphaned durable files and staging cleanup races.
- Preserve intentional duplicate uploads while preventing misleading duplicate records from one attempt.

## Out of Scope

- Upload controller, queue, cleanup command, or other P2-003 implementation.
- FFmpeg, transcription, or processing infrastructure.

## Dependencies

- Must be completed before any real upload workflow begins.

## Acceptance Criteria

- [x] Failure boundaries and compensation rules are explicit.
- [x] Retry identity is explicit and compatible with allowed duplicates.
- [x] No upload implementation is added.
- [x] Independent review passes.

## Review Reference

`reviews/P2-001-P2-002-independent-review.md`

## Completion

Implementation notes:

- Documented the approved `upload_attempt_id` contract in `architecture.md` and
  ADR-009: the upload boundary allocates one owner-scoped identifier, which is
  reused for completion/response retries; a new intentional duplicate receives
  a new identifier; checksums remain non-unique and are never idempotency keys.
- Defined proportionate compensation for validation, promotion, persistence,
  response, and ambiguous-commit boundaries. Committed media is never
  compensated; unconfirmed cleanup becomes an orphan candidate and is never
  auto-adopted into a new `MediaFile`.
- Defined claim/lease coordination for the 24-hour staging cleanup race and
  private-storage orphan reconciliation, including defer, delete, and failed
  delete outcomes.
- Added `tests/Feature/IngestionCompensationContractTest.php` as the explicit
  Pest TODO inventory for the future workflow: retry idempotency, allowed
  duplicates, promotion compensation, ambiguous persistence, staging races,
  and orphan reconciliation.

No upload controller, queue, outbox, cleanup command, persisted state machine,
or P2-003 implementation was added.

Verification:

- `git diff --check`: PASS; no whitespace errors reported.
- Focused Pest execution: NOT EXECUTED. The repository-recorded PHP path
  `C:\Users\Admin\.config\herd\bin\php84\php.exe` is not present in this
  environment, and no `php.exe` is available on PATH.
- Static scope review: PASS. The task-specific changes are limited to the
  contract documentation, this task record, the future-work test inventory,
  and operational state records; no upload workflow or infrastructure files
  were introduced.

Files changed for P2-002B:

- `architecture.md`
- `DECISIONS.md`
- `tasks/P2-002B-define-ingestion-compensation-and-retry.md`
- `tests/Feature/IngestionCompensationContractTest.php`
- `CURRENT_STATE.md`

Independent review VERIFIED this task in `reviews/P2-001A-P2-002A-P2-002B-P2-002C-independent-review.md`. Work reconciled the stale acceptance checklist and closed the task as DONE under ADR-010. P2-003 remains closed.
