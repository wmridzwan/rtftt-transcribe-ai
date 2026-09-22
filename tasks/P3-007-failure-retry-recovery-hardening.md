# P3-007 — Failure / Retry / Recovery Hardening

## Status

DONE

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: Claude Code (per AGENTS.md agent model)

## Authorization

AUTHORIZED. Promoted to READY by the Human Product Owner on 2026-09-19 as part
of Phase 3 Batch 3 authorization (`DECISION-P3-BATCH3-001`). The Batch 3 owner
decisions B3-01 through B3-07 are DECIDED (see `DECISION_QUEUE.md` and ADR-018
in `DECISIONS.md`). Implementation is authorized within this contract. P3-008
execution remains dependency-gated on this task being implementation-complete
and independently VERIFIED. This authorization does not mark this task VERIFIED
or DONE and does not close Phase 3.

## Authorized Phase

Phase 3 — Real Transcription Engine (ADR-017)

## Batch

Batch 3

## Objective

Finalize failure classification and deterministic, bounded, retry-safe recovery
behavior across queue, Laravel, HTTP worker, FFmpeg, faster-whisper, and
persistence — without introducing translation, Horizon, provider redesign, or a
parallel lifecycle vocabulary.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (canonical specification)
- P3-001 through P3-006 (all prior batches; Batch 1 and Batch 2 = CLOSED)
- ADR-013 / ADR-016 and the P2-004A2 genuine-concurrency verification standard
- `reviews/PHASE3-BATCH2-independent-review.md`

## Current-State Facts This Contract Builds On

These are verified repository facts, not assumptions:

- `TranscriptionStatus` = draft, queued, preparing, transcribing, completed,
  failed, cancelled (`app/Enums/TranscriptionStatus.php`).
- `ProcessingStatus` = queued, running, completed, failed, cancelled
  (`app/Enums/ProcessingStatus.php`).
- `TranscriptionLifecycle::VALID_TRANSITIONS`
  (`app/Transcription/TranscriptionLifecycle.php:23-46`) currently makes
  `failed`, `completed`, and `cancelled` fully terminal (`→ []`). There is no
  `failed → queued` transition today.
- `TranscriptionOrchestrator::request()`
  (`app/Actions/TranscriptionOrchestrator.php:41-50`) throws for
  Completed/Failed/Cancelled transcriptions.
- `TranscriptionResultWriter::persist()`
  (`app/Actions/TranscriptionResultWriter.php:65-71`) early-returns for
  Completed/Failed/Cancelled.
- `ProcessTranscription` (`app/Jobs/ProcessTranscription.php`) has `$tries = 1`,
  no `backoff()`, no `retryUntil()`; it claims an attempt with a single guarded
  `UPDATE ... WHERE status = 'queued'` CAS (`claimAttempt()`), and no-ops on a
  newer attempt (`hasNewerAttempt()`, ordering by `ProcessingJob.id`).
- `TranscriptionFailure` already defines the full 12-category taxonomy and
  `isRetryable()` currently marks only WorkerUnavailable, WorkerTimeout,
  WorkerSaturated, ResourceExhausted as retryable
  (`app/Transcription/TranscriptionFailure.php:29-38`).
- `HttpTranscriptionProvider` maps the worker error envelope's `error_code` to a
  `TranscriptionFailure` but **discards the envelope's `retryable` boolean**
  (`app/Transcription/HttpTranscriptionProvider.php:40-54`;
  `app/Transcription/WorkerErrorResponse.php:38-52`).
- The Python worker emits `FFMPEG_FAILED` with `retryable = True`
  (`worker/main.py:91-93`), which disagrees with Laravel's
  `FfmpegFailed->isRetryable() = false`.
- `ProcessingAttemptIdentity` (`app/Transcription/ProcessingAttemptIdentity.php`)
  already records the canonical intent: retries of the same logical
  transcription use the same `transcription_id` with a new attempt id;
  retranscription uses a new transcription id.
- `failed_jobs`, `jobs`, and `job_batches` tables already exist
  (`database/migrations/0001_01_01_000002_create_jobs_table.php`).
- There is currently **no** user-facing transcription-request or retry surface;
  `TranscriptionOrchestrator::request()` is referenced only by tests. The
  transcription show view (`resources/views/transcriptions/show.blade.php`)
  renders status and processing jobs but not `error_message`.

## Scope

Failure taxonomy covering at minimum:

- MEDIA_MISSING
- MEDIA_REJECTED
- WORKER_UNAVAILABLE
- WORKER_AUTH_FAILED
- WORKER_TIMEOUT
- WORKER_SATURATED
- FFMPEG_FAILED
- RESOURCE_EXHAUSTED
- INVALID_WORKER_RESPONSE
- PROCESSING_FAILED
- PERSISTENCE_FAILED
- CONFIGURATION_ERROR

Each category: retryable?, user-safe message class, internal diagnostic handling,
terminal/recoverable semantics.

Retry requirements:

- Bounded attempts
- Explicit backoff
- Same logical transcription
- New attempt identity where appropriate
- No duplicate logical transcript
- No duplicate segments
- Safe exhaustion state

Recovery scenarios:

- Redis duplicate delivery
- Laravel worker crash before provider
- Laravel worker crash after provider result
- Worker HTTP timeout
- Python worker crash
- FFmpeg failure
- Resource exhaustion
- Malformed worker result
- Persistence transaction failure
- Persistence succeeds but queue acknowledgement fails
- Prepared audio left behind
- Retry exhaustion

## Retry Eligibility Contract

The single domain source of truth for retryability is Laravel's provider-neutral
`TranscriptionFailure::isRetryable()`. DECIDED (ADR-018): the worker envelope's
`retryable` boolean is transport metadata/advisory only and must not
independently control domain retry policy. P3-007 must make the cross-layer
contract internally consistent and must not leave contradictory retryability
semantics exposed between Python and Laravel (current disagreement: worker
`FFMPEG_FAILED: retryable=true` vs Laravel `FfmpegFailed: retryable=false`;
`HttpTranscriptionProvider` currently discards the worker flag). If
`FFMPEG_FAILED` remains non-retryable under the canonical Laravel taxonomy,
align the worker/envelope behavior accordingly or explicitly normalize it at the
provider boundary.

Proposed classification (subject to the B3-02/B3-03 policy decisions):

| Category | Retryable | Terminal on exhaustion | Safe user message class | Diagnostic handling |
|---|---|---|---|---|
| MEDIA_MISSING | No | Yes | "Media unavailable." | log class + ids; never store path |
| MEDIA_REJECTED | No | Yes | "Media file rejected." | log worker safe_message only |
| WORKER_UNAVAILABLE | Yes | Yes | "Transcription worker unavailable." | log HTTP status; no body leak |
| WORKER_AUTH_FAILED | No | Yes | "Worker authentication failed." | log class; never log token |
| WORKER_TIMEOUT | Yes | Yes | "Transcription timed out." | log timeout config + ids |
| WORKER_SATURATED | Yes | Yes | "Transcription worker busy." | log class |
| FFMPEG_FAILED | Bounded-retry only if transient | Yes | "Audio processing failed." | log class; no FFmpeg stderr to user |
| RESOURCE_EXHAUSTED | Yes | Yes | "System resources exhausted." | log class |
| INVALID_WORKER_RESPONSE | No | Yes | "Invalid transcription result." | log validator reason internally |
| PROCESSING_FAILED | No | Yes | "Transcription failed." | log exception class internally |
| PERSISTENCE_FAILED | Bounded-retry (idempotent rerun) | Yes | "Could not save transcription." | log exception class; no SQL leak |
| CONFIGURATION_ERROR | No | Yes | "Transcription is misconfigured." | log config key name only |

Layer ownership:

- **Worker/Python** determines the raw error code and an advisory `retryable`
  hint, and must always return the stable envelope
  (`error_code`, `retryable`, `safe_message`, `request_id`).
- **Provider adapter** maps the code to a `TranscriptionFailure`.
- **Domain/`TranscriptionFailure`** is authoritative for retryability.
- **Job/retry orchestrator** decides whether a retry is permitted given policy,
  attempt bound, and state.

## Attempt Model

- The canonical processing-attempt identity remains `ProcessingJob.id`
  (Batch 2 idempotency boundary; must be preserved).
- A retry creates a **new** `ProcessingJob` row; it never reuses or rewrites the
  previous attempt.
- The previous attempt remains `failed` (historical, preserved, never rewritten)
  with its `error_message`, `started_at`, and `completed_at` intact.
- Ordering is monotonic by `ProcessingJob.id`; `hasNewerAttempt()` continues to
  use `id >` so stale deliveries no-op.
- The current/latest attempt is the maximum `id` for the transcription.
- At most **one active attempt** (`queued` or `running`) may exist per
  transcription at any time. This is the invariant P3-007 must prove under
  genuine concurrency.
