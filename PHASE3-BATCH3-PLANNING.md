# Phase 3 Batch 3 — Planning / Decision Package

Date: 2026-09-19
Status: CLOSED — P3-007 and P3-008 DONE; Phase 3 Batch 3 CLOSED; Phase 3 CLOSED (2026-09-19)
Authority: ADR-017; ADR-018; `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md`
Scope: P3-007 (Failure / Retry / Recovery Hardening), P3-008 (Real Phase
Integration Verification)

The Batch 3 owner decisions B3-01 through B3-07 were resolved by the Human
Product Owner on 2026-09-19 and are recorded in `DECISION_QUEUE.md` and durably
in ADR-018 (`DECISIONS.md`). Batch 3 was authorized under
`DECISION-P3-BATCH3-001` and P3-007/P3-008 were promoted to READY. Both tasks
were subsequently independently VERIFIED and closed as DONE
(DECISION-P3-007-CLOSURE-001, DECISION-P3-008-CLOSURE-001), and Phase 3 Batch 3
was recorded CLOSED (DECISION-P3-BATCH3-CLOSURE-001). This package remains the
planning artifact. Phase 3 was then closed as a whole on 2026-09-19
(DECISION-PHASE3-CLOSURE-001). Phase 3 = CLOSED. Phase 4 is NOT authorized.

## HPO Decision Outcomes (2026-09-19)

| ID | Decision | Outcome |
|----|----------|---------|
| B3-01 | Failed-transcription retry semantics | APPROVED same-transcription retry; canonical `failed → queued` extension; new `ProcessingJob` per retry; prior attempt immutable |
| B3-02 | Retry trigger model | Manual domain retry only in Phase 3; no automatic domain retry; transport ≠ domain retry |
| B3-03 | Retry count / backoff | No automatic schedule; each valid manual retry may create one attempt; concurrency/idempotency prevents multiple active attempts; no arbitrary lifetime cap |
| B3-04 | Completed transcriptions | Protected; retry rejected/no-op; no overwrite; retranscription outside Phase 3 |
| B3-05 | Abandoned running-attempt recovery | Recover stale `running` → terminal recoverable failure → manual-retry-eligible; no automatic new inference; stale-authority guard; threshold from real timeout (~300s); minimum additive schema |
| B3-06 | Live Redis phase gate | Mandatory live Redis evidence before P3-008 VERIFIED; no database-queue substitute |
| B3-07 | Real FFmpeg + faster-whisper gate | Mandatory real self-hosted end-to-end run before P3-008 VERIFIED; mocks insufficient; small fixture; code-switching may supplement with normalized fixtures |

Failure-taxonomy authority: Laravel's provider-neutral `TranscriptionFailure`
is the authoritative domain retryability source; the worker `retryable` flag is
advisory only, and the cross-layer contract must be made internally consistent.

---


## A. Repository State Confirmed

This section records the repository state as confirmed at planning time
(before the 2026-09-19 authorization); the pre-authorization values below are
historical. Current status is DECIDED/AUTHORIZED (see the header and section J).

Verified directly against the repository (not assumed from the brief):

- Phase 2 = COMPLETE_WITH_DEFERRED_DEBT (closed 2026-09-17).
- Phase 3 Batch 1 = CLOSED (2026-09-18); P3-001/P3-002/P3-003 = DONE; canonical
  model `large-v3`.
- Phase 3 Batch 2 = CLOSED (2026-09-19); P3-004/P3-005/P3-006 = DONE; Batch 2
  independent review VERIFIED; P3-006 HIGH-1 resolved via Correction Cycle 1.
- P3-007 = BACKLOG, P3-008 = BACKLOG.
- Batch 3 = NOT AUTHORIZED. No `DECISION-P3-BATCH3-*` entry exists.
- Phase 3 = IN PROGRESS (not complete).
- Option D (ADR-013) remains in force; P2-004A/P2-004A1 remain BLOCKED
  historical debt.
- The working tree is dirty with the (already-closed) Batch 2 implementation
  plus its review artifact; no commit was made by this planning action.
- `CURRENT_STATE.md`, `plan.md`, `RTFTT-MASTER-ROADMAP.md`, and
  `DECISION_QUEUE.md` all consistently record Batch 3 as not authorized.

Conclusion: the brief's expected high-level state is confirmed by the
repository.

## B. Batch 3 Objective

Make transcription processing recoverable and retry-safe under the established
lifecycle and queue architecture, then independently verify the complete Phase 3
transcription pipeline end-to-end against the canonical contracts.

