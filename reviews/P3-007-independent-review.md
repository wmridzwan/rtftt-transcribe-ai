# P3-007 — Independent Review

Reviewer: Claude Code (independent reviewer role per AGENTS.md / ADR-015)
Date: 2026-09-19
Scope: `tasks/P3-007-failure-retry-recovery-hardening.md` only. Does not review,
execute, or authorize P3-008 or Phase 3 closure.

## 1. Executive Summary

P3-007's implementation satisfies its authorized contract and ADR-018. The
canonical `failed → queued` retry re-open transition is narrowly scoped to the
explicit `TranscriptionRetry` action; the previous failed attempt is preserved
immutably; retry is manual-only with no automatic scheduler or backoff; the
one-active-attempt invariant is enforced by a guarded compare-and-set inside a
single transaction plus a partial unique index as defense-in-depth; the
independent-process concurrency evidence is genuine (two real OS processes,
separate SQLite connections, deterministic two-phase filesystem barrier,
invoking the real production `TranscriptionRetry::retry()`); abandoned
`running` attempts are recovered without ever auto-dispatching inference, and
the stale-authority guard correctly prevents an obsolete worker from
overwriting newer authoritative state after both stale recovery and a
subsequent manual retry (directly tested). The Batch 1/2 VERIFIED writer,
queue, and atomicity contracts are preserved and regression-tested. The three
"frozen" test edits are justified, narrowly-scoped fixture adjustments
required by the new one-active-attempt invariant, not weakenings of the
invariants those tests actually assert. All independently reproduced quality
gates match the implementation report exactly. No unauthorized scope
expansion (P3-008, Horizon, translation, automatic retry) was found.

One MEDIUM, non-blocking finding: the stale-recovery threshold configuration
(`attempt_stale_seconds`) accepts an operator-supplied value lower than the
provider execution timeout without validation or normalization. This is a
hardening gap in a config knob, not a proven correctness defect — the
downstream CAS/stale-authority guards independently prevent any data
corruption or cross-attempt overwrite even under this misconfiguration, as
directly proven by the "obsolete worker cannot overwrite newer state after
recovery and retry" test. It does not gate VERIFIED (see §11 and Findings).

**Verdict: P3-007 = VERIFIED**, eligible to return to the HPO for closure
consideration. Not marked DONE. P3-008 execution not authorized or performed
by this review.

## 2. Authorization / Scope

- Confirmed `tasks/P3-007-failure-retry-recovery-hardening.md` is the only
  task this review acted against; `tasks/P3-008-real-phase-integration-verification.md`
  was read only for cross-reference, never executed.
- Grepped the full diff surface (`app/`, `config/`, `worker/*.py`) for
  `Horizon`/`translat` — the only hits are a pre-existing `config/app.php`
  locale comment, a pre-existing `config/transcription.php` comment noting
  Redis is used *without* Horizon, and `worker/transcription.py`'s unrelated
  `ctranslate2` import (faster-whisper's backend library, pre-existing).
  No Horizon dependency, no translation feature code.
- No automatic domain retry scheduler, no backoff scheduling code, exists
  anywhere in the diff. `ProcessTranscription::$tries` remains `1`
  (confirmed by direct read and by
  `TranscriptionRetryTest::'domain retry does not depend on laravel transport retry'`).
- No live Redis exercised anywhere in this diff's tests (grepped
  `tests/` and `worker/tests/` for `127.0.0.1:6379`/`Redis::`/`REDIS` — zero
  hits). P3-008's live-Redis/real-faster-whisper final gate was not executed
  or claimed.
- `CURRENT_STATE.md`, `DECISION_QUEUE.md` (DECISION-P3-BATCH3-001,
  B3-01–B3-07) and `DECISIONS.md` ADR-018 confirm P3-007/P3-008 READY,
  P3-007 implementation-complete and in REVIEW, P3-008 dependency-gated and
  not independently VERIFIED. No governance file was found marked
  VERIFIED/DONE by the implementer.

No unauthorized scope expansion found.

## 3. Retry Lifecycle

`app/Transcription/TranscriptionLifecycle.php` adds exactly one transition:
`Failed → [Queued]`. All other exits from `Failed`, `Completed`, and
`Cancelled` remain `[]`. This is the only lifecycle change and matches
ADR-018/B3-01 exactly.

`TranscriptionRetry::retry()` is the only code path that performs this
transition (confirmed by reading `TranscriptionOrchestrator::request()`,
which explicitly still rejects `Completed`/`Failed`/`Cancelled` and cannot be
used to bypass retry). A failed `ProcessingJob` attempt is never itself
transitioned back to `queued`; retry always creates a **new** `ProcessingJob`
row (`TranscriptionRetry::retry()` lines 82–104: CAS on `Transcription.status`,
then `ProcessingJob::query()->create(...)` inside the same transaction).

