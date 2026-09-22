# Phase 5 Corrective Batch — Independent Review (P5-003 c2, P5-002B, P5-004 c2, P5-005 c2)

Reviewer: Claude Code (independent reviewer role, `.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-21
Branch: `phase5-7/parallel-2026-09-21`, HEAD `fb09fe0`
Scope: the four tasks above, then re-confirmation of the P5-002A MEDIUM findings.
No application code, test, migration, task status, or governance file was
modified by this review. This artifact is the only file written. No task is
marked DONE. P5-006 and P5-008 were not started.

Independence: reviewed as a fresh context from source, tests, migrations, worker
code, and my own executed probes. The implementer's cycle-2 pre-reviews were
treated as claims, not evidence. `git diff HEAD` over every reviewed path is
empty, so the working tree equals HEAD for this scope.

## 1. Verdicts

| Task | Verdict | Blocking | Non-blocking |
|---|---|---|---|
| P5-003 cycle 2 — provider boundary | **VERIFIED** | none | L-1..L-4, INFO |
| P5-002B — writer attempt-state | **VERIFIED** | none | L-1, L-2 |
| P5-004 cycle 2 — queue / lifecycle | **VERIFIED** | none | M-1, M-2, M-3, L-1..L-4, INFO |
| P5-005 cycle 2 — failure / retry / recovery | **VERIFIED** | none | M-1 (shared with P5-004 M-1), L-1 |

Severity legend: BLOCKER / HIGH / MEDIUM / LOW / INFO. BLOCKER or HIGH prevents
VERIFIED. **No BLOCKER or HIGH finding remains.** The two prior HIGH findings
(X-1 fencing, X-2 divergent re-run paths) and P5-003 H-1 are closed in code, and
I reproduced the closure against the real classes (§4).

Verdicts follow the repository rubric literally. Several MEDIUM findings below
are real, reproduced, and should be fixed before the phase closes; per the
orchestration policy they become READY follow-up tasks and do not start
automatically. Where I chose MEDIUM over HIGH I say why, so the HPO can re-rate.

## 2. Closure of the prior P5-002A MEDIUM findings

**Both are fully closed by P5-002B.**

| Prior finding | Closed? | Evidence |
|---|---|---|
| P5-002A M-1: `translationId` not validated when a completed same-target row exists | **Yes** | `TranslationResultWriter::persist()` now requires `int $translationId` and `string $attemptToken` and resolves strictly by `whereKey + transcription_id` (`TranslationResultWriter.php:47-58`). Probe Q5 with a completed `ms` row present: nonexistent id, another transcription's `ms` row, and a wrong-target row are all rejected with `TranslationException`; neither the foreign nor the wrong-target row is mutated. |
| P5-002A M-2: provider-echoed alignment persisted | **Yes** | `replaceSegments()` copies `segment_index`, `start_seconds`, `end_seconds`, and `language` from the source `TranscriptionSegment` rows. Probe Q6: provider echoing `zh` and 0.0004 s drift persists source values (`en`, source timestamps read raw from the DB). `TranslationResponseValidator` independently copies alignment from the invocation. |
| P5-002A L-1: legal source state was "not failed" | Yes | Only `translating` may complete; Q6 rejects `pending`/`queued`/`failed` and leaves them unchanged; `TranslationLifecycle::assertValidTransition` is asserted. |
| P5-002A L-2: rollback test never reached segment persistence | Yes | The new test throws on the 2nd `TranslationSegment` create; it asserts the row stays `translating` and 0 segments exist. |
| P5-002A L-3: alignment check outside the transaction | Retained (INFO) | Read-only, before any write; source segments are immutable (D5-05). |

Note: the pre-review says the M-1 shape is "structurally impossible" because of the
partial unique index. That is inaccurate (a completed `ms` row plus a queued `en`
row is legal, which is what Q5 uses), but harmless: the shape is now rejected.

## 3. Findings

### P5-003 cycle 2 — VERIFIED

Closure of the requested items:

| Item | Result | Evidence |
|---|---|---|
| Strict response validation against the invocation | Closed | `fromArray()` takes the `TranslationInvocation`; checks target, count, unique index set, timestamps (±0.0005 s), source-language echo. Q9 confirms the taxonomy per case: empty / partial / foreign-index-same-count / extra → `MISSING_SEGMENTS`; duplicate index / drift → `MALFORMED_OUTPUT`. |
| Malformed / foreign / partial rejection | Closed | Q9; provider tests for empty, partial, foreign index, array text. |
| Typed error handling | Closed | Q10 (single closure fake): 401/403/404/405 → `CONFIGURATION_ERROR`; 422 → `MALFORMED_OUTPUT`; 429/5xx → `PROVIDER_UNAVAILABLE`; 504 → `PROVIDER_TIMEOUT`; `ConnectionException` → `PROVIDER_TIMEOUT`; other transport errors → `PROVIDER_UNAVAILABLE`; 503 + `CONFIGURATION_ERROR` envelope → terminal `CONFIGURATION_ERROR`. No raw exception escaped in any case. |
| `zsm_Latn` mapping | Closed | `worker/translation.py:24` maps `ms → zsm_Latn`; a mapping test asserts all four codes. Context7 (`/huggingface/transformers`) confirms the `forced_bos_token_id=tokenizer.convert_tokens_to_ids(<FLORES code>)` pattern the worker uses. **Not executed** — see INFO-1. |
| Worker dependency declaration | Closed (see INFO-2) | `worker/requirements.txt` now declares `transformers>=4.40.0`, `torch>=2.2.0`, `sentencepiece>=0.2.0`, which is the correct set for `AutoTokenizer` + `AutoModelForSeq2SeqLM` + `return_tensors="pt"` (no `accelerate`/`device_map` is used, so it is not needed). |

- **L-1 — LOW — "Strict" typing is looser than the pre-review claims.** Q8:
  `segment_index: "0"` (string) and `0.0` (float) are accepted; timestamps as
  numeric strings are accepted. Harmless: persisted alignment comes from the
  invocation and out-of-set indices are rejected. Bools and non-numeric strings
  are rejected. The comment/pre-review wording ("strict") overstates it.
- **L-2 — LOW — Top-level `text` is not reconciled with the segments.** Q8: a
  response whose `text` is unrelated to its segments, or whose `text` is missing
  or null, is accepted; `full_text` is persisted as `''` or as the unrelated
  string. Segment data is authoritative and correct; only `translations.full_text`
  can disagree with the segments.
- **L-3 — LOW — Persisted `provider`/`model` are Laravel config, not the worker's
  report** (P5-003 prior L-3, retained by design and documented). The worker's
  `provider`/`model` response fields are ignored; `RTFTT_TRANSLATION_MODEL` has
  different defaults in Laravel (`self-hosted-default`) and the worker (the NLLB
  checkpoint), so identity is correct only if both processes share the variable.
- **L-4 — LOW — Test assertions are weaker than the behavior.** Most validator
  and provider tests assert only `toThrow(TranslationException::class)`, not the
  `TranslationFailure` value, so a regression from `MissingSegments` to
  `MalformedOutput` (or a wrong status→taxonomy mapping, other than the 401 and
  timeout cases) would not fail the suite. The behavior is correct today (Q9,
  Q10). `_translate_text` (per-segment `src_lang`, `forced_bos_token_id`) has no
  unit test with a fake tokenizer.
- **INFO-1** — The real translation path is unexecuted: `transformers`, `torch`,
  and `sentencepiece` are not installed in `worker/.venv`, and worker tests patch
  `translate_segments`. `tokenizer.src_lang = ...` post-init assignment relies on
  the upstream tokenizer setter; the docs show `src_lang` at construction. The
  mandatory real-model gate is P5-008 (D5-09), and nothing here claims it.
- **INFO-2** — Dependency governance: the three runtime dependencies were added by
  the implementer without a recorded HPO approval (`DECISIONS.md` freezes D5-04
  "self-hosted default" but does not name them; `BLOCKERS.md` records them as a
  B-002 resolution). They are unbounded (`>=`); `transformers` 5.x is published.
  The HPO should acknowledge the addition and consider an upper bound before the
  P5-008 gate.
- **INFO-3** — Worker behavior for P5-008 to exercise: a segment language of
  `und` silently falls back to `eng_Latn` (`translation.py:82`); tokenizer input
  is silently truncated at 512 tokens; `/translate` is an `async def` running
  blocking inference (same as `/transcribe`); 422 (FastAPI request validation) is
  classed `MALFORMED_OUTPUT` although it describes the request, not the response
  (both terminal).

### P5-002B — VERIFIED

All seven acceptance criteria met (§2). Probes Q5, Q6, Q7 pass against the real
writer; the committed tests pass.

- **L-1 — LOW — Source-authoritative timestamps are not test-guarded.** Mutation
  M7 (writer persisting `$segment->startSeconds` instead of `$source->start_seconds`)
  leaves all 109 translation tests green, because the test fixtures give the provider
  the exact source timestamps. The language half is guarded (M9 fails as it
  should). Add a test where the provider values are within tolerance but differ.
- **L-2 — LOW — Committed tests cover wrong-target only.** Nonexistent and
  foreign-transcription ids are correct in code (Q5) but have no committed test.
  The rollback test also leaves a `TranslationSegment::creating` listener
  registered for the rest of the process (disarmed by a flag).
- **INFO** — `TranslationLifecycle::assertValidTransition(translating→completed)`
  is redundant after the explicit `translating` check; harmless.

### P5-004 cycle 2 — VERIFIED

| Item | Result |
|---|---|
| Attempt-token identity/fencing | **Complete in code across every state-mutating path** (table below). |
| Actual `TranslationLifecycle` enforcement | **Partially met** — see L-1. Used at the job's claim gate and at the writer; the CAS predicates are hand-coded. |
| Request/retry identity convergence | Met, with two edge gaps — M-2 (concurrent first request), LOW L-2 (stale view). |
| Explicit queue/connection routing | Closed. Q19: pushed job has `queue = 'translation'`, `connection = 'redis'` when configured, `connection = null` when not; the config falls back to the Phase 3 connection env. |

Attempt-fencing audit of every mutation of `translations`:

| # | Mutation | Site | Fence | Reproduced by |
|---|---|---|---|---|
| 1 | claim `queued→translating` | `ProcessTranslation::claim` `:168-172` | token + status CAS | committed test (M4 caught), two-process race |
| 2 | fail `→failed` | `ProcessTranslation::fail` `:191-195` | token + non-terminal-status CAS | Q1–Q3; **no committed test** (M1 survived) |
| 3 | complete `translating→completed` | `TranslationResultWriter::persist` `:60,77` | token check under `lockForUpdate` in one transaction | Q1, Q3, Q4; committed test (M3 caught) |
| 4 | stale recovery `translating→failed` | `StaleTranslationAttemptRecovery` `:49,62` | token + status CAS | Q1–Q4; **no committed test** (M2 survived) |
| 5 | retry `failed→queued` (mints token) | `TranslationRetry::retry` `:71` | status CAS; token is minted, not checked | committed + two-process test; see L-2 |
| 6 | request: create / `pending→queued` (mints token) | `TranslationOrchestrator::request` `:60,82` | unique index; token minted | see M-2 |
| 7 | dispatch | `TranslationDispatcher` | token carried in payload | Q19, payload test |

In-flight late-writer scenarios that the prior review reproduced as P9/P10 all
pass against the real code (Q1: recovery then late success; Q2: recovery then late
`TranslationException`; Q3: recovery + manual retry then late success / typed
failure / unexpected exception; Q4: the new attempt completes first, the old one
finishes last). In each case the newer state, `failure_code`, and segments are
untouched. Write-vs-write serialization at #3 relies on the transaction, not on a
token-predicated `UPDATE`: `lockForUpdate` is a no-op on SQLite, but my SQLite
probe (two connections, rollback-journal, DEFERRED) shows a second connection
cannot commit between the writer's `SELECT` and `UPDATE` (it gets
`database is locked`). It also holds on engines where `lockForUpdate` is real.

- **M-1 — MEDIUM (shared with P5-005) — The regression tests do not guard two of
  the four fences.** Mutation testing in a scratch copy of HEAD (repository
  untouched): removing the token predicate from `ProcessTranslation::fail()`
  (M1) or from the recovery CAS (M2) leaves **all 109 tests green**. The two
  committed X-1 tests exercise a job that *arrives* after recovery/retry (skipped as
  terminal, or stopped by the claim fence); neither reproduces the original defect
  path, an in-flight job whose late failure or late completion arrives after
  recovery/retry. The prior review asked for exactly that sequence
  ("recovery→retry→late-old-job"). The code is correct (Q1–Q4); the guard is
  missing, so a future edit could reintroduce X-1 unnoticed. I rate this MEDIUM
  because the behavior is verified and the fix is test-only. If the HPO reads AC4
  as requiring test-proven fences on every path, re-rate it HIGH for P5-005.
- **M-2 — MEDIUM — Concurrent first `request()` for one target leaks a raw
  `UniqueConstraintViolationException`.** Q12 (a competing row inserted between the
  existence check and the insert): `request()` throws the raw exception. On SQLite,
  `lockForUpdate` is a no-op, so a UI double-submit is a realistic trigger; the loser
  may instead see `database is locked`. The unique index preserves the invariant
  (no data corruption), but this is the same seam X-2 was about, and the
  convergence guarantee claimed in the pre-review does not extend to it. `retry()`
  converges correctly. Fix is local: catch the exception and re-select the
  active row. P5-006 will expose this path directly.
- **M-3 — MEDIUM — A lost dispatch strands a `queued` row with no recovery path,
  and `request()`/`retry()` will not re-dispatch it.** Q13 (an unroutable queue
  connection makes dispatch throw): the row stays `queued`; a second `request()`
  returns it and pushes nothing. Recovery scans `translating` only. This is weaker
  than the Phase 3 precedent: `TranscriptionOrchestrator::request()` calls
  `dispatch()` even when it reuses an existing attempt, so a repeat request
  self-heals there. The prior review rated this LOW ("parity with Phase 3"); it is
  not exact parity, so I raised it. Non-blocking because it needs a broker failure at
  the moment of dispatch and the caller does see an exception at that time.
- **L-1 — LOW — `TranslationLifecycle` is enforced at two chokepoints, not
  everywhere.** `handle()` gates the claim on `canTransition(status → translating)`
  (`:73`) and the writer asserts `translating → completed`. The CAS predicates are
  hand-written source sets: `claim()` includes `pending` (dead code — Q21: a
  `pending` row is skipped by the pre-check) and `fail()` includes `pending`
  (`pending → failed` is not in the lifecycle table; unreachable today because
  `fail()` runs only after a claim). The earlier request to route `fail()` through
  the lifecycle was not done. No reachable illegal transition exists.
- **L-2 — LOW — `retry()` eligibility is evaluated on the caller's in-memory row and
  is not part of the CAS.** Q11: a stale view of a retryable failure requeues a row
  whose current `failure_code` is non-retryable (`MALFORMED_OUTPUT`). The window is
  load-to-CAS only if the caller reloads the row (P5-006 must). Fix: include the
  retryable codes in the CAS predicate.
- **L-3 (residual L-1, assessed separately) — LOW — No `$timeout` and no
  `failed()` handler.** Unchanged, and matches `ProcessTranscription`, which also
  has neither. Consequences: with `tries = 1`, a job that outlives `retry_after`
  (90 s default) is failed by the queue without running twice, and a job killed by
  `queue:work --timeout` (default 60 s where `pcntl` exists) leaves the row
  `translating` until stale recovery (360 s) marks it retryable `PROVIDER_TIMEOUT`.
  Provider timeout is 300 s, so real inference will hit the 60 s default unless the
  worker is started with a larger `--timeout`. Q18: any exception between the claim
  and the provider `try` (invocation build, segment mapping) also strands the row
  the same way. May wait until P5-008, but the runbook and a per-job `$timeout`
  must be settled before the real-provider gate.
- **L-4 (residual L-2, assessed separately) — LOW — Zero-segment dispatch
  unchanged.** Q14: a completed transcript with no segments makes one HTTP call
  and completes as an empty translation (0 segments, empty text). This is benign
  and consistent with the writer's alignment contract; it wastes a round-trip and
  yields an empty "completed" translation. May wait until P5-008 (or P5-006 may
  hide the action).
- **INFO** — Recovery ignores `translating` rows with a null token (Q17); no such
  row can be produced by current code (claim requires a token), so this matters only
  for data migrated in from before `attempt_token`. `test:*-race-worker` commands
  live under `app/Console/Commands`, env-guarded (Phase 3 precedent). The queue
  name `translation` is not consumed by the `composer dev` script
  (`queue:listen` without `--queue`); the P5-008 runbook must name
  `queue:work --queue=translation`. The orchestrator and retry perform no
  authorization by design; P5-006 must gate them. `translations_active_target_unique`
  is SQLite-only (D7-01 debt).

### P5-005 cycle 2 — VERIFIED

| Item | Result |
|---|---|
| Stale-writer protection | Closed in code (Q1–Q4). The writer/claim fences are test-guarded; `fail()` is not (P5-004 M-1). |
| Stale-recovery fencing | Closed in code: selects only token-bearing `translating` rows and CASes on `status` + `attempt_token`. Not test-guarded (M2). |
| Retry convergence, no raw unique-index error | Closed for `retry()`: Feature test with the real partial index (failed A + active B → returns B) and the two-process race (exactly one dispatch, both converge on the same row). |
| X-1 and X-2 regression tests | X-2: adequate (request-after-retryable, request-after-non-retryable, active convergence). X-1: partially adequate (P5-004 M-1). |

Acceptance criteria: AC1 (manual only; nothing scheduled — `routes/console.php`
has no entry) pass; AC2 pass (genuine two-process CAS); AC3 pass; AC4 pass in code
and my probes, test evidence partial (M-1); AC5 pass; AC6 pass.

- **M-1 — MEDIUM** — same finding as P5-004 M-1 (test evidence for AC4).
- **L-1 — LOW** — `retry()` runs `active()` (queued/translating) before the
  eligibility check and ignores `pending`/`completed` siblings. Q16: a `pending` or
  `completed` sibling row for the same target makes the retry CAS raise a raw
  `UniqueConstraintViolationException` (the partial index covers both). Current
  code cannot create either sibling, so this is unreachable today. Also see
  P5-004 L-2 (stale eligibility).
- **INFO — non-retryable failure is a permanent dead end.** With X-2 closed,
  `request()` and `retry()` both refuse a non-retryable failed row, and no code path
  creates a fresh attempt. `CONFIGURATION_ERROR` (for example a wrong worker
  token), `MALFORMED_OUTPUT`, and `PERSISTENCE_FAILED` are therefore final for that
  (transcription, target) until someone edits the database. This is consistent with
  the ADR-018 taxonomy the earlier review recommended, but the HPO should decide the
  P5-006 UX (for example, an explicit "start over" after operator remediation)
  before P5-006 ships.

## 4. Evidence

Reproduced by me (Windows, PHP 8.4.24 via Herd, in-memory SQLite per `phpunit.xml`):

| Evidence | Result |
|---|---|
| `php artisan test --compact tests/Unit/Translation tests/Feature/Translation` | 109 passed, 280 assertions (matches the implementer) |
| `php artisan test --compact` (full, working tree) | 542 tests: 541 passed, 1 skipped, 2 warnings, 1731 assertions (matches the implementer) |
| **Clean checkout of HEAD** (`git archive HEAD`, vendor + `.env` copied): translation tests | **109 passed, 280 assertions** — no untracked file needed |
| Clean checkout of HEAD: `worker/tests/test_translation.py` | 5 passed |
| Working tree: full worker suite (`worker/.venv`) | 41 passed |
| `vendor/bin/pint --test` (read-only) on all reviewed PHP paths and tests | passed |
| `phpstan analyse --memory-limit=1G` (project config) | 0 errors |
| Reviewer probes Q1–Q21 (real classes; scratch files outside the repository) | as cited above |
| Mutation runs M1–M4, M7, M9 (scratch copy of clean HEAD, repository untouched) | M1, M2, M7 survived; M3, M4, M9 killed |
| SQLite lock-semantics probe (two PDO connections) | second connection cannot commit between the writer's SELECT and UPDATE |

Clean-checkout full suite (informational, B-001 wider baseline): 400 tests, 340
passed, 59 failed, 1 skipped. 58 failures are `Vite manifest not found` — my
`git archive` has no `public/build` (gitignored), an artifact of the method, not a
defect. The one remaining failure is
`TranscriptExportTest › export controls depend on completed status…` (Phase 4
baseline; `TranscriptionExportController` is uncommitted — the wider B-001 item
already recorded in `BLOCKERS.md`). **No translation test fails at HEAD.** The
count is lower than the working tree's 542 because Phase 3/4 tests are untracked.

Taken from the implementer, not reproduced: the cycle-2 pre-review counts at
intermediate commits (103/104/109). Not reproducible here: the real translation
model (`transformers`/`torch`/`sentencepiece` are not installed), real Redis, and
any CI run (none exists for this branch).

## 5. Residual items, assessed separately

| Item | Rating | Can wait until P5-008? |
|---|---|---|
| P5-004 L-1: no job `$timeout` / `failed()` (also Q18 stranding) | LOW | Yes, but the worker `--timeout`/`retry_after` runbook and a per-job `$timeout` must exist before the real-provider gate |
| P5-004 L-2: zero-segment dispatch | LOW | Yes |
| B-001 clean-checkout reproducibility | Closed **for Phase 5**: a clean HEAD checkout passes all 109 P5 PHP tests and the 5 worker translation tests. The wider Phase 3/4 baseline (untracked review files, `TranscriptionClaimRaceWorker`, unique-segment-index migration, uncommitted `config/database.php` `busy_timeout`, and others) remains an HPO action recorded in `BLOCKERS.md` | Yes for Phase 5; the HPO must still decide the wider baseline |
| Worker runtime dependency correctness | Declared set is correct for the code; unexecuted, unbounded, and approval not recorded (INFO-1, INFO-2) | The real-model gate is P5-008; the HPO should acknowledge the dependency addition earlier |

## 6. Is starting P5-006 safe?

**No BLOCKER/HIGH remains, so no finding makes starting P5-006 unsafe under the
rubric.** P5-006's own contract requires P5-005 DONE, which needs HPO closure of this
VERIFIED verdict. Three things should be settled before P5-006 is *verified*
because the UI exposes them directly:

1. P5-004 M-2 (concurrent first request raw exception) and M-3 (stranded queued
   row that is never re-dispatched). Both are small, local fixes in
   `TranslationOrchestrator`/`TranslationRetry`.
2. The dead-end UX for non-retryable failures (HPO decision, INFO above).
3. P5-006 must add authorization at the action layer and reload the row before
   calling `retry()` (P5-004 L-2).

Findings that may wait until P5-008: P5-004 L-3 and L-4, P5-003 L-1..L-4 and
INFO, P5-002B L-1/L-2 (tests), P5-005 L-1, the wider Phase 3/4 baseline.

## 7. Recommended follow-ups (VERIFIED is not DONE; HPO closure required)

Per policy, MEDIUM/LOW findings become READY follow-up tasks; none starts automatically.

1. **Test-only follow-up (P5-004/P5-005): close the X-1 evidence gap.** Add
   in-flight tests for late `fail()` (typed failure and unexpected exception) after
   recovery, and after recovery + retry; add a recovery-CAS race test. Both
   mutations M1 and M2 must fail the suite. Add a within-tolerance timestamp test
   (M7) and nonexistent/foreign-id writer tests.
2. **P5-004 hardening:** `request()` catches `UniqueConstraintViolationException`
   and converges on the active row; `request()` re-dispatches a `queued` row it
   returns (Phase 3 parity) or a recovery covers stale `queued` rows; wrap the
   post-claim invocation build in the failure handler.
3. **P5-005 hardening:** put retryable failure codes in the retry CAS predicate;
   treat `pending`/`completed` siblings as convergence targets.
4. **HPO:** acknowledge the worker dependency addition; decide the non-retryable
   dead-end UX; commit or otherwise resolve the wider Phase 3/4 baseline.
5. **Before P5-008:** per-job `$timeout` and a written worker command
   (`queue:work --queue=translation --timeout=...`); zero-segment handling; tighten
   provider/validator test assertions to the failure code.

Only items 1 and 2 are worth completing before P5-006 is verified. VERIFIED does
not authorize production deployment.
