# P2-006 — Failure / Retry Handling

## Status

DONE

P2-006 is closed as already covered by the completed P2-002B contract and
independently VERIFIED P2-003 workflow. No P2-006 implementation was required.
This closure does not authorize changes to code, tests, schema, runtime
configuration, or operational tooling.

## Authorization State

P2-006 is closed with disposition `CLOSE_P2-006_AS_ALREADY_COVERED`.

No implementation was performed or required. The closure records the existing
verified behavior; it does not create new behavior.

No P2-007, Phase 3, metadata probing, staging cleanup, lease implementation,
or later-phase work is authorized by this record.

## Ownership

Decision owner: Human Product Owner / Work under the repository State-to-Action
Contract for recording closure.

Implementation owner: None required unless a new, concrete gap is separately
identified and authorized.

Independent reviewer: Already recorded for the relevant P2-003 implementation
in `reviews/P2-003-independent-review.md`.

## Current State Assessment

### Failure and retry behavior already defined

P2-002B and ADR-009 define:

- one owner-scoped opaque `upload_attempt_id` per intentional upload;
- reuse of that identifier for completion and response retries;
- a new identifier for a separate intentional duplicate;
- a unique `(user_id, upload_attempt_id)` boundary on successful ingestion;
- best-effort compensation for validation, promotion, and persistence failures;
- no compensation after a committed `MediaFile`;
- ambiguous-result lookup by owner and attempt before replay;
- no automatic adoption of an unconfirmed orphan; and
- staging-cleanup and durable-orphan rules as future operational work.

### P2-003 implementation evidence

The verified P2-003 implementation uses the existing controller/service
boundary:

- `MediaUploadController` validates one file, rejects foreign attempt reuse,
  returns an existing owner/attempt record on response retry, and surfaces
  validation or retryable failure feedback;
- `MediaIngestionService` stages, validates, checksums, promotes, persists,
  recovers an ambiguous duplicate-key race, and compensates attempt-owned
  staging/durable objects when safe; and
- committed media remains `MediaStatus::Uploaded` and opens Media Detail
  without starting transcription or processing.

### Independently verified scenarios

The third-pass P2-003 review independently reproduced the focused and full
checks and verified the relevant behavior:

- same-attempt retry returns the committed record without a second record;
- separate intentional duplicate content remains allowed;
- cross-user attempt reuse is rejected;
- promotion failure cleans staging and creates no media record;
- persistence failure after promotion removes the promoted object and staging;
- an ambiguous duplicate-key race returns the existing record and avoids a
  second committed record;
- successful and failed request paths clean staging where deletion is
  confirmed;
- no unsafe orphan is auto-adopted; and
- no `Transcription` or `ProcessingJob` side effects occur.

The review returned VERIFIED on its third pass. Its only remaining note is LOW
and non-blocking: the ambiguous duplicate-key test does not independently
assert the durable duplicate directory is empty or constrain the delete call
count. The reviewer confirmed the service behavior by trace and did not treat
this as a P2-003 blocker.

### User-visible behavior already present

Validation failures return the existing validation feedback. Unexpected
ingestion failures return a retryable JSON error or a redirect with an error,
and the message instructs the client to retry using the same upload attempt.
Successful response retries return the existing Media Detail destination.

No separate failure/retry UX contract is missing within the accepted P2-003
boundary.

### Intentionally deferred behavior

- Out-of-band abandoned staging cleanup is tracked by P2-004A and P2-004A1.
  The lease/claim decision is deferred because the current P2-003 path has no
  shared active-attempt mechanism.
- Durable orphan reconciliation is part of the future operational cleanup
  boundary, not a missing P2-006 upload retry behavior.
- FFprobe/FFmpeg metadata probing is tracked by P2-005 and deferred to the
  canonical Phase 3 gate.

These deferred items must not be pulled into P2-006 merely because they involve
the word “failure” or “cleanup.”

## Proposed Objective / Closure Rationale

No new P2-006 implementation objective is required. The proposed objective of
generic upload failure and retry handling has already been completed by:

1. the accepted P2-002B/ADR-009 contract; and
2. the independently VERIFIED P2-003 real upload implementation.

Opening a second failure/retry task would duplicate the verified service
boundaries and create a risk of changing retry, compensation, or idempotency
semantics without a concrete defect.

## Scope Options Considered

### Option A — Close P2-006 as covered by P2-002B/P2-003

Pros:

- matches the live repository evidence;
- preserves the independently VERIFIED implementation;
- avoids duplicating data-integrity and retry logic;
- keeps deferred cleanup and Phase 3 concerns in their existing records; and
- requires no code, schema, runtime, or test changes.

Cons and risks:

- the LOW asymmetric test assertion remains available as a future optional
  follow-up;
- operators still lack out-of-band cleanup tooling; and
- any genuinely new failure behavior would require a new bounded task later.

Likely affected files: closure task record only, plus state records when Work
legitimately records closure. No application files are required.

Test requirement: no new tests for the covered scope; rely on the existing
independent P2-003 evidence.

Recommendation: selected.

### Option B — Split P2-006 into test or documentation hardening

Pros:

- could isolate the LOW ambiguous-retry durable-delete assertion;
- keeps any optional follow-up small and reviewable.

Cons and risks:

- does not represent a missing product failure/retry contract;
- risks reopening a VERIFIED task for a non-blocking review note;
- duplicates or broadens the P2-003 regression surface without a defect; and
- documentation reconciliation belongs in the relevant closure record, not a
  new implementation task.

Likely affected files would be a focused test and a separate follow-up task
record only if the Product Owner expressly wants that LOW improvement.

Test requirement: only the exact ambiguous-retry cleanup assertion, not a new
generic failure/retry suite.

Recommendation: not required now.

### Option C — Defer P2-006 operational cleanup/reconciliation work

Pros:

- respects the missing lease/claim and reconciliation architecture;
- avoids unsafe mtime-only deletion and unapproved operations tooling; and
- aligns with P2-004A/P2-004A1.

Cons and risks:

- does not provide cleanup tooling before a future operational gate;
- may require a future claim/lease decision; and
- could be confused with a missing upload retry implementation.

Likely affected files: P2-004A/P2-004A1 planning records, not P2-006.

Test requirement: future cleanup/lease race tests under the authorized cleanup
task, not under P2-006.

Recommendation: apply only to the separately tracked cleanup work; it is not a
reason to keep P2-006 open.

### Option D — Authorize a bounded P2-006 implementation

Pros:

- would be appropriate if a concrete verified defect existed.

Cons and risks:

- no such defect is identified by the live implementation or independent
  review;
- would risk weakening verified compensation or idempotency behavior;
- could accidentally implement lease, cleanup, metadata, or Phase 3 scope; and
- would conflict with the repository’s current “no other task authorized” state.

Likely affected files: application and tests, with high regression risk.

Test requirement: a new failure-specific regression test proving the defect
before any implementation authorization.

Recommendation: reject absent new evidence.

## Closure Criteria

P2-006 may be closed as already covered when the closure record confirms:

1. P2-002B and ADR-009 are DONE/accepted and define the failure/retry contract.
2. P2-003 is DONE after independent VERIFIED review.
3. Same-attempt retry, cross-user isolation, duplicate-attempt policy,
   promotion compensation, persistence compensation, ambiguous retry recovery,
   and synchronous staging cleanup are covered by the verified implementation
   and tests.
4. No unresolved BLOCKER or HIGH/MEDIUM failure/retry finding remains.
5. The LOW asymmetric durable-delete assertion is explicitly recorded as
   optional and non-blocking, without reopening P2-003.
6. Out-of-band staging cleanup and lease/claim safety remain tracked under
   P2-004A/P2-004A1 and are not silently included in P2-006.
7. Metadata probing remains tracked under P2-005 and deferred to Phase 3.
8. No application, schema, runtime, test, queue, worker, or Phase 3 change is
   required to close P2-006.