## 4. Retry Eligibility / Failure Taxonomy

`TranscriptionFailure::isRetryable()` (unchanged from the pre-existing,
already-VERIFIED Batch 1 taxonomy) marks exactly
`WORKER_UNAVAILABLE, WORKER_TIMEOUT, WORKER_SATURATED, RESOURCE_EXHAUSTED` as
retryable; all others (including `FFMPEG_FAILED`, `PERSISTENCE_FAILED`, and
the task's own proposed-but-not-decided reclassifications) remain
non-retryable, which is the correct conservative choice since no B3 decision
re-litigated the taxonomy beyond the FFMPEG_FAILED contradiction. Retry
eligibility (`TranscriptionRetry::isEligible()`) is derived solely from the
**latest** `ProcessingJob.failure_code` cast to `TranscriptionFailure`, never
from worker-supplied metadata. An attempt with a `null`/unrecognized
`failure_code` fails safe: `$latest->failure_code?->isRetryable() ?? false`
returns `false`, so an unknown/missing code is never treated as retryable.

`FailureTaxonomyReconciliationTest` directly proves a worker envelope with
`retryable: true` for `FFMPEG_FAILED` is still resolved to
`isRetryable() === false` through `WorkerErrorResponse`. `worker/main.py`'s
`FFMPEG_FAILED` advisory flag is aligned to `False` (confirmed by diff), and
`HttpTranscriptionProvider::transcribe()` never reads or forwards the worker
`retryable` field into the exception it throws — it always constructs the
`TranscriptionException` from the mapped `TranscriptionFailure`.

## 5. Manual Retry Action

`App\Actions\TranscriptionRetry::retry()` executes in this order: mediaFile
ownership-consistency check → idempotent active-attempt short-circuit →
`isEligible()` pre-check → guarded CAS (`failed → queued`) + new
`ProcessingJob` creation inside one transaction → dispatch. Exactly one
dispatch authority exists (`$this->orchestrator->dispatch($attempt)`, called
once, only on the CAS-winning path). A losing/duplicate call re-resolves the
winner's attempt via `activeAttempt()` rather than creating a second row or
double-dispatching (confirmed by code and by
`TranscriptionRetryTest::'repeated retry requests do not create a duplicate active attempt'`,
which asserts `Queue::assertPushed(ProcessTranscription::class, 1)`).
No user-supplied attempt or ownership identifier is ever accepted — the
transcription model is route-model-bound and ownership is derived from the
authoritative `mediaFile` relation.

## 6. Concurrency / Idempotency

The CAS is a single guarded `UPDATE ... WHERE status = 'failed'` and the new
`ProcessingJob` row is created **inside the same database transaction**,
immediately after a successful CAS (`won === 1`). A losing CAS (`won === 0`)
returns `null` without creating a row; the transaction has no side effects to
roll back in that branch beyond the no-op update. This satisfies §8 of the
review charter: the CAS is the primary correctness boundary, not
check-then-update, and the transaction cannot leave
`transcription = queued` with no attempt, or a new active attempt with
`transcription` still `failed` — attempt-creation is strictly downstream of
CAS success within one atomic unit.

`lockForUpdate()` is not used anywhere in `TranscriptionRetry`. The partial
unique index (`processing_jobs_active_attempt_unique`) is additive
defense-in-depth, independently confirmed to reject a second active row via
`TranscriptionRetryTest::'the database enforces at most one active attempt per transcription'`
(asserts a real `QueryException`).

## 7. Independent-Process Evidence

`TranscriptionRetryConcurrencyTest` genuinely satisfies the ADR-013/ADR-016/
P2-004A2/P3-006 evidentiary standard:

- two real OS processes via Symfony `Process`, each a separate
  `php artisan test:transcription-retry-race-worker` invocation;
- each process opens its own SQLite `PDO` connection to a shared,
  file-backed (not in-memory) database;
- a two-phase filesystem rendezvous: both processes first signal "ready",
  then both signal arrival "at_retry" and block until both sentinels exist
  before calling the **real, unmodified** `App\Actions\TranscriptionRetry::retry()`
  (`TranscriptionRetryRaceWorker.php`), which is a materially stronger
  barrier than a bare timing race;
- outcomes are captured independently per-process to separate result files
  and only read back after both processes have exited.

Critically, the test does **not** stop at "both processes returned the same
attempt ID" (which alone would not distinguish a genuine single-mutation
authority from two independent successful mutations that happen to converge).
It directly queries the shared SQLite file afterward and asserts:
`total attempts = 2` (one historical failed + exactly one new),
`active attempts = 1`, the original failed attempt's status is unchanged
(`'failed'`), `dispatched jobs = 1`, and `transcription.status = 'queued'`.
This is exactly the "CAS winner count = 1, new attempt insert count = 1,
queued dispatch count = 1, active attempt count = 1" evidence the review
charter requires, independently reproduced by direct database assertion, not
inferred from applicaton-level return values alone.

