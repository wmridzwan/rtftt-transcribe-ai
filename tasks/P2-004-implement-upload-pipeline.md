# P2-004 — Upload Pipeline Hardening and Operational Verification

## Status

DONE

P2-004 is closed as already covered. P2-003 independently implements and
verifies the complete upload pipeline including staging, validation, metadata
derivation, checksum, opaque private promotion, MediaFile persistence, retry,
compensation, and ownership isolation. The remaining operational concerns
(staging cleanup, orphan reconciliation) are handled by P2-004A and P2-004A1.

## Ownership

Implementation Owner: To be assigned only after the Human Product Owner
authorizes a bounded task.

Reviewer: Claude Code after implementation, if implementation is authorized.

## Authorized Phase

Phase 2 continuation candidate only. The repository-defined Phase 2 upload
workflow is already implemented and closed as P2-003. This record must not be
used to reopen or reimplement that workflow.

## Current State Assessment

P2-003 — `tasks/P2-003-implement-real-upload-ingestion-workflow.md` — is
recorded as DONE after the third-pass independent review returned VERIFIED in
`reviews/P2-003-independent-review.md`. `plan.md` and the closure sections of
`CURRENT_STATE.md` record the same state. The review preserves earlier
CHANGES_REQUESTED passes as historical evidence; its operative verdict is the
third-pass VERIFIED result.

P2-003 already covers the original broad “upload pipeline” objective:

- authenticated normal `multipart/form-data` receiving;
- exact 500 MiB application validation;
- the approved extension/MIME matrix and one-file rule;
- temporary staging, server metadata derivation, checksum generation, and
  opaque private-storage promotion;
- authenticated ownership and existing folder authorization;
- `upload_attempt_id` retry/idempotency and the unique owner/attempt boundary;
- persistence as `MediaStatus::Uploaded` followed by Media Detail;
- promotion, persistence, and ambiguous-commit compensation;
- browser progress without claiming committed completion early; and
- focused, regression, static-analysis, and frontend verification.

The remaining repository-defined work is not another upload implementation.
P2-002B, ADR-009, and the P2-003 completion notes leave staging cleanup and
private-storage orphan reconciliation as future operational work. The current
service performs best-effort compensation and logs an orphan candidate when a
delete is not confirmed, but the repository does not contain a cleanup command,
reconciliation command, scheduler, persisted upload-attempt state machine, or
operational dashboard for those future behaviors.

The third-pass P2-003 review has one LOW, non-blocking test-hardening note: the
ambiguous duplicate-key test does not independently assert the durable duplicate
object directory is empty or constrain the delete call count. This does not
invalidate P2-003 and must not be used to justify reopening its verified scope.

## Recommended Disposition

`SPLIT_P2-004_BEFORE_AUTHORIZATION`

The name “Upload Pipeline” is too broad because its implementation is already
P2-003. The legitimate remainder consists of separable operational concerns:
staging cleanup, orphan reconciliation, and any additional observability or
test-only hardening. Those concerns must not be authorized as one undifferentiated
reimplementation task.

If the Human Product Owner decides that operational cleanup is not required at
this stage, the alternative disposition is
`CLOSE_P2-004_AS_ALREADY_COVERED`. If cleanup or reconciliation is required,
authorize only the selected bounded follow-up after the work packages below are
split and its execution surface is chosen. This planning record does not make
that Product Owner decision.

## Proposed Objective After Splitting

For any authorized successor task, preserve the verified P2-003 upload contract
while implementing or verifying only the selected, already-defined operational
hardening behavior from P2-002B and ADR-009. The successor task must not change
how a successful upload is received, promoted, persisted, retried, or shown to
the user unless a separate decision explicitly changes that contract.

## Candidate Scope for Separate Authorization

The following are candidate work packages, not one currently authorized scope.

### A. Staging cleanup execution

Implement the execution surface for the existing 24-hour staging-retention
contract, only after its command/scheduler ownership and runtime invocation are
explicitly selected. Cleanup must claim or lease an eligible artifact, defer
while a retry is active, and preserve the same attempt identity if the source
must be re-staged. It must not silently mint a new attempt identifier.

### B. Private-storage orphan reconciliation

Implement the execution surface for the existing orphan-candidate contract,
only after its invocation and failure-recording mechanism are selected. It must
defer active or young candidates, avoid deleting referenced objects, delete only
unclaimed old unreferenced objects, retry or record failed deletes, and never
create a `MediaFile` by adopting an orphan automatically.

### C. Targeted verification hardening

Optionally add only tests that prove a selected cleanup or reconciliation
behavior. The LOW P2-003 duplicate-race assertion gap may be a small independent
test follow-up, but it is not a P2-003 blocker and must not be bundled with an
unrelated operational implementation without an explicit reason.