No translation. No Horizon. No provider-abstraction redesign. No revisit of
closed Batch 1/Batch 2 architecture absent a genuine blocking contradiction.

## C. P3-007 Contract Summary

See `tasks/P3-007-failure-retry-recovery-hardening.md` for the full
implementation-ready contract. Key points:

- Retryability source of truth = provider-neutral `TranscriptionFailure`; the
  worker envelope's `retryable` flag is advisory. The current
  `FFMPEG_FAILED` disagreement (worker true / Laravel false) must be reconciled.
- A retry creates a new `ProcessingJob` attempt; the previous failed attempt is
  preserved. Ordering is monotonic by `ProcessingJob.id`.
- At most one active (`queued`/`running`) attempt per transcription; enforced by
  guarded CAS plus a partial unique index, proven with genuine independent
  processes (ADR-013 standard).
- Retry requests are idempotent; concurrent/repeated retries create one attempt.
- Domain retry is distinct from Laravel transport retry; `$tries = 1` stays
  unless the owner authorizes otherwise.
- Inference reruns on retry; no durable raw provider-result storage.
- Completed transcripts are protected; retranscription is out of scope pending
  B3-04.
- Additive schema only (partial unique index); historical migrations untouched.

## D. P3-007 State Machine

```text
Transcription:
  failed
    → (retry request; guarded CAS failed → queued) → queued
    → preparing → transcribing → completed
                              → failed (terminal, or retryable again)

ProcessingJob:
  attempt N:   queued → running → completed | failed   (terminal; preserved)
  attempt N+1: queued → running → completed | failed
```

Transitions are owned by: `TranscriptionLifecycle` + orchestrator
(transcription status), the job/result writer (attempt status), and the
orchestrator/retry action (dispatch + backoff scheduling).

**Blocking contradiction discovered:** `TranscriptionLifecycle` currently makes
`failed` fully terminal (`→ []`), and both `TranscriptionOrchestrator::request()`
and `TranscriptionResultWriter::persist()` reject/early-return on `failed`.
Therefore, retry of the same logical transcription is impossible without an
explicit lifecycle decision. `ProcessingAttemptIdentity`'s own contract already
states retries reuse the same `transcription_id` with a new attempt id, which
supports re-opening. This is owner decision B3-01.

## E. P3-007 Concurrency / Idempotency Contract

- Idempotency boundary: `ProcessingJob.id` (preserved from Batch 2).
- Primary retry concurrency boundary: a single guarded conditional `UPDATE` on
  the transcription (`failed → queued`), exactly one winner.
- Defense-in-depth: a partial unique index on `processing_jobs(transcription_id)`
  restricted to active statuses.
- Reuse the P2-004A2/ADR-016 approved SQLite-safe mechanisms; do not rely on
  `lockForUpdate()`.
- A concurrency claim requires a genuine independent-process test (two separate
  OS processes, separate SQLite connections, deterministic barrier,
  independently captured per-process outcomes), modeled on
  `tests/Feature/Transcription/TranscriptionClaimConcurrencyTest.php`.
- Repeated retries return the existing active attempt (idempotent), matching
  Batch 2 `request()` reuse semantics.

## F. P3-007 Test Requirements

See the Test Matrix in section H. Minimum required tests are enumerated in the
P3-007 task contract under "Test Requirements".

## G. P3-008 Phase Verification Contract

See `tasks/P3-008-real-phase-integration-verification.md`. P3-008 is a
verification task: the Builder prepares/executes the harness and captures
evidence; Claude independently reproduces and issues the verdict; the HPO closes.
P3-008 VERIFIED is necessary but not sufficient for Phase 3 closure; the final
Phase 3 gate (spec Completion Gate items 22–23) remains a separate HPO decision.

## H. P3-008 Integration Matrix