Independently reproduced: ran the test 5 consecutive times in this review
session; passed all 5 (12 assertions each, ~1.6–1.7s per run). Ran the full
suite once more for regression; matched the implementer's reported counts
exactly (see §16).

## 8. Active Attempt Constraint / Migration

`2026_09_19_000002_add_active_attempt_unique_index_to_processing_jobs_table.php`
creates a partial unique index (`WHERE status IN ('queued','running')`),
guarded to the SQLite driver only, with a symmetric guarded `DROP INDEX` in
`down()`. It is additive and does not touch any historical migration (grepped
`database/migrations/` — only two new `2026_09_19_*` files exist beyond the
pre-existing Batch 1/2 set). `2026_09_19_000001_add_failure_code_to_processing_jobs_table.php`
adds a nullable `failure_code` column, safe for existing rows, with a clean
`dropColumn` rollback. Both migrations ran cleanly for the full 366-test suite
(which recreates the schema via `RefreshDatabase`). The index is correctly
scoped as defense-in-depth: it is never the acting mechanism in the
concurrency test's control flow (the CAS resolves the race before either
process would attempt a conflicting insert in practice), but independently
proven to reject a violation directly (§6).

## 9. Stale Running Recovery

`StaleTranscriptionAttemptRecovery::recover()` selects `running` attempts
older than a derived threshold, then fails each with a guarded
`UPDATE ... WHERE status = 'running'` CAS. Only on a successful CAS does it
check `hasNewerAttempt()` (skip touching the transcription if a newer attempt
already exists — the obsolete-attempt case) and then update the
`Transcription` row, itself guarded by
`WHERE status IN ('queued','preparing','transcribing')` so a `completed`
transcription is never touched. Recovery never dispatches (`Queue::fake()` +
`assertNothingPushed()` directly proven) and never creates a new
`ProcessingJob`. This matches ADR-018/B3-05 exactly: `running → stale →
terminal recoverable failure`, eligible only for **explicit** manual retry
afterward (directly proven:
`app(TranscriptionRetry::class)->isEligible($transcription)` is `true` after
recovery, and a separate `retry()` call is required to create a new attempt).

Threshold: `staleThresholdSeconds()` returns the configured
`attempt_stale_seconds` if numeric, else `timeout_seconds (300) + 60 = 360`.
Boundary-tested precisely: an attempt at `threshold - 1` seconds old is not
recovered, one at exactly `threshold` seconds is (`'an attempt just inside the
threshold is not recovered but one at the threshold is'`).

## 10. Recovery vs Worker Race Safety

Both directions of the race are guarded and directly tested:

- **Worker completes, then recovery runs:** `failAttempt()`'s CAS requires
  `status = 'running'`; if the worker already transitioned the attempt to
  `completed`, the CAS matches zero rows and recovery silently skips it — no
  overwrite. (Not separately re-tested in this diff, but this is a direct,
  unavoidable consequence of the guarded CAS predicate, which the review
  independently verified by code inspection.)
- **Recovery runs, then the old worker resumes and tries to persist a
  result:** directly tested end-to-end by
  `'an obsolete worker cannot overwrite newer state after recovery and retry'`.
  This test recovers a stale attempt, retries (creating attempt N+1), then
  calls `TranscriptionResultWriter::persist()` with the **stale** attempt N
  object — reproducing literally the review charter's "old worker N resumes"
  scenario (§18). The assertion set is exactly what §18 asks for: the
  transcription remains `Queued` (not overwritten to completed), `full_text`
  is `null`, `segments()->count()` is `0`, the old attempt stays `Failed`, and
  the new attempt stays `Queued`. This is strong, direct evidence, not an
  inference from weaker signals.

## 11. Writer / Persistence Regression

`TranscriptionResultWriter::persist()` retains the Batch 2 atomic-completion
transaction shape unchanged in structure: it locks the transcription row,
early-returns on `Completed`/`Failed`/`Cancelled` **before any mutation**, and
only then mutates transcript fields, replaces segments, and marks the attempt
complete — all inside one transaction. The P3-007 addition (the
stale-authority guard) is inserted at the same point, before any mutation,
and re-fetches the **authoritative** `ProcessingJob` row fresh from the
database inside the transaction (not the possibly-stale in-memory `$attempt`
object passed in) — this is the detail that makes the guard actually
effective against a resumed obsolete worker holding a stale in-PHP-memory
object, since `$authoritativeAttempt` reflects whatever the recovery/retry
mutation already committed. No-speech completion path (empty `full_text`,
`speech_detected = false`) is untouched by this diff and is exercised
unchanged by the pre-existing P3-004/P3-005 tests, which pass unmodified in
the full suite (§16). No partial-write path was introduced.