- Retry lineage is derivable from `transcription_id` + `id` ordering; a new
  `attempt_number` or `retry_of_id` column is **not** required unless the HPO
  selects it (see Schema Impact). ADR-017's observability `attempt_number` is
  derived (count of attempts for the transcription) and logged.

## Retry State Machine

DECIDED (B3-01): same-transcription retry with a new processing attempt. The
canonical lifecycle extension `failed → queued` is reachable only through an
explicit authorized retry action.

```text
Transcription:
  failed
    → (explicit retry request; guarded CAS failed → queued) → queued
    → preparing → transcribing → completed
                              → failed (terminal, or retryable again)

ProcessingJob (attempts):
  attempt N:   queued → running → completed
                              → failed   (terminal; preserved)
  attempt N+1: queued → running → completed | failed
```

Ownership of transitions:

- `Transcription.status`: `TranscriptionLifecycle` (extended by B3-01) plus the
  orchestrator (request/retry) and the job (preparing/transcribing/failed).
- `ProcessingJob.status`: orchestrator creates `queued`; job performs the
  `queued → running` CAS; terminal `completed`/`failed` set by the result writer
  or failure handler.
- Queue dispatch/backoff scheduling: orchestrator / retry action.

Historical failed attempts are preserved and never rewritten.

## Retry Idempotency Contract

- Repeated UI/API retry clicks, duplicate HTTP requests, and concurrent retry
  requests must be idempotent: they return/observe the **same** active attempt
  and create **at most one** new attempt.
- The retry entry point reuses the Batch 2 `request()` semantics: if an active
  (`queued`/`running`) attempt already exists, return it instead of creating
  another.
- Duplicate queue delivery remains safe via the existing CAS claim; a duplicate
  delivery that loses the claim no-ops.
- Retry after a newer attempt already exists no-ops (`hasNewerAttempt`).
- Retry after the transcription is `completed` is rejected/no-op (see Completed
  Protection).
- Correctness boundary: the retry request handler plus a DB-level guard. A
  guarded conditional `UPDATE` on the transcription (`failed → queued`, exactly
  one winner) is the primary concurrency boundary; a partial unique index over
  active attempts is defense-in-depth.

## Concurrent Retry Requests (ADR-013 standard)

Two genuine concurrent retry requests must not create conflicting active
attempts. Because this repository has already established (ADR-013, ADR-016,
P2-004A2) that `lockForUpdate()` is not a valid SQLite row-lock guarantee, the
implementation must use an already-approved mechanism:

- a single guarded conditional `UPDATE` (compare-and-set), and/or
- a partial unique index on `processing_jobs(transcription_id)` limited to
  active statuses, and/or
- the `INSERT ... OR IGNORE` pattern already accepted for P2-004A2.

A concurrency guarantee may only be claimed if it is proven by a **genuine
independent-process** test (two separate OS processes / separate SQLite
connections racing the same retry boundary, with a deterministic barrier and
independently captured per-process outcomes), mirroring
`tests/Feature/Transcription/TranscriptionClaimConcurrencyTest.php`. Sequential
in-process tests are insufficient.

## Retry Limits / Policy

DECIDED (B3-02, B3-03): **manual domain retry only** for Phase 3. No automatic
domain-level retry and no automatic backoff schedule are introduced. A retry
occurs only through the canonical explicit retry action at the
product/application boundary. Each valid manual retry request may create one new
attempt. No arbitrary lifetime retry cap is imposed; concurrency/idempotency
protection prevents multiple simultaneously active attempts. Historical attempts
remain observable/auditable. Automatic retry policy is deferred to a future
phase.

## Automatic Retry vs Transport Retry

DECIDED (B3-02): these must not be conflated:

- **Laravel transport-level retry** (`$tries`, `backoff()`, `retryUntil()`,
  `failed_jobs`) is queue redelivery of the *same* job payload.
- **Domain-level transcription retry** creates a *new* `ProcessingJob` attempt
  for the same logical transcription.

Canonical contract: keep `ProcessTranscription::$tries = 1` (or the existing
effectively-single-attempt behavior) so the transport never silently re-runs
inference. No automatic domain retry scheduler is introduced in Phase 3. Domain
retry is explicit and manual only, and is guarded by the same concurrency
boundary. A test must prove domain retry does not depend on Laravel automatic
job retries.

## Recovery After Worker Crash