| ID | Task | Scenario | Layer | Concurrency? | Expected Result | Evidence Type |
|----|------|----------|-------|--------------|-----------------|---------------|
| I-01 | P3-008 | Happy path: uploaded media → request → queued → worker claims → provider → persist → completed | E2E | No | Transcript + segments persisted; retrievable | Real E2E run log + DB assertions |
| I-02 | P3-008 | No speech | E2E | No | `text=""`, `speech_detected=false`, lang `und`, 0 segments, completed success; no retry | DB assertions + worker response |
| I-03 | P3-008 | Mixed languages `ms/en/zh/ta/und` | Domain + persistence | No | Per-segment language preserved; transcript dominant separate | DB assertions + fixture |
| I-04 | P3-008 | Unicode / mixed scripts | Persistence | No | Round-trip byte-identical | DB assertions |
| I-05 | P3-008 | Fractional segment timestamps | Persistence + export | No | Millisecond precision; SRT/VTT compatible | DB + formatter assertions |
| I-06 | P3-008 | Provider failure (worker down/timeout) | Job/provider | No | Deterministic failed or bounded retry per P3-007 | Job test + logs |
| I-07 | P3-008 | Malformed provider output | Provider/validator | No | `INVALID_WORKER_RESPONSE`, terminal, no partial persist | Job test |
| I-08 | P3-008 | Persistence failure | Writer | No | Atomic rollback; state valid; no false completion | Failure-injection test |
| I-09 | P3-007/008 | Retry success after transient failure | Retry | No | New attempt; completes; old attempt preserved | DB + job test |
| I-10 | P3-007/008 | Repeated retry request | Retry | No | Idempotent; one new attempt | Feature test |
| I-11 | P3-007/008 | Concurrent retry requests | Retry | **Yes (independent processes)** | Exactly one new active attempt | Two-process race test |
| I-12 | P3-007/008 | Stale old job delivered after newer attempt | Queue | No | No-op; no state regression; provider not called | Job test |
| I-13 | P3-007/008 | Retry after completed | Retry | No | Rejected/no-op | Feature test |
| I-14 | P3-008 | Cross-user media/transcript access | Ownership | No | Denied; no persistence | Policy/feature test |
| I-15 | P3-007/008 | Second failure after retry | Retry | No | Deterministic terminal failed; exhaustion state | Job test |
| I-16 | P3-008 | Queue payload contains no path/binary | Queue | No | Only ids | Payload inspection |
| I-17 | P3-008 | Duplicate queue delivery | Queue | No | Provider invoked once; no duplicate segments | Job test |
| I-18 | P3-008 | Redis outage | Queue | No | Surfaced safely; no corruption | Feature test (unreachable endpoint) |
| I-19 | P3-008 | Live Redis integration | Queue | No | Job serialized/consumed via redis driver | Real Redis run (B3-06) |
| I-20 | P3-008 | Real worker process + FFmpeg + faster-whisper | Worker | No | Real normalized multilingual result | Real worker run (B3-07) |
| I-21 | P3-008 | Ephemeral prepared audio cleanup | Worker/storage | No | Temp audio removed; original unchanged | Worker test + filesystem assertion |
| I-22 | P3-008 | Phase 1/2/Batch1/Batch2 regression | Regression | No | Full suite green; no new skips | Suite run |

## I. Owner Decisions Required

### Decision B3-01 — Retry re-open semantics for a failed transcription

#### Option A — Add `failed → queued`; retry re-opens the same logical transcription and creates a new attempt (Recommended)
Semantics: A retry request performs a guarded CAS `transcription.status: failed → queued`, then creates a new `ProcessingJob` attempt. Same `transcription_id`; new attempt id; previous failed attempt preserved.
Pros: Matches P3-007 "same logical transcription" and the existing `ProcessingAttemptIdentity` contract; preserves one transcript identity; no duplicate logical transcript; natural "retrying" UI.
Risks: Modifies a Batch 1/2 VERIFIED lifecycle contract; requires adjusting the writer's and orchestrator's terminal guards; must prove Batch 2 terminal-protection tests still hold on non-retry paths.

#### Option B — Keep `failed` terminal; each retry creates a new `Transcription` row for the same MediaFile
Semantics: Every retry is a new logical transcription.
Pros: No lifecycle change; terminal states stay immutable.
Risks: Contradicts "same logical transcription" and P3-007 AC8; duplicates logical transcripts; fragments history; UI/ownership complexity.

#### Option C — Keep `failed` terminal; P3-007 implements no retry of failed transcriptions
Semantics: P3-007 only classifies failures and prevents loops; recovery is manual (create a new transcription outside P3-007).
Pros: Zero lifecycle change.
Risks: Fails P3-007 scope and most retry/recovery acceptance criteria; leaves failed work unrecoverable; contradicts ADR-017 "retry, recovery, failure hardening".

Recommended default: Option A.
Reason: It is the only option consistent with the already-VERIFIED `ProcessingAttemptIdentity` contract and P3-007's logical-identity requirement. The lifecycle extension is narrow (`failed → queued` only) and does not introduce a parallel vocabulary.

### Decision B3-02 — Retry trigger model