## 12. Frozen Test Changes Review

The repository's only commit (`55c620a`, Batch 1 closure) predates all Batch
2/3 code and tests, so no historical git blob exists for these files to diff
against; this review instead read each file's current content and reasoned
about whether the assertions preserve the invariant they were originally
written to prove (cross-referenced against `reviews/PHASE3-BATCH2-independent-review.md`'s
description of what Batch 2 established).

- `tests/Feature/Transcription/ProcessTranscriptionJobTest.php` —
  `'a stale attempt is ignored while the newer attempt processes'`: the
  superseded/old attempt's fixture status changed from an active
  (queued/running) state to `Failed` (required by the new unique index), but
  the actual invariant under test — a stale delivery is a no-op
  (`callCount() === 0`) and does not disturb the newer authoritative
  transcription state — is unchanged and still directly asserted.
  **JUSTIFIED CONTRACT UPDATE.**
- `tests/Feature/Transcription/TranscriptionQueueOrchestrationTest.php` —
  `'a stale queued attempt cannot overwrite a newer attempt result'`: same
  pattern — the old attempt's fixture status is now `Failed` instead of
  concurrently active, but the test still delivers the stale job first, then
  the newer one, and still asserts the newer attempt completes successfully
  while the stale one stays `Failed` and the provider is invoked exactly
  once. **JUSTIFIED CONTRACT UPDATE.**
- `tests/Feature/ModelRelationshipTest.php` — `'transcription has many
  processing jobs'`: changed `ProcessingJob::factory()->count(3)` to
  `->completed()->count(3)` purely to satisfy the new partial unique index;
  the assertion (`assertCount(3, ...)`) tests relationship cardinality only
  and has no concurrency semantics to weaken. **JUSTIFIED CONTRACT UPDATE.**
- `tests/Unit/Transcription/DomainContractTest.php` — one assertion flips
  `canTransition(Failed, Queued)` from `false` to `true`, which **is** the
  ADR-018 contract change itself, correctly reflected; a second assertion in
  a different test (previously asserting `Failed → Queued` was invalid, used
  as a generic "still-invalid transition" example) was updated to
  `Failed → Transcribing`, which remains correctly invalid and preserves that
  test's actual purpose. **JUSTIFIED CONTRACT UPDATE.**

No assertion was weakened to make new code pass; every change is a fixture
adjustment or the intended contract change itself.

## 13. Ownership / HTTP / UI

`TranscriptionActionController::retry()` calls
`$this->authorize('update', $transcription)` before invoking
`TranscriptionRetry::retry()`, reusing the existing `TranscriptionPolicy`
(shared with rename/delete, pre-existing). Route is
`POST /transcriptions/{transcription}/retry`, inside the `auth`+`verified`
middleware group in `routes/web.php`; standard Laravel `web` group CSRF
protection applies (`@csrf` present in the Blade form). Directly tested:
cross-user retry returns `403 Forbidden` and creates zero new attempts
(`TranscriptionRetryHttpTest`); unauthenticated POST redirects to login. The
UI (`show.blade.php`) gates the retry button on both `$retryEligible` (server
`isEligible()`) and `auth()->user()?->can('update', ...)`, but this is
UI-only convenience — the controller re-authorizes and re-checks eligibility
server-side regardless of what the UI renders, so hidden UI is not the
authorization boundary. `error_message` shown is always the safe,
caller-controlled string set by `ProcessTranscription::fail()` or the worker's
`safe_message` field — no raw exception, SQL, path, or stderr is ever stored
into `error_message` anywhere in the diff (confirmed by reading every write
site).

One note (not a P3-007 regression): `TranscriptionPolicy::update()` also
returns `true` for `$user->isAdmin()`, so an admin can retry another user's
transcription. This is pre-existing behavior reused unchanged from
rename/delete, and actor-vs-owner/multi-tenancy semantics are an explicitly
deferred future gate per `CURRENT_STATE.md`. INFO only, not attributable to
this task.

## 14. Queue Contract Regression

