# P3-006 — Redis Queue Orchestration + Idempotent Delivery

## Status

DONE

## Ownership

Implementation Owner: OpenCode (per AGENTS.md agent model)
Reviewer: Claude Code

## Authorization

Promoted to READY by the Human Product Owner as part of Phase 3 Batch 2
authorization (2026-09-18). Batch 2 authorized tasks: P3-004, P3-005, P3-006.
Batch 3 remains unauthorized.

## Authorized Phase

Phase 3 — Real Transcription Engine (ADR-017)

## Batch

Batch 2

## Objective

Move the verified provider/persistence path into asynchronous Redis execution.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (canonical specification)
- P3-004 (Transcript Persistence)
- P3-005 (Segment Persistence + Atomic Completion)

## Scope

Implement:

- Small queue payload (transcription_id only)
- Job state reload from database (not serialized media/provider)
- Lifecycle transitions: queued → preparing → transcribing
- Atomic processing identity (ProcessingJob per attempt)
- Duplicate-delivery protection
- Completed-state protection (no reprocessing)
- Provider invocation via TranscriptionProvider
- Atomic result writer invocation (P3-004 + P3-005)
- Safe job acknowledgement behavior
- Redis outage surfaced safely
- No Horizon dependency
- Ownership intact
- Queue/job observability (structured logs)

## Out of Scope

- Horizon (not required initially)
- Failure/retry hardening (P3-007)
- Worker implementation (P3-003)

## Dependencies

- P3-004, P3-005 (persistence semantics established)
- P3-003 (real provider available)

## Acceptance Criteria

1. HTTP request returns before inference completes.
2. Job payload contains no media binary.
3. Job reloads authoritative database state.
4. Missing media handled.
5. Missing private storage object handled.
6. Cancelled state prevents new processing.
7. Completed transcription not reprocessed.
8. Duplicate delivery does not duplicate inference where preventable.
9. Duplicate delivery cannot duplicate transcript/segments.
10. Queue acknowledgement failure does not corrupt final state.
11. Redis outage is surfaced safely.
12. No Horizon dependency.
13. Ownership remains intact.
14. Queue/job observability present.
15. All tests pass; Pint clean; PHPStan 0 errors.

## Implementation Notes

### Files Changed

- `app/Jobs/ProcessTranscription.php` (new) — asynchronous orchestration job.
- `app/Actions/TranscriptionOrchestrator.php` (new) — server-side request
  entry point: creates/reuses the processing attempt, transitions
  `draft → queued`, and dispatches the job.
- `bootstrap/providers.php` — registers the existing
  `TranscriptionServiceProvider` so `TranscriptionProvider` is
  container-resolvable. This was a latent Batch 1 gap; P3-006 cannot invoke
  the provider through the abstraction without it.
- `config/transcription.php` — `model`, `queue`, `queue_connection`.
- `app/Console/Commands/TranscriptionClaimRaceWorker.php` (new) — testing-only
  concurrency harness (Correction Cycle 1).
- `tests/Feature/Transcription/ProcessTranscriptionJobTest.php` (new).
- `tests/Feature/Transcription/TranscriptionQueueOrchestrationTest.php` (new).
- `tests/Feature/Transcription/TranscriptionClaimConcurrencyTest.php` (new,
  Correction Cycle 1).
- `tests/Unit/Transcription/ProcessTranscriptionPayloadTest.php` (new).
- `tests/Support/RecordingTranscriptionProvider.php`,
  `tests/Support/NormalizedTranscripts.php`,
  `tests/Support/TranscriptionFixtures.php` (new test support).

### Queue architecture

- Laravel queue abstraction, no Horizon. The canonical backend is Redis; the
  job dispatches on `transcription.queue_connection` (falls back to the
  application queue connection) and the `transcription` queue.
- Payload contains only `transcription_id` and `processing_attempt_id`
  (small server-controlled integers). No media bytes, PCM, paths, transcript
  content, or provider instances are serialized. The job reloads
  authoritative state from persistence.
- The queue transports identifiers, not media. Media continues to flow by
  server-controlled opaque reference.

### Job flow

```
orchestrator.request()
  create/reuse attempt (stage=transcribe, status=queued)
  draft → queued
  dispatch small-identifier job
        ↓
job: reload transcription + attempt
  terminal / mismatch / newer-attempt guards
  CAS-claim attempt (queued → running)
  queued → preparing → transcribing
  validate media ownership + private storage object
  TranscriptionProvider::transcribe()   ← outside any DB transaction
  TranscriptionResultWriter::persist()  ← short atomic transaction
```

### Idempotency / delivery safety

- Processing-attempt identity (`ProcessingJob.id`) is the delivery/idempotency
  boundary.
- Attempt claiming uses a guarded `UPDATE ... WHERE status = 'queued'`; a
  duplicate delivery that loses the claim no-ops.
- A stale attempt (a newer attempt exists for the transcription) no-ops and
  cannot revert `completed → transcribing`.
- Completed/failed/cancelled transcriptions are not reprocessed.
- The result writer is itself idempotent, so a duplicate delivery cannot
  duplicate the transcript or segments.

### Failure boundary