#### Option A — Manual-only retry
Semantics: Retry is only ever an explicit user/admin action; no automatic retries. `$tries = 1`.
Pros: Simplest; zero risk of automatic loops; smallest change.
Risks: "Worker outage retries safely" (AC5) and "Worker outage" recovery become manual; may under-serve the task's stated intent.

#### Option B — Bounded automatic retry for retryable categories + manual retry after exhaustion (Recommended)
Semantics: Retryable failures automatically create a new attempt after backoff, up to a bound; after exhaustion the transcription is terminal `failed` but remains manually retryable if the owner permits.
Pros: Satisfies bounded/backoff/worker-outage ACs; bounded so no infinite loop; mixed model matches real operations.
Risks: Requires the attempt bound/backoff (B3-03) and stale-running recovery (B3-05) to be coherent; more tests.

#### Option C — Unlimited automatic retry with backoff for retryable categories
Semantics: Retryable failures retry indefinitely with backoff.
Pros: Maximizes eventual success for transient outages.
Risks: Directly violates "bounded attempts" and "no infinite retries"; can busy-loop or starve resources; not acceptable.

Recommended default: Option B.
Reason: The P3-007 contract explicitly requires bounded attempts, explicit backoff, and safe worker-outage retry, which is the mixed model. Concrete bound/backoff is B3-03.

### Decision B3-03 — Retry bound and backoff profile

#### Option A — Attempt-count bound: 3 attempts total (1 initial + 2 retries), exponential backoff 30s then 120s (Recommended)
Semantics: At most 3 attempts; delays 30s, 120s.
Pros: Simple, bounded, predictable; short enough to recover quickly; long enough to clear a transient outage.
Risks: A long worker outage may exhaust before recovery; manual retry remains available.

#### Option B — Time bound: retry while elapsed < 15 min, max 5 attempts, backoff 30s/60s/120s
Semantics: Both a time and count ceiling; mirrors P2-004A2's 15-minute window.
Pros: Aligns with an existing repository precedent; tolerates longer outages.
Risks: More state to reason about (time + count); 15 min may be long for interactive UX.

#### Option C — Single automatic retry only (1 initial + 1 retry), backoff 60s
Semantics: Exactly one automatic retry.
Pros: Minimal; very safe.
Risks: Weak recovery for transient outages; likely to need manual retry often.

Recommended default: Option A.
Reason: Balanced, simple, and bounded; matches the task's "bounded attempts + explicit backoff" wording without importing extra time-window complexity.

### Decision B3-04 — Retranscription of completed transcripts

#### Option A — Completed is protected; retry/reprocess forbidden; retranscription is a separate future feature (Recommended)
Semantics: Completed transcripts are immutable in P3-007; retry is rejected/no-op.
Pros: Preserves Batch 2 completion protection; no accidental overwrite; no scope creep.
Risks: Users cannot correct a bad transcript in Phase 3; deferred product need.

#### Option B — Completed may be intentionally replaced by an explicit re-run action
Semantics: A new attempt overwrites the completed transcript atomically.
Pros: Allows correction without a new logical transcript.
Risks: Destroys prior result unless versioned; expands scope; needs audit/version history; contradicts "completed not reprocessed".

#### Option C — Completed protected; a future "new transcription from same media" (new logical transcription) is allowed but explicitly outside P3-007
Semantics: No overwrite; history preserved via a separate transcription.
Pros: Safe; preserves history; clean separation.
Risks: Duplicate logical transcripts for the same media; still a separate future task.

Recommended default: Option A.
Reason: The spec explicitly places retranscription outside Phase 3 unless separately decided; Batch 2 already enforces completion protection. C is an acceptable future direction but should not be built in P3-007.

### Decision B3-05 — Abandoned running-attempt recovery

#### Option A — Timeout/lease-based recovery: a configurable stale threshold transitions a stale `running` attempt to `failed` (retryable), then normal retry policy applies (Recommended)
Semantics: e.g. an attempt `running` longer than `transcription.attempt_stale_seconds` is recovered by an explicit command/orchestrator check using a guarded CAS.
Pros: Handles worker crash/indefinite-running scenarios listed in P3-007; reuses the P2-004A2 crash-recovery precedent; testable.
Risks: Threshold tuning; must not race a legitimately long inference (set above the 300s HTTP timeout); requires genuine-concurrency proof.

#### Option B — Manual admin recovery command only
Semantics: A command marks stale `running` attempts failed; no automatic detection.
Pros: Safe; no timing heuristics; small change.
Risks: Failed/abandoned attempts linger until an operator acts; weaker "recovery" story.