`ProcessTranscription::$tries` remains `1` (confirmed by direct read and by
`TranscriptionRetryTest`'s explicit assertion). The claim boundary
(`claimAttempt()`, guarded `UPDATE ... WHERE status = 'queued'`),
`hasNewerAttempt()` staleness guard, and terminal-attempt guards are
unchanged in `ProcessTranscription::handle()`. Payload remains
`(transcriptionId, processingAttemptId)` — two integers, no media path, no
binary. Inference (`$provider->transcribe(...)`) executes outside any DB
transaction; only the final persistence step and the failure-recording step
are wrapped in short transactions. No Horizon dependency. Full P3-006-era
queue tests pass unmodified in the full suite.

## 15. Python Worker Review

The diff to `worker/main.py` is exactly the claimed one-line semantic change:
`FFMPEG_FAILED`'s advisory `retryable` flag flips from `True` to `False`,
with a comment explaining the ADR-018 alignment. No other worker logic
changed. Independently ran `worker/tests` via the project's own `.venv`
(`pytest`): **36 passed**, matching the implementation report exactly (33
pre-existing + 3 new `test_ffmpeg_failure_is_not_retryable`-family tests).
FFmpeg handling, faster-whisper invocation, and the error-envelope shape
(`error_code`, `retryable`, `safe_message`, `request_id`) are otherwise
untouched.

## 16. Test / Quality Evidence

All quality-gate claims were independently reproduced in this review session
(not merely re-read from the implementer's report):

- **Full PHP suite:** `php artisan test --compact` → 366 total, 365 passed, 1
  skipped, 1152 assertions, 2 warnings. **Matches exactly.**
- **Concurrency test, run 5x independently:** `TranscriptionRetryConcurrencyTest`
  passed all 5 runs, 12 assertions each, ~1.6–1.7s per run. Stable.
- **Pint:** `vendor/bin/pint --test --format=agent` → passed (clean).
- **PHPStan:** `vendor/bin/phpstan analyse --memory-limit=1G` → 0 errors.
- **Python worker suite:** `./.venv/Scripts/pytest.exe -q` → 36 passed.

A CAS-guard mutation test (temporarily removing the
`->where('status', TranscriptionStatus::Failed->value)` predicate from
`TranscriptionRetry::retry()` to independently confirm the concurrency test
would fail without it) was attempted but blocked by this session's tool-use
safety classifier before execution; the edit was reverted immediately and
`git diff` confirmed a clean revert (no residual change). This specific
mutation-proof claim from the implementation report is therefore **not
independently reproduced** in this review, though the code-level reasoning in
§6 (CAS-then-insert inside one transaction, no unconditional update path
exists elsewhere) supports the claim on inspection.

No live Redis server was exercised or required by any P3-007 test (correctly
out of this task's scope; live Redis remains P3-008's B3-06 requirement).

## 17. Governance / Git

- `git status`/`git log` confirm the repository's only commit predates all
  Batch 2/3 code; all P3-007 files are new/uncommitted working-tree changes,
  consistent with this repository's established pattern of accumulating
  batch work in the working tree until HPO closure (also true of the
  already-DONE Batch 2 files at review time).
- No task file, review file, or `CURRENT_STATE.md`/`DECISION_QUEUE.md` entry
  was found marked VERIFIED or DONE by the implementer; `tasks/P3-007-...md`
  correctly states "Review Status: PENDING" and "Implementation owner must
  not mark their own work VERIFIED."
- No secrets, absolute local paths, or debug/temp artifacts were found in the
  reviewed diff.
- The testing-only race worker (`TranscriptionRetryRaceWorker`) is
  environment-guarded (`app()->environment('testing') === false` → refuses to
  run) and `protected $hidden = true`, consistent with the accepted
  P2-004A2/P3-006 harness precedent.
- No prior reviewer artifact (`reviews/PHASE3-BATCH1-*`, `reviews/PHASE3-BATCH2-*`)
  was modified by this review or found modified by the implementation.

## 18. Findings Summary

| ID | Severity | Finding | Required Action |
|----|----------|---------|-----------------|
| MEDIUM-1 | MEDIUM | `StaleTranscriptionAttemptRecovery::staleThresholdSeconds()` accepts any numeric `transcription.attempt_stale_seconds` config value, including one lower than `transcription.timeout_seconds` (the provider execution timeout), without validation or normalization. No test proves rejection/normalization of an unsafe low threshold. A misconfigured deployment could mark a still-legitimately-running attempt as abandoned. Downstream CAS/stale-authority guards (§10, §11) independently prevent any resulting data corruption or overwrite — the exposure is a spurious premature failure/retry-eligibility, not corrupted state — so this does not block VERIFIED (see §19 justification). | Non-blocking follow-up: validate/clamp `attempt_stale_seconds` to be `>= timeout_seconds` (or log a warning) at config-read time, or document the operational constraint explicitly in `config/transcription.php`. |
| INFO-1 | INFO | The mutation-proof claim for the concurrency test's CAS guard ("mutation of the CAS guard makes it fail") was not independently reproduced in this review session — the attempted mutation test was blocked by the session's own tool-safety classifier before execution, and immediately reverted. The static code-path analysis in §6 supports the claim without contradiction. | No P3-007 correction required. A future reviewer with fewer session-level restrictions may wish to independently reproduce this specific claim. |
| INFO-2 | INFO | `TranscriptionResultWriter`/`TranscriptionOrchestrator` continue to call `lockForUpdate()` under SQLite, which ADR-013/ADR-016 established is not a valid SQLite row-lock guarantee. This is pre-existing Batch 2 VERIFIED code, unchanged in shape by P3-007 (only the stale-authority guard was added inside the same transaction); actual correctness in this flow rests on SQLite's whole-transaction write serialization, not the row-lock syntax. | No P3-007 correction required; residual risk already accepted at Batch 2 closure. |
| INFO-3 | INFO | `TranscriptionPolicy::update()` (reused for retry authorization) permits an admin to retry another user's transcription. Pre-existing behavior, consistent with rename/delete; actor-vs-owner/multi-tenancy semantics remain an explicitly deferred future gate. | No P3-007 correction required. |

## 19. Final Verdict

No BLOCKER or HIGH findings. The single MEDIUM finding is a defense-in-depth
configuration-validation gap, not a proven data-integrity, security,
ownership, lifecycle, or architecture violation — the scenario it describes
is independently shown (§10, §18) to be safely contained by the CAS and
stale-authority guards even if it occurred. Per the review charter's rule
that "a MEDIUM finding accompanying VERIFIED requires explicit canonical
justification," that justification is: the finding concerns robustness of an
internal operator-only config knob whose safe default is correctly derived,
not a defect in the retry/recovery/concurrency mechanism itself, and no test
or code path demonstrates actual data corruption or an authority violation
resulting from it.

```text
P3-007 = VERIFIED
```

P3-007 is eligible to return to the Human Product Owner for closure
consideration. This review does not mark P3-007 DONE, does not execute or
authorize P3-008's final verification, and does not close Phase 3.

---

## 20. Verdict Reconciliation (2026-09-19)

A separate reconciliation pass was requested because §19's original
justification for coexisting with MEDIUM-1 leaned on reviewer discretion
("the finding concerns robustness of an internal operator-only config knob")
rather than an explicit canonical governance/contract basis, and the original
Findings Summary table (§18) did not carry a `Blocking?` column making the
closure-eligibility of each finding unambiguous. This section resolves that
without disturbing any of the independently-established evidence in §§1–17,
which is unchanged and still authoritative.

### MEDIUM-1

**Original severity:** MEDIUM

**Reconciled severity:** LOW (renumbered LOW-1 for the reconciled table; the
original MEDIUM-1 label and text in §18 is preserved unchanged as the
historical record of the initial assessment and must not be read as deleted
or superseded silently)

**Original concern (quoted from §18, unchanged):** "`StaleTranscriptionAttemptRecovery::staleThresholdSeconds()`
accepts any numeric `transcription.attempt_stale_seconds` config value,
including one lower than `transcription.timeout_seconds` (the provider
execution timeout), without validation or normalization. No test proves
rejection/normalization of an unsafe low threshold. A misconfigured
deployment could mark a still-legitimately-running attempt as abandoned.
Downstream CAS/stale-authority guards (§10, §11) independently prevent any
resulting data corruption or overwrite — the exposure is a spurious premature
failure/retry-eligibility, not corrupted state."

**Canonical/technical analysis:**

Re-inspected the exact relationship this session, independently, with no
code/test/config changes made:

- `config/transcription.php:40` — `'timeout_seconds' => 300` (the canonical
  provider execution timeout contract; also consumed directly by
  `HttpTranscriptionProvider::transcribe()`'s HTTP client timeout).
- `config/transcription.php:54` — `'attempt_stale_seconds' => env('RTFTT_TRANSCRIPTION_ATTEMPT_STALE_SECONDS')`,
  unset by default.
- `StaleTranscriptionAttemptRecovery::staleThresholdSeconds()` — if
  `attempt_stale_seconds` is numeric, it is used verbatim (`max(1, (int) $configured)`,
  the only floor applied); otherwise it derives `timeout_seconds + 60`.
  Confirmed by direct re-read: no other call site references either key
  (`grep -rn 'attempt_stale_seconds|timeout_seconds' app/ config/ bootstrap/ tests/`
  returns only these production sites plus the existing config-override test
  at `StaleTranscriptionAttemptRecoveryTest.php:96`, which sets
  `attempt_stale_seconds => 42` — below the 300s provider timeout — purely to
  exercise the override mechanism, with no assertion either way about
  whether that value *should* be permitted).
- **Confirmed precisely:** an operator can set
  `RTFTT_TRANSCRIPTION_ATTEMPT_STALE_SECONDS` to any value down to `1`, with
  zero validation, rejection, or normalization against `timeout_seconds`. So
  yes, stale recovery *can* be configured to fire before a worker has
  exhausted its legitimate 300s provider execution window.

Checked this against the actual canonical sources, not the reviewer's own
investigative framing:

- **ADR-018 §B3-05** requires only that "the stale threshold is derived
  conservatively from the actual current provider execution timeout
  contract/configuration (~300 seconds), not an arbitrary hard-coded value"
  — this governs the *default derivation method*, which is correctly
  implemented (`timeout_seconds + 60`, not a bare literal). ADR-018 does not
  state a requirement that an explicit operator override must be bounded,
  rejected, or normalized relative to `timeout_seconds`.
- **`tasks/P3-007-...md`, "Recovery After Worker Crash"** uses identical
  language ("must be derived conservatively... not an arbitrary hard-coded
  value") and separately requires "a stale-authority guard must prevent an
  old worker that later resumes from completing or overwriting the newer
  authoritative state; the exact guard must be defined and tested" — this is
  a distinct, data-correctness requirement, independently satisfied and
  independently tested regardless of what threshold value is configured
  (§10, §11; the `TranscriptionResultWriter` guard re-reads the authoritative
  attempt row fresh inside the transaction and blocks any obsolete-worker
  write unconditionally on attempt status/newer-attempt, not conditionally
  on whether recovery fired "correctly timed" or "prematurely").
- Neither ADR-018 nor the task acceptance criteria (`tasks/P3-007-...md`
  §Acceptance Criteria, items 1–19) contains a criterion requiring
  operator-override input validation on the stale threshold. The sentence in
  the *reviewer's own charter/instructions* ("a running attempt must not be
  recoverable while its worker can still legitimately be inside the allowed
  provider execution window") is investigative guidance directing what to
  check, not a quoted requirement from ADR-018 or the task contract itself,
  and must not be treated as an independent source of authorized scope.

Applying the distinction requested between data/state correctness and
operational robustness: the only way this gap can manifest is if an operator
explicitly sets a non-default, sub-300s override. Even then:

- **Data/state correctness:** fully preserved. The stale-authority guard in
  `TranscriptionResultWriter::persist()` and the CAS in
  `StaleTranscriptionAttemptRecovery::failAttempt()` are unconditional — they
  do not become weaker or bypassed by a low threshold. `'an obsolete worker
  cannot overwrite newer state after recovery and retry'` proves this for
  *any* trigger of recovery, not specifically a "correctly timed" one; the
  test's mechanism of proof does not depend on the threshold value at all.
- **Operational consequence of misconfiguration:** the affected attempt is
  failed with `WORKER_TIMEOUT` prematurely, the transcription becomes
  manually-retry-eligible, and a legitimate in-flight inference's result is
  discarded when the old worker later tries to persist it (safely blocked,
  not corrupted) — i.e., one wasted inference cycle and a spurious `failed`
  status requiring a manual retry click. This is avoidable operational work,
  not a correctness, security, ownership, or lifecycle violation, and it is
  fully self-healing (retry-eligible immediately, per the isEligible()
  contract).

This matches the Outcome C trigger precisely: a robustness observation with
no contract violation, because the CAS and stale-authority protections
already safely contain the worst case regardless of the threshold value.
Retaining MEDIUM here would overstate the actual consequence against the
severity model's own definitions (MEDIUM = "significant contract/test gap...
normally requiring correction before closure"; there is no contract gap, only
an input-hardening opportunity — that is the definition of LOW = "minor
non-blocking implementation/test/documentation issue").

**Required action:** Unchanged recommendation, now explicitly non-blocking
and optional: validate/clamp `attempt_stale_seconds` to be
`>= timeout_seconds` (or log a warning) at config-read time, or document the
operational constraint explicitly in `config/transcription.php`. This is a
quality-hardening suggestion for a future task, not a P3-007 correction
required before closure.

**Effect on verdict:** None. The verdict was already VERIFIED under the
original (MEDIUM) classification and remains VERIFIED under the reconciled
(LOW) classification. The reconciliation removes the need for the "explicit
canonical justification" clause that MEDIUM-coexisting-with-VERIFIED
requires, because LOW findings do not trigger that clause under the review
severity model (§33 of the original review charter: MEDIUM requires explicit
canonical justification to coexist with VERIFIED; LOW does not).

### INFO-2 and INFO-3 (retained, unchanged)

Both are re-affirmed as pre-existing, non-regressed conditions and remain
INFO, not elevated:

- **INFO-2** (`lockForUpdate()` under SQLite in `TranscriptionResultWriter`/
  `TranscriptionOrchestrator`): re-confirmed this is unchanged in shape by
  P3-007 — the only P3-007 addition at that call site is the stale-authority
  guard, which is itself a guarded, unconditional re-fetch-and-check, not a
  new dependency on `lockForUpdate()`'s locking semantics. This was already
  litigated and accepted at Batch 2 closure (ADR-013/ADR-016); P3-007
  introduces no new reliance on it. No regression evidence found.
- **INFO-3** (admin cross-user retry via `TranscriptionPolicy::update()`):
  re-confirmed this ability predates P3-007 and is identically reused by the
  pre-existing rename/delete actions; P3-007 did not add, change, or expand
  the policy. Actor-vs-owner/multi-tenancy semantics remain an explicitly
  deferred future gate per `CURRENT_STATE.md`, not a P3-007 scope item. No
  regression evidence found.

Neither is promoted to a blocking finding.

### Reconciled Findings Table

This table supersedes §18 for closure-eligibility purposes only. §18's
original text is preserved above unchanged as the historical record of the
initial assessment.

| ID | Severity | Finding | Required Action | Blocking? |
|----|----------|---------|-----------------|-----------|
| LOW-1 (reconciled from MEDIUM-1) | LOW | `StaleTranscriptionAttemptRecovery::staleThresholdSeconds()` permits an explicit `attempt_stale_seconds` config override below the 300s provider `timeout_seconds` with no validation/normalization, so stale recovery could fire before a worker's legitimate execution window ends. Confirmed no other call site validates this relationship. Consequence is fully contained: the CAS/stale-authority guards (§10, §11) make it impossible for the resulting recovery to corrupt data or let an obsolete worker overwrite newer state, regardless of the configured value — the only effect is one avoidable wasted inference cycle plus a spurious `failed`→manual-retry cycle. Neither ADR-018 nor the P3-007 acceptance criteria require override validation; the default derivation (which they do govern) is correctly conservative. | Optional hardening for a future task: validate/clamp `attempt_stale_seconds >= timeout_seconds` or document the constraint in `config/transcription.php`. Not required before P3-007 closure. | No |
| INFO-1 | INFO | Mutation-proof of the CAS guard not independently re-executed this session (tool-safety classifier blocked it before execution; edit immediately reverted, confirmed clean via `git diff`). Static code-path analysis (§6) supports the claim without contradiction. | None required. | No |
| INFO-2 | INFO | Pre-existing `lockForUpdate()` usage under SQLite in `TranscriptionResultWriter`/`TranscriptionOrchestrator` (Batch 2 VERIFIED, already litigated under ADR-013/ADR-016). Unchanged in shape by P3-007; the new stale-authority guard added alongside it does not depend on `lockForUpdate()`'s locking semantics. | None required. | No |
| INFO-3 | INFO | `TranscriptionPolicy::update()` (reused unchanged for retry authorization) permits an admin to retry another user's transcription. Pre-existing behavior, identical to rename/delete; actor-vs-owner/multi-tenancy semantics are an explicitly deferred future gate, not P3-007 scope. | None required. | No |

No finding in this table requires correction before P3-007 closure.

### Reconciled Final Verdict

```text
P3-007 = VERIFIED
```

Canonical governance/contract basis for VERIFIED coexisting with the
remaining LOW-1 (and INFO-1/2/3): none of the retained findings identify a
gap against an actual textual requirement in ADR-018 or
`tasks/P3-007-failure-retry-recovery-hardening.md`'s Acceptance Criteria —
every explicit requirement in those two canonical sources (manual-only retry,
same-transcription identity, bounded/idempotent concurrency, the stale
threshold's *default* conservative derivation, the stale-authority guard
against an obsolete worker overwriting newer state, completed-transcription
protection, ownership boundary, and the taxonomy-authority contract) is
independently verified as implemented and tested in §§2–17. LOW-1 concerns
an unvalidated operator-only configuration override that is outside what
either canonical source requires to be validated, and its worst-case effect
is independently proven (§10, §11, and the reconciled analysis above) to be
contained entirely within already-tested, unconditional correctness guards —
it is operational-robustness debt, not an unmet contract term. This
satisfies the review severity model's own rule that only a HIGH finding, or
an unjustified MEDIUM, blocks VERIFIED; a properly-classified LOW does not
require the same justification burden and does not gate closure.

This reconciliation changes no code, test, migration, worker file, or
governance/task-state file. It updates only this reviewer artifact.
P3-007 is eligible to return to the Human Product Owner for closure
consideration. This reconciliation does not mark P3-007 DONE, does not
execute or authorize P3-008's final verification, and does not close Phase 3.