### D. Observability and error-surface decision

The current contract defines orphan-candidate error logging, but it does not
define a broader event schema, metrics, alerting policy, or normalized upload
error taxonomy. Do not add those by implication. If production-oriented
observability is required, record the required fields, destination, retention,
privacy boundary, and ownership as a separate Product Owner/architecture
decision before implementation.

## Established Contracts It Must Preserve

- P2-001 / P2-001A: exact product boundary of `524,288,000` bytes, approved
  media matrix, single-file uploads, no duration rejection, and duplicates
  allowed.
- P2-002 / ADR-009: temporary staging → server validation → metadata
  derivation → opaque private promotion → `MediaFile` persistence → Media
  Detail.
- P2-002A: media storage is accessed through the configured private disk and
  the public-disk boundary remains rejected.
- P2-002B: owner-scoped `upload_attempt_id`, same-attempt retry behavior,
  separate identifiers for intentional duplicates, best-effort compensation,
  no automatic orphan adoption, and claim/lease-aware future cleanup.
- P2-002C: server-owned lowercase 64-character SHA-256 checksum; client values
  cannot override it.
- P2-003: normal Laravel multipart transport, the existing controller/service
  boundary, `MediaStatus::Uploaded`, Media Detail navigation, no Phase 3 side
  effects, and the verified regression baseline.
- ADR-009: no general persisted upload-attempt state machine is part of the
  existing contract.

## Architecture and Contract Boundary

The existing P2-003 boundary remains:

1. `MediaUploadController` receives one authenticated multipart request.
2. `MediaIngestionService` validates request/file metadata, stages under
   `media/.staging/{owner}/{attempt}/`, computes the server checksum, promotes
   to an opaque `media/{MediaUuid}/...` path, persists `MediaFile`, and removes
   staging after a committed result.
3. The owner/attempt unique database boundary makes a same-attempt retry return
   the committed record rather than insert another record.
4. Failure compensation removes attempt-owned staging and promoted objects when
   safe; an unconfirmed delete is logged as an orphan candidate and is never
   silently adopted.

P2-004 must not introduce a second upload entrypoint, a second storage identity,
another checksum source, another retry key, or a competing MediaFile lifecycle.
Any cleanup or reconciliation surface must operate against these existing
boundaries and must not change a committed `MediaFile` to `Processing` or
`Ready`.

## Dependencies and Readiness

Satisfied repository dependencies:

- P2-001, P2-001A, P2-002, P2-002A, P2-002B, and P2-002C are DONE after
  independent verification.
- P2-003 is DONE after the independent VERIFIED third pass.
- The checksum and upload-attempt migrations exist, including the unique
  `(user_id, upload_attempt_id)` constraint.
- `config/media.php`, `MediaFile::storage()`, the upload controller/service,
  and the current P2-003 tests are the live implementation baseline.

Not yet satisfied for a single P2-004 implementation authorization:

- the specific work package has not been selected;
- the execution surface for cleanup/reconciliation has not been approved;
- invocation, scheduling, failed-delete recording, and operational visibility
  are not defined as one bounded task; and
- broader observability or normalized error behavior would require a new
  decision rather than being inferred from the P2-003 implementation.

These are planning/authorization blockers, not evidence that P2-003 is broken.

## Task Decomposition Record

| Candidate | Purpose | Likely affected surface | Acceptance focus | Required tests |
|---|---|---|---|---|
| P2-004A | Execute the existing staging-retention contract | The separately approved cleanup invocation surface, its tests, and task record | 24-hour eligibility, claim/lease, active-retry deferral, same-attempt identity, no premature deletion | Eligible/young/active-race cases and cleanup idempotency |
| P2-004B | Reconcile private-storage orphan candidates | The separately approved reconciliation surface, storage tests, and task record | Never delete referenced objects, delete only eligible unreferenced objects, retry/record failed deletes, never adopt | Referenced, young, old, failed-delete, and rerun cases |
| P2-004C | Close a narrowly selected verification gap | Tests and the task/review record only unless a defect is proven | Preserve P2-003 behavior while proving the selected missing assertion | The exact branch under review; no broad upload retest by implication |
| P2-004D | Define observability if required | Decision/architecture records before implementation | Explicit fields, destination, privacy/retention, and operational owner | Contract tests only after the decision exists |

No subtask is READY. No implementation file set is authorized by this table.

## Acceptance Criteria for Any Authorized Successor

The selected successor task must independently demonstrate, as applicable to
its split scope:

1. Existing P2-003 focused and full regression tests remain passing.
2. Same-attempt retry still returns one committed record and does not create a
   second durable object.