#### Option C — No recovery in P3-007; defer abandoned-running handling to Phase 7 production hardening
Semantics: P3-007 only handles failures that return control to Laravel.
Pros: Smallest Batch 3.
Risks: Leaves "attempt remains running indefinitely" explicitly unhandled, contradicting the P3-007 recovery-scenario list.

Recommended default: Option A.
Reason: The P3-007 contract explicitly lists worker-crash and indefinite-running recovery scenarios; Option A addresses them with an existing, proven concurrency pattern. Threshold must exceed the provider timeout (300s).

### Decision B3-06 — Live Redis evidence for the Phase 3 gate

#### Option A — Mandatory live Redis execution before P3-008 VERIFIED (Recommended)
Semantics: A reachable Redis server, `redis` driver, real `queue:work`, retained evidence.
Pros: Directly satisfies P3-008 AC2 and the spec Completion Gate item 8; removes the Batch 2 residual INFO-1 risk.
Risks: The current environment has no reachable Redis; requires environment setup; may block P3-008 completion.

#### Option B — Accept a documented environment gap: database queue + Redis-outage test + a committed reproducible Redis integration test/CI config; record live Redis as a pre-production residual requirement
Semantics: P3-008 verifies the driver-agnostic abstraction and outage path; live Redis is deferred with an explicit recorded limitation.
Pros: Unblocks completion without a Redis server; preserves honest scoping.
Risks: Phase 3 would be accepted with the same unproven live-Redis gap Batch 2 already carries; weakens the "real Redis execution verified" claim.

#### Option C — Require live Redis only in CI (not in P3-008 acceptance)
Semantics: A CI job with a Redis service proves the path; P3-008 references CI evidence.
Pros: Reproducible and automatable.
Risks: The repository has no established CI Redis service today; effectively defers the gap.

Recommended default: Option A.
Reason: P3-008's own contract and the spec Completion Gate require real Redis execution. If the environment cannot provide Redis, Option B is acceptable only as an explicitly recorded HPO limitation (never a silent claim of verification).

### Decision B3-07 — Real faster-whisper + FFmpeg execution evidence for P3-008

#### Option A — Mandatory real end-to-end run (real worker process, real FFmpeg, real faster-whisper `large-v3`, representative fixture) with retained evidence before P3-008 VERIFIED (Recommended)
Semantics: A real, reproducible integration run in the canonical environment with retained evidence.
Pros: Directly satisfies P3-008 AC3/4/5/16 and spec Completion Gate items 9–12; highest confidence.
Risks: Requires faster-whisper/FFmpeg and a model download; CPU inference is slow (large-v3 RTF ~7.7); test cost.

#### Option B — Real run required, but permitted as a manually executed, environment-dependent integration test recorded with full environment metadata and reproducible commands
Semantics: The real run is not automated in CI but is executed and retained as evidence; residual environment risk documented.
Pros: Practical when model/worker cannot run in the default test environment; still real evidence.
Risks: Not reproducible in CI; evidence provenance depends on the operator.

#### Option C — Mock-provider verification only
Semantics: Verify with a recording/fake provider.
Pros: Fast and deterministic.
Risks: Directly violates P3-008 AC16 ("Mock-only tests insufficient; real path evidence required"); not acceptable.

Recommended default: Option A.
Reason: The P3-008 contract explicitly demands real worker/FFmpeg/faster-whisper evidence. Option B is an acceptable fallback only with the limitation explicitly recorded by the HPO.

## J. Dependency / Authorization Order

- RESOLVED: Batch 3 was authorized on 2026-09-19 via
  `DECISION-P3-BATCH3-001`; P3-007 and P3-008 were promoted to READY, both were
  independently VERIFIED and closed as DONE, and Phase 3 Batch 3 was recorded
  CLOSED (DECISION-P3-BATCH3-CLOSURE-001). Batch 1/Batch 2
  closure did not authorize Batch 3.
- P3-007 was implementation-complete and frozen before P3-008 executed the
  final integration verification; P3-008 was not independently VERIFIED until
  P3-007 was independently VERIFIED.
- P3-008 executed the final verification against the frozen P3-007 contract.
- Per the Phase 3 batch-execution exception, OpenCode proceeded sequentially
  and both tasks moved to REVIEW for one independent batch review. VERIFIED →
  DONE remained an HPO closure action.