DECIDED (B3-05): Phase 3 must support recovery of abandoned `running` attempts,
but recovery must **not** automatically start a new inference attempt. Batch 2
has no stale-`running` detection, lease, heartbeat, timeout, or reconciliation.
P3-007 must handle at minimum:

- worker dies after attempt becomes `running`;
- worker dies during provider inference;
- process killed after provider result but before persistence;
- queue job disappears;
- attempt remains `running` indefinitely.

Canonical semantics:

```text
running attempt
→ demonstrably stale/abandoned
→ terminal recoverable failure state
→ transcription eligible for explicit manual retry
```

The stale threshold must be derived conservatively from the actual current
provider execution timeout contract/configuration (~300s), not an arbitrary
hard-coded value. A stale-authority guard must prevent an old worker that later
resumes from completing or overwriting the newer authoritative state; the exact
guard must be defined and tested. Use the minimum additive schema needed to
prove staleness safely; do not introduce heartbeat infrastructure unless
strictly necessary. Recovery must reuse the guarded-CAS/lease precedent, not
`lockForUpdate()`. Do not implement stale → automatic new inference.

## Persistence Failure Recovery

Preserve Batch 2 guarantees:

- transcript completion is atomic (`TranscriptionResultWriter::persist()`);
- partial results do not remain;
- attempt/transcription state stays valid after rollback.

On retry, inference is **rerun**. A normalized provider result is never
persisted across attempts, and no durable raw-provider-result store is added
(respecting OD-07/OD-08 and the provider-neutral DTO contract). If
`durable_until_terminal` retention is configured, prepared audio may survive,
but inference still reruns. Inference must remain outside any DB transaction.

## Completed Transcription Protection

DECIDED (B3-04):

- A `completed` transcription is protected: the retry action must reject or
  safely no-op.
- `TranscriptionResultWriter::persist()` continues to no-op on `completed`.
- Transcript text, detected language, segments, completion metadata, and
  successful attempt history must not be overwritten.
- Retranscription/reprocessing of completed media is a separate future product
  feature and is outside Phase 3. It must not be silently enabled.

## User-Facing Failure State

Minimum contract (keep changes minimal; do not redesign the transcription UI):

- show the persisted, already-sanitized `error_message` (never a raw
  provider/internal exception);
- show attempt status and attempt count;
- show a retry action only when the transcription is retry-eligible under the
  resolved policy;
- show queued/running state while a retry is in flight;
- never leak storage paths, tokens, SQL, or FFmpeg/Python stderr.

The retry action's authorization must use the existing `TranscriptionPolicy`
(`update`) so cross-user retry is impossible.

## Observability / Audit

Reuse existing schema/logging; no new observability platform:

- attempt id (`ProcessingJob.id`), status, `started_at`, `completed_at`,
  `processing_seconds`, safe `error_message`, `job_uuid`, `stage`;
- ordering by `id`; derived `attempt_number`;
- structured logs already present in `ProcessTranscription` and
  `TranscriptionOrchestrator` (`transcription_id`, `processing_attempt_id`,
  `failure`, `retryable`, `request_id`).

## Schema Impact

- **Additive only.** The primary expected change is a partial unique index on
  `processing_jobs(transcription_id)` limited to active statuses
  (`queued`, `running`) to enforce the one-active-attempt invariant, plus
  possibly a supporting `(transcription_id, status)` index.
- No column changes are required for attempt ordering/lineage (derived).
- If the HPO selects explicit lineage/attempt-number columns (B3-05 option or a
  separate decision), those are additive nullable columns.
- Historical migrations must not be modified. A new additive migration is
  required; `down()` must reverse it cleanly.

## Dependencies

- P3-006 (queue orchestration + CAS claim) — DONE.
- Resolution of owner decisions B3-01 through B3-05.

## Acceptance Criteria

1. Retryable vs terminal is deterministic and driven by the domain taxonomy.
2. Retry counts are bounded (per resolved policy).
3. Backoff is explicit.
4. No infinite retry or resource busy-loop.
5. Worker outage retries safely (when authorized by policy).
6. Configuration/security failures do not loop.
7. Persistence recovery is safe and atomic.
8. Logical transcription identity is preserved (same `transcription_id`).
9. Duplicate transcript/segments are prevented.
10. Exhaustion produces a deterministic terminal `failed` state.
11. Safe user-facing errors; no provider/internal leak.
12. Detailed internal diagnostics preserved.
13. Temporary artifact recovery/cleanup verified.
14. A genuine independent-process test proves exactly one new active attempt
    under concurrent retry requests.
