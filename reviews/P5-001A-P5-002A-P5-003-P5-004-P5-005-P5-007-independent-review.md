# Phase 5 — Independent Review: P5-001A, P5-002A, P5-003, P5-004, P5-005, P5-007

Reviewer: Claude Code (independent reviewer role, `.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-21
Branch: `phase5-7/parallel-2026-09-21`, HEAD `714288a`
Scope: the six tasks above only. No application code, test, migration, task
status, or governance file was modified by this review. No task is marked DONE.
P5-006 and P5-008 were not started.

Independence note: `reviews/P5-001A-independent-review.md`,
`P5-002A-independent-review.md`, `P5-003-independent-review.md` already exist
(untracked). I formed the verdicts below from source, tests, and my own
executed probes before comparing. Where I concur with those artifacts I say so;
findings marked **[new]** were not in them.

## 1. Verdicts

| Task | Verdict | Blocking findings | Non-blocking findings |
|---|---|---|---|
| P5-001A DTO input guards | **VERIFIED** | none | INFO-1 |
| P5-002A Writer correctness | **VERIFIED** | none | M-1, M-2 [new], L-1, L-2 [new], L-3 |
| P5-003 Provider boundary | **CHANGES_REQUESTED** (still cycle 1 of 3; no remediation commit exists) | H-1 | M-1, M-2, L-1..L-3, INFO |
| P5-004 Queue / lifecycle | **CHANGES_REQUESTED** | X-1, X-2 (shared) | M-1 [new], M-2 [new], L-1, L-2, INFO |
| P5-005 Failure / retry / recovery | **CHANGES_REQUESTED** | X-1 (AC4), X-2 (AC2) | M-1 [new], L-1 |
| P5-007 Translation export | **VERIFIED** | none | L-1, L-2 [new], INFO |

Severity legend: BLOCKER / HIGH / MEDIUM / LOW / INFO. HIGH prevents VERIFIED.

## 2. Findings

### Cross-task (behavioral backbone)

**X-1 — HIGH — No attempt identity or fencing; late writers overwrite newer state** [new]
Affects P5-005 AC4 ("Old writers cannot overwrite newer authoritative state"),
P5-004 (`ProcessTranslation::fail()`), P5-002A writer state check.
The retry model reuses one `translations` row (`failed → queued`), so an old
job cannot be told apart from the current attempt. `ProcessTranslation::fail()`
(`app/Jobs/ProcessTranslation.php`) sets `failed` for any state except
`completed`; `StaleTranslationAttemptRecovery` CAS is `status = translating`
only (no `started_at`/token); the writer accepts any non-`failed` row.
Reproduced (probes P9, P10, in-memory SQLite, real classes, no mocks of the
code under test):
- P9: stale recovery marks the row `failed/PROVIDER_TIMEOUT` (retryable); the
  old job's late writer is rejected with `InvalidRequest`; `fail()` then
  re-stamps `failure_code = INVALID_REQUEST` → `isEligible()` is false. A
  recoverable failure became non-retryable.
- P10: recovery → manual retry (row `queued`, job dispatched) → the old job
  fails late → `fail()` flips the *new* queued attempt to `failed`.
Likelihood is low under defaults (stale threshold = 300 s timeout + 60 s), but
the acceptance criterion is explicit, the Phase 3 precedent fences by attempt
row, and `attempt_stale_seconds` is operator-lowerable. The existing test only
proves "recovery never overwrites completed"; no test exercises AC4.

**X-2 — HIGH — Two divergent re-run paths for a failed translation** [new]
`TranslationOrchestrator::request()` (P5-004) creates a **new** row when only a
`failed` row exists; `TranslationRetry::retry()` (P5-005) flips the **same**
row. Reproduced:
- P12: `request()` after a non-retryable failure (`MALFORMED_OUTPUT`) silently
  creates a second row and queues it — bypassing the retry-eligibility gate.
- P11: with failed row A and active row B (from `request()`), `retry(A)` leaks a
  raw `UniqueConstraintViolationException` from the partial unique index (500),
  not a converged/typed result. AC2 ("concurrent identical retries yield
  exactly one new active attempt") is only proven for identical retries of the
  same row.
Makes P5-006 unsafe (UI will expose both "Translate" and "Retry").

### P5-003

**H-1 — HIGH — Provider boundary accepts empty, partial, and foreign-index responses (AC4)** (concur with earlier artifact)
`HttpTranslationProvider::translate()` passes only target/provider/model to
`TranslationResponseValidator::fromArray()`; the invocation's segment set is
never compared. Probes P1–P3: `segments: []`, 1-of-2 segments, and index 99
with timestamps 500–900 s all return a `TranslationResult` without error. No
test covers it. Data-integrity blast radius is contained by the P5-002A writer
(P5-004 test "fails when misaligned"), which is why this is one HIGH and not a
BLOCKER — but AC4 is a provider-boundary criterion and is not met, and a
future provider adapter would inherit the gap.

**M-1 — MEDIUM — Loose type coercion; raw exception leak** (concur)
P4: `text: ["a"]` → `ErrorException: Array to string conversion` (not a
`TranslationException`). P5: `segment_index: "abc"`, timestamps `"zzz"` are
silently cast to `0`/`0.0`.

**M-2 — MEDIUM — `ms` maps to `msa_Latn`** (concur; not locally reproducible)
`worker/translation.py` `NLLB_CODES["ms"] = "msa_Latn"`. The published
NLLB-200/FLORES-200 code for Standard Malay is `zsm_Latn`; an unknown token
resolves to the unk id, so the primary product target would produce garbage on
the real path. `transformers` is not installed here, so I could not execute it;
worker tests patch `translate_segments`, so no mapping test exists.

**L-1** — non-envelope HTTP errors (FastAPI 401/422 `detail`) all map to
`ProviderUnavailable` (retryable), so a bad token looks retryable.
**L-2** — transport exceptions are not mapped (P6: `ConnectionException`
escapes the provider; the job's catch-all turns a timeout into
`ProcessingFailed` rather than `ProviderTimeout`).
**L-3** — persisted `provider`/`model` come from Laravel config, not the worker
response (P14); `translation.provider` is a label, not an implementation
selector; the pre-review's "binds from `translation.provider`" is overstated.
**INFO** — `transformers`/`torch`/`sentencepiece` are not in
`worker/requirements.txt` (default provider not runnable; dependency change
needs approval); `/translate` route wiring is uncommitted (B-002); tokenizer
`src_lang` is never set per segment (NLLB defaults to `eng_Latn`); silent
512-token truncation; empty token sends `Authorization: Bearer `; class is
`HttpTranslationProvider`, not the contract's `SelfHostedTranslationProvider`.

### P5-004

**M-1 — MEDIUM — `TranslationLifecycle` is never used** [new]
Scope item 3 ("Lifecycle transitions enforced via `TranslationLifecycle`").
`grep` of `app/` finds the class only at its definition. Transitions are raw
`update()`/`forceFill()`. Consequences: writer completes from `queued`
(P8), `fail()` accepts any non-completed source. `TranslationAlignment`
(passthrough policy) is likewise unused in the app layer.

**M-2 — MEDIUM — Job is not routed to a dedicated/configurable queue** [new]
P13: `ProcessTranslation` is dispatched with `queue = null`,
`connection = null` (default connection is `database` in `.env`). Phase 3
uses `config('transcription.queue')` / `queue_connection` on Redis. A worker
consuming the `transcription` queue will never run translations. Objective
says "over the existing Redis queue".

**L-1** — no timeout/`failed()` alignment: no `$timeout` or `failed()` handler;
`retry_after` (90 s) and default `queue:work --timeout` (60 s) are shorter than
the 300 s provider timeout, so a killed job waits for stale recovery (360 s).
A dispatch failure or lost message leaves a `queued` row with no recovery path
(parity with Phase 3; recovery scans `translating` only).
**L-2** — P18: a zero-segment (no-speech) transcript still dispatches a
provider call.
**INFO** — orchestrator/retry perform no authorization by design (P5-006 must
gate via `TranscriptionPolicy`); `test:*-race-worker` commands live in
`app/Console/Commands` but are env-guarded (Phase 3 precedent). Concurrency
evidence is genuine (two OS processes, real `claim()` via reflection).

### P5-005

**M-1 — MEDIUM — Tests do not exercise AC4 or the request/retry seam** [new]
`StaleTranslationAttemptRecoveryTest`/`TranslationRetryTest` cover happy paths
and "never overwrites completed". The pre-review marks AC4 PASS on "CAS
predicates"; probes P9/P10 show otherwise. The two-process retry test is
genuine (real `TranslationRetry`, real barrier) but uses a hand-built table
without the partial unique index, so it cannot surface X-2.
**L-1** — recovery's CAS is not fenced by `started_at`, so it can in principle
fail a row that was retried and re-claimed between its SELECT and UPDATE
(subsumed by X-1's fix).

### P5-002A

**M-1 — MEDIUM — `translationId` not validated when a completed same-target row exists** (concur)
P15: completed row for the target exists + non-matching id → the completed row
is returned silently, not rejected (scope item 1 "reject a non-matching id").
No data is written and the only caller passes its own id, hence non-blocking.
**M-2 — MEDIUM — Writer persists provider-echoed alignment metadata** [new]
`assertAligned()` checks indices and timestamps only within ±0.0005 s, then
persists the *provider's* values. P7: a result claiming `source_language = zh`
over `en` source segments persists `zh`; P17: 0.0004 s drift persists. P5-001
makes the source-language marker a hard alignment invariant.
**L-1** — legal source state is "not failed", not `translating` (P8) (concur).
**L-2** [new] — the "rollback" test throws in `Translation::creating`, before
any segment is written, so it does not prove segment-level rollback. The code is
correct (P16 forces failure on the 2nd segment; parent row rolls back) — this is
a test-adequacy gap only.
**L-3** — `assertAligned()` runs outside the transaction (concur).

### P5-007

**L-1 — LOW — AC5 (UTF-8/multilingual) has no test evidence**
Tests use ASCII Malay only; DOCX content is never inspected. Behavior is
correct: P19 exports zh + Tamil through TXT/SRT/VTT/DOCX (DOCX `document.xml`
unzipped) with byte-correct text and inherited ms timestamps. The pre-review's
"PASS" rested on ASCII.
**L-2 — LOW** [new] — filename collision possible: source "Weekly Meeting MS" →
`weekly-meeting-ms.txt` equals the translated export of "Weekly Meeting" → `ms`
(P20).
**INFO** — SRT/VTT cue text is not sanitized (mirrors frozen P4-005; model
output containing a blank line or `-->` would split a cue); depends on
`SegmentTimestamp`, which is untracked (see G-1). Authorization, 403 on
non-completed, 404 on unknown id, admin access, and no-provider-call all
verified (P21 + existing tests).

### P5-001A

**INFO-1** — pre-review evidence figures are combined-state, not task-specific
(concur). Guards verified: `NAN`/`INF`/`-INF` rejected (finite check precedes
range checks); duplicate invocation indices rejected; empty list accepted;
2 source files, ~18 lines, no behavior change otherwise.

### Repository / governance

**G-1 — MEDIUM — Committed P5 commits are not self-contained** (recorded as
B-001/B-002; needs HPO action)
At HEAD, `app/TranscriptExperience/SegmentTimestamp.php`, the
`transcription_segments.language` migration/cast, and the
`TranscriptionSegmentFactory` change are untracked/uncommitted. A clean
checkout of `714288a` would fail P5-007 SRT/VTT (missing class) and
`ProcessTranslation::sourceSegments()` (`->language`). ADR-023 defines
`IMPLEMENTED_PENDING_REVIEW` as including task-scoped commits; that holds only
for the dirty working tree, which is what I reviewed.
**G-2 — INFO** — task-status edits for P5-001A/002A/003 and the three earlier
review artifacts are uncommitted working-tree changes. P5-004/005 were built on
P5-003 while its HIGH-1 was open; ADR-023 §4 requires reconciliation once P5-003
is fixed (the consumed contract only tightens, so no rework is expected beyond
X-1/X-2).

## 3. Scope, compatibility, and boundary checks

- **Scope**: `git diff --name-status d08c050..HEAD` shows only Phase 5 files plus
  `AppServiceProvider` (binding), `bootstrap/app.php` (route file load),
  `BLOCKERS.md`, and task files. No Phase 6/7 work, no UI, no schema change to
  `transcriptions`/`transcription_segments`.
- **Source immutability (D5-05)**: job/orchestrator/writer never write
  `Transcription`/`TranscriptionSegment`; asserted in tests and by inspection.
- **Migrations**: three additive migrations (translations, translation_segments
  with unique index, SQLite-only partial unique index consistent with D7-01).
  Cascade delete from `transcriptions` is consistent with derived data.
- **Provider boundary data**: request carries ids + text only; no media path;
  no transcript text in logs.
- **Ownership**: export authorizes via `TranscriptionPolicy::view` (owner/admin);
  no other translation surface exists yet.
- **Mock-vs-real**: no task claims the real-provider gate. Nothing in P5-003
  has run against a real model.

## 4. Evidence

Reproduced by me (Windows, PHP 8.4.24, in-memory SQLite per `phpunit.xml`):
- `php artisan test --compact tests/Unit/Translation tests/Feature/Translation`
  → 92 passed, 257 assertions.
- `php artisan test --compact` (full) → 525 tests, 524 passed, 1 skipped,
  2 warnings, 1710 assertions (matches the P5-007 pre-review figure).
- `vendor/bin/pint --test` on all Phase 5 PHP paths → passed (read-only mode).
- `vendor/bin/phpstan analyse` → 0 errors.
- `worker/.venv pytest worker/tests` → 40 passed (`transformers` not installed).
- Executed probes P1–P21 (reviewer-owned scripts, scratchpad, not in the
  repository): `probe.php`, `probe2.php`, `probe3.php`. All 21 behaviors
  described above were confirmed after isolating facade state between probes.

Taken from the implementer, not reproduced: pre-review artifacts' historical
counts (57/70/84/8 tests at each stage); the NLLB code list (external
knowledge). No CI evidence exists for this branch.

Files inspected: `app/Translation/*` (all), `app/Models/Translation*.php`,
`app/Actions/Translation*.php`, `StaleTranslationAttemptRecovery.php`,
`app/Jobs/ProcessTranslation.php`, `app/Console/Commands/*Translation*`,
`app/Http/Controllers/TranslationExportController.php`,
`routes/translation.php`, `bootstrap/app.php`, `config/translation.php`,
three translation migrations, `worker/translation.py`, `worker/main.py`
(translate diff), `worker/requirements.txt`, all `tests/**/Translation*`,
`tests/Support/RecordingTranslationProvider.php`, the four pre-review files,
tasks P5-001A/002A/003/004/005/007, PHASE5-PLANNING, ADR-022/023,
PHASE5-7-CONTROLLED-PARALLEL-EXECUTION.md, BLOCKERS.md.

## 5. Is starting P5-006 safe?

**No.** Blocking, in order: X-2 (single re-run semantics; the UI will surface
both paths), X-1 (stable failure/retry state under stale recovery), P5-003 H-1
(the user-visible failure taxonomy depends on it), and P5-005 being
CHANGES_REQUESTED (P5-006's contract requires the retry surface DONE). P5-006
must also add authorization at the action layer (INFO above). Not blocking but
needed before P5-008: P5-003 M-2 (`zsm_Latn`), P5-004 M-2 (queue routing),
undeclared worker model dependencies, B-002 worker wiring commit, G-1.

## 6. Recommended corrective order

1. **P5-003 (cycle 2)**: enforce segment count/index set/timestamps and
   source-language echo against the invocation at the provider boundary with
   taxonomy failures; strict type validation; map transport exceptions;
   `zsm_Latn` plus a mapping test; tests for empty/partial/foreign.
2. **Small writer follow-up (P5-002B)**: require `translating` as the legal
   completion source; validate `translationId` even when a completed row exists;
   copy timestamps and source-language from the source rows; add a
   segment-level rollback test.
3. **P5-004 rework**: add an attempt fence (additive; e.g. a per-claim token
   compared in `fail()`, writer, and recovery); route `fail()` through
   `TranslationLifecycle`; make `request()` on a failed target delegate to the
   retry path (or reject) so there is one identity model; dedicated queue and
   connection config; skip provider call for zero segments.
4. **P5-005 rework**: fenced recovery CAS; `retry()` converges (typed result,
   no raw unique-index exception) when another active row exists; tests for
   AC4 (recovery→retry→late-old-job) and the request/retry seam, including
   against the real partial unique index.
5. **P5-007 follow-up tests (LOW)**: multilingual round-trip for all four
   formats including DOCX content; collision note.
6. **HPO**: commit or otherwise resolve the Phase 3/4 baseline (G-1, B-001,
   B-002) and approve or decline worker model dependencies before P5-008.
   Then re-review P5-003 → P5-004 → P5-005 in that order; P5-006 may start after
   P5-005 is VERIFIED.

Actionable MEDIUM/LOW findings on VERIFIED tasks (P5-002A, P5-007) should become
READY follow-up tasks referencing this artifact; per ADR-023 they must be
resolved before Phase 5 closure. VERIFIED is not DONE; HPO closure is required.