3. Separate intentional attempts remain allowed to create separate records.
4. Failed operations do not leave an invalid `MediaFile` or an unsafe durable
   object; unconfirmed cleanup remains an orphan candidate.
5. Referenced private objects are never deleted by cleanup/reconciliation.
6. Orphans are never automatically adopted into a new `MediaFile`.
7. The configured private storage boundary and opaque paths remain intact.
8. No `MediaStatus` or upload-attempt contract is broadened without a recorded
   decision.
9. No transcription, processing, queue, worker, or Phase 3 side effect occurs.
10. Acceptance, tests, and independent verification are traceable to the
    selected work package rather than to the already-closed P2-003 scope.

## Required Verification

For a future implementation, the selected task must run the relevant existing
checks and record exact results:

- focused tests for the selected cleanup/reconciliation behavior;
- `php artisan test --compact` for regression coverage;
- `vendor/bin/pint --test --format agent` when PHP files change;
- `vendor/bin/phpstan analyse --no-progress` when analyzed PHP surfaces change;
- `npm run build` only when frontend assets change; and
- `git diff --check` plus an inspected scoped diff before staging.

The current P2-003 suites already cover the product boundary, media matrix,
private storage, checksum, ownership, retry, compensation, no-side-effect, and
receiving-path behavior. Do not duplicate those tests unless a selected change
actually regresses or extends that contract.

## Explicit Non-Scope

Do not use P2-004 to:

- reimplement or redesign the P2-003 upload controller, UI, service, staging,
  promotion, checksum, storage, or retry contract;
- lower or reinterpret the 500 MiB product boundary;
- add FFprobe, FFmpeg, probing, conversion, transcoding, extraction, or
  waveform generation;
- add transcription, faster-whisper, translation, provider routing, queues,
  Redis, Horizon, workers, callbacks, or Phase 3 behavior;
- introduce a general upload-attempt state machine;
- change ownership, multi-tenancy, collaboration, admin-as-user, rename,
  folder, or media deletion semantics;
- add transcript export or processing behavior;
- redesign retention, privacy, legal, or production policy;
- add deployment, backup/restore, rollback, monitoring, or production
  configuration by implication; or
- authorize P2-005 or any later task.

## Implementation-Isolation Plan

The worktree is materially dirty with pre-existing governance, Phase 1, P2-003,
test, migration, configuration, and untracked artifacts. That state is part of
the owner’s existing work and must not be reset, stashed, moved, overwritten,
or broadly staged.

If a split successor is authorized:

1. Start from an explicitly agreed baseline that includes the accepted P2-003
   artifacts, using a separate branch or worktree where possible.
2. Keep the current dirty worktree untouched and available as the owner’s
   working state.
3. Classify the baseline and every changed file before implementation.
4. Limit changes to the selected cleanup/reconciliation surface, its tests, and
   the task/state records required by the State-to-Action Contract.
5. Do not change Herd/PHP/Nginx or other machine configuration unless a
   separate environment decision authorizes it; P2-003’s local receiving-path
   verification is not a reusable production guarantee.
6. Before staging, inspect status, stage only the selected task’s files, run
   `git diff --check`, inspect the staged diff, and confirm no P2-003 regression,
   application expansion, migration, runtime, P2-005, or Phase 3 files are
   included.
7. Do not commit or push until the selected task has passed independent review
   and the Product Owner’s separate continuation decision permits closure.

## Documentation and State Handling

This planning record does not update `plan.md`, `CURRENT_STATE.md`,
`DECISIONS.md`, or `DECISION_QUEUE.md`. Those files should remain unchanged
until the Human Product Owner selects a disposition and explicitly authorizes
the resulting task or closes the candidate as covered.

The historical CHANGES_REQUESTED passages in the P2-003 review must remain
unchanged. If the older “awaiting independent review” paragraph in the
operational-state history is later reconciled, that is a separate
documentation-only cleanup and must not be combined with P2-004 implementation.

## Review and Closure

If a successor is authorized, it follows the repository State-to-Action
Contract:

`BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE`

The implementation owner may move the task to REVIEW after self-verification.
Claude Code independently reviews it. Work closes it only after VERIFIED.
Publishing this BACKLOG record does not authorize any of those implementation
steps.

## Recommended Product Owner Decision

`SPLIT_P2-004_BEFORE_AUTHORIZATION`

P2-003 already delivered the real upload pipeline. Select whether the remaining
repository-defined cleanup/reconciliation work is needed now, split that work
into bounded tasks, and authorize only the selected task. If it is not needed
before the next approved phase gate, close P2-004 as already covered and leave
the existing P2-003 implementation unchanged.