15. Old failed attempts remain historical; stale jobs cannot process newer
    state; the provider executes only for the winning attempt.
16. Completed-state behavior is explicit and protected.
17. Retry cannot cross the user ownership boundary.
18. Domain retry does not depend on Laravel automatic job retries.
19. Full regression suite green; Pint clean; PHPStan 0 errors.

## Test Requirements

Implementation-ready acceptance tests (see `PHASE3-BATCH3-PLANNING.md` §Test
Matrix for the full matrix):

- failed transcription becomes retry-eligible correctly;
- retry creates exactly one new attempt;
- previous failed attempt remains historical;
- duplicate retry request is idempotent;
- two independent concurrent retry requests create only one new active attempt
  (genuine independent processes);
- stale previous queue jobs cannot claim a newer attempt;
- retry after completion is rejected/no-op;
- retry cannot cross the user ownership boundary;
- provider executes only for the winning attempt;
- a new attempt can complete successfully;
- a second failure remains internally valid;
- safe error handling (no leak);
- no-speech remains success and is never retryable failure;
- inference remains outside any DB transaction;
- persistence remains atomic;
- domain retry does not accidentally depend on Laravel automatic job retries.

## Out of Scope

- Translation
- Hosted ASR fallback
- Multi-provider routing
- Horizon
- Retranscription of completed transcripts (unless B3-04 selects otherwise)
- Provider abstraction redesign
- Full transcription UI redesign
- Phase 4 work

## Implementation Notes

Implemented 2026-09-19 against ADR-018 and this contract. Manual domain retry
only; no automatic retry scheduler or backoff. `ProcessTranscription::$tries`
remains `1`.

### Files Changed

- `app/Transcription/TranscriptionLifecycle.php` — canonical `failed → queued`
  retry re-open transition; all other exits from `failed` remain invalid.
- `app/Models/ProcessingJob.php` — `failure_code` fillable + cast to the
  provider-neutral `TranscriptionFailure` enum.
- `app/Actions/TranscriptionResultWriter.php` — P3-007 stale-authority guard
  (a recovered/superseded attempt never persists a result) and clears
  `failure_code` on successful completion.
- `app/Jobs/ProcessTranscription.php` — records the normalized `failure_code`
  on attempt failure; transport remains single-attempt.
- `app/Actions/TranscriptionRetry.php` (new) — manual retry operation:
  ownership check, taxonomy-derived eligibility, guarded CAS
  `failed → queued` + new attempt inside one transaction, idempotent
  duplicate/loser handling, canonical dispatch.
- `app/Actions/StaleTranscriptionAttemptRecovery.php` (new) — guarded
  `running → failed` recovery with a stale-authority guard; never auto-runs
  inference; threshold derived from the provider timeout + 60s margin.
- `app/Console/Commands/RecoverStaleTranscriptionAttempts.php` (new) —
  explicit `transcription:recover-stale-attempts` operation.
- `app/Console/Commands/TranscriptionRetryRaceWorker.php` (new, hidden,
  testing-only) — independent-process concurrency harness invoking the real
  retry action.
- `app/Transcription/HttpTranscriptionProvider.php` — documents that the
  worker `retryable` flag is advisory and the Laravel taxonomy is authoritative.
- `app/Http/Controllers/TranscriptionController.php`,
  `app/Http/Controllers/TranscriptionActionController.php`,
  `routes/web.php`, `resources/views/transcriptions/show.blade.php`,
  `resources/views/layouts/app/sidebar.blade.php` — minimal retry surface:
  safe failure message, retry action only when eligible, error flash.
- `config/transcription.php` — `attempt_stale_seconds` (derived default).
- `config/database.php` — SQLite `busy_timeout` default so the retry CAS
  transaction waits for a concurrent writer instead of failing.
- `database/migrations/2026_09_19_000001_add_failure_code_to_processing_jobs_table.php`
  (new, additive) — normalized domain failure classification.
- `database/migrations/2026_09_19_000002_add_active_attempt_unique_index_to_processing_jobs_table.php`
  (new, additive, SQLite partial unique index) — defense-in-depth for the
  one-active-attempt-per-transcription invariant.
- `worker/main.py` — `FFMPEG_FAILED` advisory `retryable` aligned to `false`.
- Tests: `tests/Feature/Transcription/TranscriptionRetryTest.php`,
  `TranscriptionRetryConcurrencyTest.php`,
  `StaleTranscriptionAttemptRecoveryTest.php`,
  `TranscriptionRetryHttpTest.php`,
  `tests/Unit/Transcription/FailureTaxonomyReconciliationTest.php`,
  `TranscriptionLifecycleRetryTest.php`, `worker/tests/test_main.py`.