## Recommended Contract if New Work Is Later Proven Necessary

Any future failure/retry task must identify a specific uncovered behavior before
authorization and preserve these boundaries:

- entrypoint: the existing authenticated multipart controller unless a new
  boundary is separately approved;
- service: `MediaIngestionService` remains the single ingestion lifecycle
  owner;
- storage: staging and durable paths remain on the configured private disk;
- database: the owner/attempt unique boundary remains authoritative;
- retry: same attempt returns the committed record, while a new intentional
  upload uses a new attempt identifier;
- compensation: only resources created by an uncommitted attempt may be
  compensated; committed media is never deleted by retry handling;
- user behavior: failures remain retryable with the same attempt and successful
  retries return Media Detail; and
- logging: unconfirmed durable cleanup remains an orphan candidate, never an
  automatic new record.

A future task must not use P2-006 to implement staging cleanup leases, durable
orphan reconciliation, media probing, queues, workers, or processing.

## Testing Requirements

No tests are added for this closure-only record. The relevant existing tests are
the P2-003 cases for:

- same-attempt retry;
- separate intentional duplicate uploads;
- cross-user attempt isolation;
- promotion failure compensation;
- persistence failure compensation;
- ambiguous duplicate-key retry recovery;
- missing-file and unauthenticated rejection;
- staging cleanup after successful/failure paths; and
- absence of transcription and processing-job side effects.

The focused P2-003 suite is recorded as 16 passing tests / 94 assertions, and
the full suite as 185 passing / 12 skipped / 566 assertions. The independent
review reproduced the relevant checks, plus Pint, PHPStan, and the frontend
build.

The following tests must not be added under P2-006 now because their required
architecture is deferred:

- active-lease staging cleanup races;
- abandoned-staging command behavior;
- durable orphan reconciliation; and
- metadata-probe failure or timeout behavior.

## Explicit Non-Scope

P2-006 does not include:

- staging cleanup commands or scheduler automation;
- upload-attempt lease/claim implementation;
- durable orphan reconciliation;
- media metadata probing, FFprobe, or FFmpeg;
- transcription, translation, faster-whisper, queues, workers, Redis, Horizon,
  or processing jobs;
- P2-007 or any later task;
- UI redesign; or
- folder, delete, rename, download, or transcript export features.

## Risks and Regression Concerns

- Reimplementing already verified compensation could introduce orphaned objects
  or duplicate `MediaFile` records.
- A broad “failure handling” task could silently absorb deferred lease,
  cleanup, probing, or Phase 3 work.
- Treating the LOW review note as a blocker would reopen a task that already
  passed independent verification.
- Adding tests that require deferred architecture would create false pressure
  to implement unauthorized infrastructure.
- The current worktree contains unrelated governance, Phase 1, P2-003, test,
  migration, and configuration changes; no broad staging or cleanup is allowed.

## Implementation Isolation and Review

No implementation is authorized or required. If a concrete future defect is
found, publish a separate bounded task, establish an agreed baseline in an
isolated branch/worktree, classify all dirty paths, and stage only that task’s
files. The task must return through:

`BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE`

Independent review remains required for any future implementation. This closure
recommendation does not alter the historical P2-003 review artifact.

## Phase Boundary

P2-006 is limited to reconciling Phase 2 failure/retry coverage. Its closure
does not authorize P2-007, staging cleanup, media probing, FFprobe/FFmpeg,
transcription, queues, workers, Redis, Horizon, processing, or Phase 3.

## Recommended Authorization Decision

`CLOSE_P2-006_AS_ALREADY_COVERED`

The core failure/retry contract is already defined in P2-002B and implemented
and independently verified in P2-003. Remaining cleanup, lease, and metadata
concerns are explicitly deferred under P2-004A/P2-004A1 and P2-005. No concrete
P2-006 defect justifies new implementation scope.

## Next Action

None. P2-006 is closed as already covered. Do not implement P2-006, add a
generic retry suite, start P2-007, or begin Phase 3.