- Batch 2 implements only the failure behavior needed for queue execution:
  the attempt and transcription are moved to a deterministic `failed`
  terminal state with a provider-neutral safe message; the result writer's
  rollback keeps state valid. Bounded retry/backoff/recovery policy is
  P3-007 (Batch 3, not implemented).
- A Redis outage at dispatch is surfaced (the exception propagates); the
  transcription remains `queued` and is not corrupted.

### Verification

- `php artisan test --compact tests/Feature/Transcription/ProcessTranscriptionJobTest.php`
  → 13 passed, 48 assertions (12 original + 1 Correction Cycle 1 test).
- `php artisan test --compact tests/Feature/Transcription/TranscriptionQueueOrchestrationTest.php`
  → 9 passed, 35 assertions.
- `php artisan test --compact tests/Feature/Transcription/TranscriptionClaimConcurrencyTest.php`
  → 1 passed, 10 assertions (Correction Cycle 1).
- `php artisan test --compact tests/Unit/Transcription/ProcessTranscriptionPayloadTest.php`
  → 2 passed, 8 assertions.
- Queue backend actually exercised: Laravel `database` queue with real job
  serialization and `queue:work --once --queue=transcription` deserialization
  + execution (payload inspected for absence of media path/binary). `Queue::fake`
  used for dispatch-shape assertions. Real Redis was NOT available in this
  environment (no server listening on 127.0.0.1:6379); a Redis-outage test
  points the configured Redis connection at an unavailable endpoint and
  asserts safe surfacing. This is not a claim of working Redis integration.
- Pint clean; PHPStan 0 errors.

## Review

Review File: reviews/PHASE3-BATCH2-independent-review.md
Review Status: VERIFIED (P3-006 Correction Cycle 1 independent re-review,
2026-09-19, artifact §14.11). The original verdict — CHANGES_REQUESTED,
HIGH-1 (genuine independent-process concurrency evidence for the
`ProcessingJob` CAS claim) — is preserved unedited in artifact §13 and
explicitly marked superseded; it is not rewritten.

## Closure

Closed as DONE by the Human Product Owner on 2026-09-19, based on the
completed independent Batch 2 verification (artifact §14.11:
`P3-006 = VERIFIED`, `Batch 2 overall = VERIFIED`, `HIGH-1 = RESOLVED`).
Canonical transition: VERIFIED → (HPO closure decision) → DONE.

Canonical history (preserved, not rewritten):

```
READY
→ implementation
→ REVIEW
→ independent review: CHANGES_REQUESTED (HIGH-1)
→ Correction Cycle 1
→ REVIEW
→ independent re-review: VERIFIED
→ DONE
```

## Correction Cycle 1 (2026-09-18)

The independent Batch 2 review found no defect in the CAS implementation but
held that the duplicate-delivery proof was sequential (two in-process
`queue:work --once` runs), not the genuine independent-process/connection
contention that ADR-013 and the accepted P2-004A2 verification gate require
for SQLite concurrency claims.

### Changes

- `app/Console/Commands/TranscriptionClaimRaceWorker.php` (new) — testing-only,
  hidden harness command. Each spawned process opens its own SQLite connection
  to a shared file database and invokes the **real, unmodified**
  `ProcessTranscription::claimAttempt()` (via reflection) after a filesystem
  rendezvous.
- `tests/Feature/Transcription/TranscriptionClaimConcurrencyTest.php` (new) —
  spawns two genuine independent OS processes
  (`Symfony\Component\Process\Process` → separate `artisan` invocations, each
  with `DB_DATABASE` pointing at the same file-backed SQLite database). A
  two-phase barrier (`ready`/`go` sentinels, then per-PID `*.at_claim`
  sentinels) guarantees both processes are in-flight at the claim point
  simultaneously.
- `tests/Feature/Transcription/ProcessTranscriptionJobTest.php` — one added
  test proving an attempt already claimed by another worker
  (`status=running`) never reaches the provider.

### Invariant proven

```
same ProcessingJob, initial status = queued
two independent processes/connections race the guarded UPDATE
exactly one succeeds (queued -> running)
exactly one reports a failed claim
final ProcessingJob.status = running
```

Composition with existing coverage: the cross-process test proves exactly one
claimant can win the claim; the new same-process test proves a delivery that
finds the claim already taken never invokes the provider; the existing
duplicate-delivery test proves exactly-once provider invocation. Together they
cover the concurrent duplicate-delivery invariant.

### Mutation check

Temporarily removing the `WHERE status = 'queued'` guard from
`claimAttempt()` makes the new test fail (`successfulClaims = 2`), and it
passes again once the guard is restored. The guard was restored immediately;
production code is unchanged. This demonstrates the test is genuinely
sensitive to the CAS.

### Verification (Correction Cycle 1)

- `TranscriptionClaimConcurrencyTest` → 1 passed, 10 assertions.
- `ProcessTranscriptionJobTest` → 13 passed, 48 assertions.
- Full PHP suite → 334 total, 333 passed, 1 skipped, 1028 assertions, 2
  pre-existing warnings.
- Pint clean; PHPStan 0 errors (`--memory-limit=1G`).
- Python worker untouched; worker suite 33 passed.

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