- Phase 3 closure is a separate HPO decision after the final phase-gate review.
  Phase 3 was closed as a whole on 2026-09-19 (DECISION-PHASE3-CLOSURE-001).
  Phase 3 = CLOSED. Phase 4 is NOT authorized.

```text
BACKLOG
→ HPO Batch 3 authorization (DONE 2026-09-19)
→ READY
→ implementation (P3-007 → then P3-008)
→ REVIEW (batch)
→ independent review (Claude)
→ VERIFIED
→ HPO closure
→ DONE
```

## K. Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Duplicate retries create multiple active attempts | Guarded CAS `failed → queued` + partial unique index + genuine independent-process test |
| Stale job overwrites newer attempt | Preserve `hasNewerAttempt` + CAS claim; test old-job-after-newer |
| Automatic queue retry confused with domain retry | Keep `$tries = 1`; implement domain retry explicitly; test that domain retry is independent of Laravel retry |
| Abandoned `running` attempts | B3-05 timeout/lease recovery above the 300s provider timeout, with guarded CAS |
| Permanent failures retried forever | Deterministic retryability table + manual-only retry (B3-02/B3-03) |
| Retry destroys historical evidence | Never rewrite prior attempts; append-only attempt rows |
| Completed result overwritten unintentionally | Preserve writer completion guard; retry-after-completed test |
| SQLite concurrency behaviour | Reuse ADR-016 CAS/`INSERT OR IGNORE`; no `lockForUpdate()` reliance; real multi-process tests |
| Redis environment gap | B3-06 decision; honest scoping; no false live-Redis claim |
| Real worker/model test cost | B3-07 decision; use a short representative fixture; record model/device/RTF |
| Multi-process concurrency test flakiness | Deterministic two-phase barrier (as in `TranscriptionClaimConcurrencyTest`), repeated runs, no sleep-based correctness |
| Long inference inside a transaction | Keep provider call outside any DB transaction; assert `DB::transactionLevel()` unchanged |
| Phase 4 scope creep | Explicit non-scope list; translation/Horizon/diarization excluded |
| Lifecycle change regresses Batch 2 | Run full regression; scope the new transition to `failed → queued` only; independent re-verification |
| Worker `retryable` flag vs Laravel taxonomy | Make Laravel taxonomy authoritative; reconcile `FFMPEG_FAILED`; test the mapping |

## L. Migration / Compatibility Review

- P3-007 is expected to require **additive schema only**: a partial unique index
  on active `processing_jobs` (plus an optional supporting index). No column
  changes required for ordering/lineage (derived).
- P3-008 requires no schema change.
- Historical migrations must not be modified; a new additive migration with a
  clean `down()` is required.
- Compatibility: the `failed → queued` lifecycle extension and the adjusted
  writer/orchestrator terminal guards must not change any non-retry path. All
  Batch 2 tests (`TranscriptPersistenceTest`, `SegmentPersistenceTest`,
  `AtomicCompletionTest`, `ProcessTranscriptionJobTest`,
  `TranscriptionQueueOrchestrationTest`, `TranscriptionClaimConcurrencyTest`,
  `ProcessTranscriptionPayloadTest`) must remain green.
- P3-004/P3-005/P3-006 are DONE and must not be reopened.

## M. Files Created or Updated

- `tasks/P3-007-failure-retry-recovery-hardening.md` — refined to
  implementation-ready contract; now DONE.
- `tasks/P3-008-real-phase-integration-verification.md` — refined to
  implementation-ready verification contract; now DONE.
- `PHASE3-BATCH3-PLANNING.md` — this package (new).
- `DECISION_QUEUE.md` — DECISION-P3-BATCH3-001, B3-01 through B3-07, and the
  P3-007/P3-008/Batch 3 closure decisions recorded as DECIDED.
- `DECISIONS.md` — ADR-018 records the Batch 3 retry/recovery contract and
  authorization.
- `PHASE3-P3-008-INTEGRATION-EVIDENCE.md` — Builder integration evidence.
- `CURRENT_STATE.md`, `plan.md`, `RTFTT-MASTER-ROADMAP.md`, `AGENTS.md` —
  updated to reflect Batch 3 CLOSED, P3-007/P3-008 DONE, and Phase 3 NOT closed.

## N. Explicit Non-Actions

- Phase 3 was closed as a whole on 2026-09-19 (DECISION-PHASE3-CLOSURE-001);
  Phase 4 is NOT authorized and requires a separate planning/authorization
  decision.
- No reviewer artifact modified.
- No change to Batch 1/Batch 2 VERIFIED/DONE contracts.
- Option D (ADR-013) not lifted.