### Design

- Retry eligibility is derived from the latest processing attempt's
  `failure_code` via `TranscriptionFailure::isRetryable()`. Worker-supplied
  retryability is advisory only and is never trusted.
- Correctness boundary: a single guarded conditional `UPDATE`
  (`WHERE status = 'failed'`) inside a transaction that also creates the new
  attempt. `lockForUpdate()` is not used as the guarantee. The partial unique
  index is defense-in-depth only.
- `failure_code` stores the normalized domain classification (not raw provider
  metadata). No raw provider result is persisted across attempts; retry reruns
  inference.
- Stale recovery fails the attempt with a guarded `running → failed` CAS and
  fails the transcription only while it is still in a processing state and the
  attempt is the authoritative latest attempt. Recovery never dispatches.
- The result writer refuses to persist for a recovered/superseded attempt, so
  an obsolete worker that resumes after recovery + retry cannot overwrite newer
  state.

### Deviations / frozen-contract reconciliation

- Adding the partial unique active-attempt index made three frozen tests that
  intentionally created two concurrently-active attempts for one transcription
  contradict ADR-018's one-active-attempt invariant. Per the task's
  "direct contradiction with ADR-018" allowance, they were updated to the
  realistic post-retry shape (superseded attempt terminal, newer attempt
  active): `tests/Feature/Transcription/ProcessTranscriptionJobTest.php`,
  `tests/Feature/Transcription/TranscriptionQueueOrchestrationTest.php`, and
  `tests/Feature/ModelRelationshipTest.php`.
- `tests/Unit/Transcription/DomainContractTest.php` was updated for the
  canonical `failed → queued` lifecycle extension.
- No other Batch 1/Batch 2 behavior was changed. No automatic retry, Horizon,
  translation, or provider redesign was introduced.

### Verification

- New P3-007 focused tests: `TranscriptionRetryTest` 11 passed,
  `TranscriptionRetryConcurrencyTest` 1 passed (genuine two-process race; run 5x
  stable; mutation of the CAS guard makes it fail), `StaleTranscriptionAttemptRecoveryTest`
  9 passed, `TranscriptionRetryHttpTest` 5 passed; unit
  `FailureTaxonomyReconciliationTest` + `TranscriptionLifecycleRetryTest` 6 passed.
- Full PHP suite: 366 total, 365 passed, 1 skipped (pre-existing 2FA),
  2 pre-existing warnings, 1152 assertions.
- Pint clean on changed files; PHPStan 0 errors (`--memory-limit=1G`).
- Python worker suite: 36 passed (33 pre-existing + 3 new).

## Review

Review File: `reviews/P3-007-independent-review.md`
Review Status: VERIFIED (independent review, 2026-09-19). Original MEDIUM-1 was
reconciled to LOW-1 in the review artifact's §20 (Verdict Reconciliation);
LOW-1, INFO-1, INFO-2, and INFO-3 are explicitly non-blocking and preserved as
historical/non-blocking findings.

## Closure

Closed as DONE by the Human Product Owner on 2026-09-19
(DECISION-P3-007-CLOSURE-001), based on the completed independent verification
(`reviews/P3-007-independent-review.md`: P3-007 = VERIFIED; no BLOCKER/HIGH/
MEDIUM; MEDIUM-1 reconciled to LOW-1; INFO-1/INFO-2/INFO-3 non-blocking).

Canonical transition: VERIFIED → (HPO closure decision) → DONE.

Canonical history (preserved, not rewritten):

```text
implementation
→ REVIEW
→ independent review: VERIFIED with MEDIUM-1
→ review reconciliation: MEDIUM-1 downgraded to LOW-1
→ final VERIFIED
→ HPO closure
→ DONE
```

Non-blocking findings remain historical and are not promoted into scope:

- LOW-1: `attempt_stale_seconds` override validation/clamp (optional future
  hardening; not required before closure).
- INFO-1: mutation-proof of the CAS guard not independently re-executed in the
  review session.
- INFO-2: pre-existing `lockForUpdate()` observation under SQLite
  (ADR-013/ADR-016 accepted at Batch 2 closure).
- INFO-3: deferred admin actor-vs-owner semantics.

Closure is governance/state reconciliation only; no implementation change is
authorized by this closure. P3-008 remains READY and dependency-gated; Phase 3
is not closed.

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
