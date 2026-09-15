# P2-003 (regression) / P2-004A / P2-004A1 / P2-005 — Remediation Cycle 2 Independent Re-Review

Reviewer: Claude Code

Date: 2026-09-13

Prior evidence trail (preserved, not rewritten):

- `reviews/P2-004A-P2-004A1-P2-005-independent-review.md` (first pass, CHANGES_REQUESTED, four findings)
- `reviews/P2-004A-P2-004A1-P2-005-remediation-report.md` (implementer's first remediation claims)
- `reviews/P2-003-P2-004A-P2-004A1-P2-005-independent-re-review.md` (second pass, CHANGES_REQUESTED — New Finding A: cleanup check-then-act race; New Finding B: undocumented/unguarded FFprobe dependency; New Finding C: unreproducible targeted-suite count; P2-003 regression VERIFIED)
- `reviews/P2-003-P2-004A-P2-004A1-P2-005-remediation-cycle-2-report.md` (implementer's second remediation claims, independently verified below)

Implementation owner: Codex, under `DECISIONS.md` ADR-012.

This review does not modify code, tests, migrations, or governance records, and does not stage or commit anything. It does not authorize P2-007 or Phase 3.

## 1. Overall Summary

The handoff report's claims for mechanical verification (test counts, Pint, PHPStan, frontend build, `git diff --check`) are all independently reproduced exactly as reported — see §7. The FFprobe documentation/skip-guard gap (previous cycle's New Finding B) is genuinely and fully resolved, and the real WAV fixture test was independently confirmed to reach a real, non-mocked `ffprobe` executable and assert genuine parsed metadata on this review machine (§4). The previous cycle's traceability gap (New Finding C) is also resolved — the report now names exact commands and exact results.

The cleanup check-then-act gap (previous cycle's New Finding A) is fixed **at the sequential-code level**: `CleanupStaging` now re-reads claim and `MediaFile` state immediately before deletion, inside a `DB::transaction`, and a new test proves this recheck fires. However, independently tracing the claimed concurrency mechanism against the actual, accepted database engine for this repository (SQLite — confirmed in `architecture.md:14`, `.env`, `.env.example`, and `config/database.php`) surfaces a **new, material discrepancy that the remediation report does not disclose or test for**: `lockForUpdate()` compiles to an empty string on SQLite (verified directly in `vendor/laravel/framework/.../SQLiteGrammar.php:31-34`), so the "row-level lease" the report describes taking does not exist. The safety of the fix in practice depends entirely on SQLite's own coarse, whole-database transaction locking and an **unconfigured, PDO-driver-default busy timeout that the repository does not pin anywhere** — a mechanism nobody designed for, documented, or tested, and no test in the suite opens a second real database connection to exercise it. See New Finding D below.

This is a narrower, more specific gap than the previous cycle's Finding A (which was about the absence of any recheck at all); the recheck now genuinely exists and the specific single-connection scenario it targets is closed. But the deeper question this review was explicitly asked to answer — "do not accept a lock merely because locking syntax exists" / "whether transaction isolation or the test database driver makes the test misleading" — resolves to: the locking syntax exists but does nothing, the test database driver's *incidental* behavior (not the claimed mechanism) is carrying all of the actual protection, and no test would reveal this even if it stopped working, because no test uses two real connections. This prevents a clean VERIFIED for P2-004A and P2-004A1.

## 2. Verdicts

| Surface | Verdict |
|---|---|
| P2-003 (regression re-review of `MediaIngestionService.php`) | **VERIFIED** |
| P2-004A (`media:cleanup-staging` command) | **CHANGES_REQUESTED** |
| P2-004A1 (staging-claim/lease contract) | **CHANGES_REQUESTED** |
| P2-005 (`MediaMetadataProbeService` / FFprobe) | **VERIFIED** |

P2-004 closure: **remains valid** (unaffected; see §6, unchanged from the prior re-review's analysis, independently re-checked).
P2-006 closure: **remains valid** (unaffected; see §6).

**Governance consequence:** this is the **third** consecutive CHANGES_REQUESTED cycle for P2-004A and P2-004A1 (cycle 1: `reviews/P2-004A-P2-004A1-P2-005-independent-review.md`; cycle 2: `reviews/P2-003-P2-004A-P2-004A1-P2-005-independent-re-review.md`; cycle 3: this review). Per `.ai/guidelines/orchestration-policy.md:35` ("A task may undergo at most three autonomous repair and re-review cycles. If it still cannot pass review after the third cycle, escalate it as BLOCKED..."), **Work should now escalate P2-004A and P2-004A1 to BLOCKED and record this evidence**, rather than routing a fourth autonomous repair cycle back to the implementation owner. This is a mechanical application of an already-adopted repository rule, not a new decision by this reviewer.

P2-007 and Phase 3 remain **not authorized** by this review.

## 3. Resolution Status of Prior Cycle's Findings

### New Finding A (cleanup check-then-act race): **PARTIALLY RESOLVED — see New Finding D**

The specific code gap identified — "no re-check of claim state, no database transaction, no row lock... between the read and the delete" — is fixed at the source level. `app/Console/Commands/CleanupStaging.php:122-176` now wraps the final decision in `DB::transaction()`, re-reads the claim with `lockForUpdate()` (line 129) and re-checks for a committed `MediaFile` (lines 136-141) immediately before deleting files and the directory (lines 163-171), and the new test `cleanup does not delete a candidate claimed after initial eligibility observation` (`tests/Feature/CleanupStagingCommandTest.php:136-162`) proves the recheck fires: it hooks the `StagingCleanupCandidateObserved` event (dispatched at line 123, before the transaction opens) to insert a competing active claim, and confirms the file survives. I independently confirmed this test is discriminating, not vacuous: tracing the pre-cycle-2 code path described in the prior re-review (single read, no recheck, unconditional delete), this exact test would have failed against that implementation (the file would have been deleted despite the injected claim). This satisfies the instruction to reject tests that cannot fail against the previous defect, **for the single-connection scenario it exercises**.

However, this test — and the two related tests in `IngestionCompensationContractTest.php` ("defers staging cleanup while a retry owns the active attempt claim", "requires restaging with the same attempt identity after cleanup wins a race") — are all single-process, single-database-connection, sequentially-ordered tests: the "competing" claim is always written and committed *before* the cleanup transaction's own `SELECT` executes, using the same PHP process and the same database connection as the command under test. None of them open a second real connection or process. This means none of them can exercise, or reveal a defect in, genuine cross-connection interleaving — which is exactly the scenario the claimed `lockForUpdate()` mechanism is supposed to protect. See New Finding D for what this leaves unverified.

### New Finding B (undocumented/unguarded FFprobe dependency): **RESOLVED**

See §4 for full independent verification. `.env.example:38-39` now documents `RTFTT_FFPROBE_PATH`; `README.md:29-45` documents the FFprobe/FFmpeg operational dependency, its bounded P2-005-only purpose, default `PATH` resolution, override variable, a verification command, and the test's skip behavior; `tests/Feature/MediaMetadataProbeServiceTest.php:41-43` guards the real-probe test with `isAvailable()` + `markTestSkipped()`. Independently reproduced: the full suite is green with the real-probe test genuinely executing (not skipped) on this review machine, which has a real FFprobe installation on `PATH` (§4, §7).

### New Finding C (unreproducible targeted-suite count): **RESOLVED**

The cycle 2 report (§H) names the exact command and file list behind its "focused remediation/regression" claim. Independently re-run verbatim (§7): identical result (47 passed, 177 assertions).

## 4. P2-005 — Independent FFprobe Verification

- **Dependency documentation**: confirmed present and accurate in `.env.example` and `README.md` (§3 above). No Phase 3/transcription scope is implied; the README text explicitly scopes FFprobe to "already persisted private media file" metadata only.
- **Behavior when FFprobe is unavailable**: `MediaMetadataProbeService::probe()` (`app/Services/MediaMetadataProbeService.php:54-66`) wraps `runFfprobe()` in `try/catch (\Throwable)`, logs, and returns all-null metadata — confirmed by `probe and update sets null values when probe fails` (passing, independently re-run).
- **Behavior when FFprobe is available**: independently re-ran `tests/Feature/MediaMetadataProbeServiceTest.php` alone on this review machine, which has a real FFprobe (WinGet `Gyan.FFmpeg.Shared`) discoverable on `PATH`. Result: **5 passed, 13 assertions, 0 skipped** — the real-probe test genuinely executed rather than being skipped, confirming `isAvailable()` correctly detects a real installation and the skip guard does not fire when it shouldn't.
- **Genuine, non-mocked real-file exercise**: read `tests/Feature/MediaMetadataProbeServiceTest.php:38-68` directly. It constructs a real, valid, minimal PCM WAV file (RIFF/WAVE/fmt/data chunks, 8000 Hz, 16-bit mono, exactly 8000 samples = 1.000s), writes it to the faked local disk, and calls the real `probe()` method with no mocking of `Symfony\Component\Process\Process` or the storage adapter. Assertions check `duration_seconds === 1`, `audio_codec === 'pcm_s16le'`, `sample_rate === 8000`, `channels === 1`, `video_codec === null` — meaningful, specific metadata, not a placeholder assertion.
- **Command invocation safety**: `runFfprobe()` (`MediaMetadataProbeService.php:120-136`) builds the `Symfony\Component\Process\Process` from an array (`[$this->ffprobePath, '-v', 'quiet', '-print_format', 'json', ...]`), which does not go through a shell — no shell-injection surface from the file path. A 30-second timeout is set (line 131).
- **Partial/inconsistent state risk**: `probeAndUpdate()` only ever writes the five pre-existing nullable technical columns (`duration_seconds`, `audio_codec`, `video_codec`, `sample_rate`, `channels`) and never touches `status` or any other lifecycle field — confirmed by reading the full file; probe failure cannot leave the record in an inconsistent lifecycle state because it does not participate in the lifecycle state machine at all.
- **Capability/skip policy**: explicit, narrow, and justified (`isAvailable()` check + `markTestSkipped()` with a specific reason). The rest of the suite does not depend on FFprobe being present — confirmed by the fact that the full suite is 216/216 passing regardless of FFprobe availability (only this one test's coverage of the success path is conditional).

**Verdict: P2-005 is VERIFIED.** `MediaMetadataProbeService.php` itself was not changed in this cycle (confirmed unmodified from the prior cycle, consistent with the handoff report's own claim), and its previously-verified fallback/parsing behavior is unaffected. The specific gap from the prior cycle (undocumented, unguarded, environment-fragile dependency) is closed.

## 5. P2-004A / P2-004A1 — New Finding D (HIGH): Claimed row-level lock is a no-op on the repository's actual database engine; the real protection is untested, undocumented, and dependent on an unpinned default

**Files:** `app/Console/Commands/CleanupStaging.php:125-134`, `app/Actions/MediaIngestionService.php:229-241`, `config/database.php:41`, `architecture.md:14`, `reviews/.../remediation-cycle-2-report.md:16-24` (claimed mechanism description).

**Evidence:**

1. The repository's accepted and only configured database engine is SQLite: `architecture.md:14` ("SQLite (Development)"), `.env:23` and `.env.example:23` (`DB_CONNECTION=sqlite`), `phpunit.xml:26-27` (tests also run against SQLite `:memory:`), `config/database.php:20` (default falls back to `sqlite`).
2. Laravel's SQLite query grammar strips locking clauses entirely: `vendor/laravel/framework/src/Illuminate/Database/Query/Grammars/SQLiteGrammar.php:31-34` —
   ```php
   protected function compileLock(Builder $query, $value)
   {
       return '';
   }
   ```
   This means `StagingClaim::query()->...->lockForUpdate()->first()` in `CleanupStaging.php:126-130` issues a **plain `SELECT`**, identical to one without `lockForUpdate()`. No row lock, no `SELECT ... FOR UPDATE`, is ever sent to the database on this engine. This directly contradicts the handoff report's own description of the mechanism ("locks the per-owner/per-attempt claim row with `lockForUpdate()`").
3. `config/database.php:41` sets `'busy_timeout' => null` for the sqlite connection. `Illuminate\Database\Connectors\SQLiteConnector::configureBusyTimeout()` only issues the `PRAGMA busy_timeout` statement `if (isset($config['busy_timeout']))` — and PHP's `isset()` returns `false` for a key whose value is `null`. So the repository **never sets a busy timeout PRAGMA at all**; whatever default the PDO SQLite driver build happens to compile in silently governs concurrent-write blocking behavior, and nothing in the repository pins or documents this.
4. I independently reproduced the actual cross-connection behavior with a standalone two-PDO-connection script against a shared on-disk SQLite file (not part of the repository; run from the scratchpad and discarded), mirroring the real topology (cleanup process and an upload request each hold their own connection):
   - Connection A (cleanup) opens a transaction and does a plain `SELECT` of an expired claim row — the same operation `lockForUpdate()` produces on this engine.
   - Connection B (a concurrent real `stage()` upsert) attempts to `UPDATE` the **same row** while A's transaction is still open, uncommitted, and has issued no write yet.
   - Observed result: B's write **blocked for 60.5 seconds** and then failed with `SQLSTATE[HY000]: General error: 5 database is locked` — not because of any row lock A explicitly took (A had taken none), but because SQLite's own non-WAL locking model escalates *any* open read transaction to a database-wide `SHARED` lock that blocks other connections' writes until the holder commits or a busy-timeout elapses. The observed 60-second window is the PDO/SQLite driver's own compiled-in default, not something this repository configures (see point 3).
5. **Practical consequence of this gap (not itself proof of data loss — see mitigating context below):**
   - The mechanism actually protecting the invariant is an accidental byproduct of SQLite's whole-database locking, not the `lockForUpdate()` row lock the report describes. If this application is ever pointed at MySQL/Postgres/another engine, or if a future PDO/SQLite build ships a different default busy timeout (including `0`, which fails instantly with no wait at all), this exact protection could silently change or disappear, and nothing in the test suite would notice, because:
   - **No test in the repository opens two real database connections.** `CleanupStagingCommandTest`'s new race test and both `IngestionCompensationContractTest` claim/cleanup tests are single-process, single-connection, sequentially ordered (competing writes are always fully committed *before* the command under test even begins its transaction). None of them are capable of detecting whether real concurrent interleaving is actually safe — they only prove the sequential recheck logic is correct, which is necessary but not sufficient for the concurrency guarantee both tasks' acceptance criteria require ("atomic," "genuine post-observation race test," "prove an active P2-003 retry cannot be deleted while it is staging or promoting").
   - `MediaIngestionService::stage()`'s `StagingClaim::query()->upsert(...)` call (line 229) sits **outside** `stage()`'s own `try/catch` (which only wraps `putFileAs`, lines 243-253). If a concurrent write blocks and eventually throws a "database is locked" `QueryException` (as reproduced above), it propagates as a raw, uncaught database exception out of `ingest()`'s top-level catch (which only handles staging/durable cleanup, not user-facing translation) rather than the validated, retryable failure path the rest of the P2-002B/P2-003 contract establishes for other failure modes.
   - Because the file-deletion I/O (`$disk->delete($file)`, `$disk->deleteDirectory($attemptDir)`) happens **inside** the `DB::transaction()` closure (`CleanupStaging.php:163-171`), any slow storage operation directly extends how long SQLite's whole-database write lock is held — meaning cleanup of one large/slow candidate can block **every other unrelated write in the entire application** (any other user's upload, any Livewire save) for the duration of that file I/O, not just writes to `staging_claims`. This is a real, newly-introduced availability/scalability cost that is not mentioned anywhere in the handoff report's "Remaining risks" section.

**Mitigating context (why this is HIGH, not BLOCKER):** My own empirical reproduction shows the specific data-loss scenario — cleanup silently deleting an actively-claimed attempt — does **not** occur in practice on this engine: the competing writer is blocked and, if it cannot proceed within the (unpinned, environment-default) busy-timeout window, fails loudly with a retryable database exception rather than silently losing data or being silently overwritten. No cross-user leakage, no permanent inconsistent state, and the existing P2-002B retry contract still allows the affected request to be retried by the same attempt id after such a failure. The narrow, manually-invoked-only scope of `media:cleanup-staging` (no scheduler in this task's authorized scope, per its own explicit non-scope section) also bounds how often this window is actually exercised in practice.

**What must change before VERIFIED:** At minimum, one of:

(a) Correct the mechanism and its test coverage to match the claim: either explicitly pin and document the SQLite busy-timeout behavior this safety property now silently depends on (`config/database.php`'s `busy_timeout`, currently `null`/unset), and add a genuine two-connection (or two-process) test that opens a second real database connection concurrently with an open cleanup transaction to prove the blocking behavior this review had to reproduce manually; or

(b) Correct the handoff report and task records to describe the actual mechanism (SQLite whole-database transaction locking, not a row-level `lockForUpdate()` lease) so a future reader or reviewer is not misled about what is actually protecting the invariant, and explicitly record the availability trade-off (unrelated application writes blocking during cleanup's file I/O) as an accepted, documented risk rather than an undisclosed one; or

(c) An explicit Product Owner decision accepting the current, engine-incidental protection as sufficient for the current single-engine, manually-invoked-only deployment, recorded as a decision rather than left implicit — mirroring how the previous cycle's Finding A offered an equivalent explicit-acceptance path.

Absent one of these, P2-004A and P2-004A1 cannot be VERIFIED against their own stated acceptance criteria ("atomic," race tests that "prove" the safety invariant rather than assume it).

## 6. P2-003 Regression Re-Review — Detail

Read `app/Actions/MediaIngestionService.php` in full (current working-tree revision) and independently re-traced every element the prior re-review verified, since this file is again the surface this cycle's claim-lifecycle changes touch:

- **Same-attempt idempotency** — `ingest()` (lines 82-88) still checks `findByAttempt()` first and returns the existing record unchanged before touching storage. Unaffected.
- **Cross-user isolation** — `staging_claims` retains its `unique(['user_id', 'upload_attempt_id'])` constraint (`database/migrations/2026_09_13_130000_create_staging_claims_table.php:20`), confirmed enforced by `staging claim enforces unique user attempt constraint` and `staging claim allows same attempt id for different users` (both independently re-run, passing).
- **Retry semantics** — the ambiguous-duplicate-key recovery path (lines 119-159) is unchanged in structure: looks up by owner/attempt before replay, never double-inserts, confirmed by `resolves an ambiguous database result by attempt identity before replaying ingestion` (passing).
- **Claim-before-write ordering** — `stage()` (lines 212-256) still creates/refreshes the claim (now via `upsert()`, lines 229-241) before the first staging byte is written (line 244), and a catchable write failure deletes the just-created/refreshed claim row (lines 247-253) before rethrowing. An uncatchable interruption between claim creation and write completion leaves a claim bounded by the same 24-hour retention window as before — not permanent, not unbounded.
- **Promotion/persistence compensation** — `deleteOrLog()` and the try/catch/finally structure around promotion and the DB transaction (lines 96-173) are textually unchanged from the previously verified revision; claims are additive bookkeeping, not a replacement for existing compensation logic.
- **Cleanup interaction** — this remains the one area with an open gap, attributable to `CleanupStaging` (P2-004A), not to `MediaIngestionService`'s own logic — see New Finding D. `MediaIngestionService`'s own behavior in isolation (claim-before-write, cleanup on failure, bounded claim lifetime) is unchanged and correct.
- **Processing/transcription side-effect prohibition** — confirmed no reference to `Transcription`, `ProcessingJob`, queues, or Phase 3 concerns anywhere in the file.

**Verdict: P2-003's regression surface remains VERIFIED.** No regression was found in this cycle's changes to `MediaIngestionService.php`. The residual concurrency-mechanism gap (New Finding D) belongs to `CleanupStaging`/the claim-lease protocol as a whole (P2-004A/P2-004A1), not to the ingestion service's own, unchanged contract.

## 7. Mechanical Verification (Independently Reproduced)

All commands run directly by this reviewer against the live, unmodified working tree; no files were changed as a result (confirmed via `git status` before/after, excluding the gitignored `public/build/*` output of `npm run build`).

| Check | Remediation report claim | Independently reproduced | Match? |
|---|---|---|---|
| Focused remediation set (`CleanupStagingCommandTest`, `StagingClaimTest`, `IngestionCompensationContractTest`, `MediaMetadataProbeServiceTest`, `MediaIngestionTest`) | 47 passed, 0 failed, 0 skipped, 177 assertions | **47 passed, 177 assertions, 0 failed** | Match |
| Full suite | 216 passed, 0 failed, 1 skipped, 649 assertions (217 total) | **217 tests, 216 passed, 1 skipped, 649 assertions** | Match |
| `MediaMetadataProbeServiceTest.php` alone (FFprobe available on this machine) | Real-probe test not skipped, passes | **5 passed, 13 assertions, 0 skipped** — real-probe test genuinely executed and passed | Match |
| The suite's one skip | (not separately claimed) | Confirmed pre-existing and unrelated: `tests/TestCase.php:13`, a Fortify-feature-flag skip helper, not the FFprobe test | N/A — informational |
| Pint (`--test --format agent`) | passed | **passed** | Match |
| PHPStan (`analyse --no-progress`) | 0 errors | **0 errors** | Match |
| Frontend build (`npm run build`) | passed | **passed** (`built in 1.79s`) | Match |
| `git diff --check` | passed (one harmless CRLF warning) | **exit 0**, one harmless CRLF-normalization warning on `.ai/guidelines/ai-development-os.md` | Match |

No claim in the handoff report's mechanical-verification section (§H) was found to be inaccurate. The report's technical *mechanism* description (§B) — addressed in New Finding D — is the one claim in the report that does not hold up under independent tracing.

## 8. P2-004 / P2-006 Closure Re-Assessment

Unchanged from the prior re-review's analysis, independently re-checked against the current repository state:

**P2-004 closure: remains valid.** Its rationale (P2-003 already implements and verifies staging, validation, checksum, promotion, persistence, retry, and compensation) does not depend on whether P2-004A/P2-004A1 are currently VERIFIED.

**P2-006 closure: remains valid.** Its rationale rests on P2-002B/P2-003 guarantees this cycle's §6 regression re-verification confirms still hold. P2-006 explicitly scoped staging cleanup/lease safety to P2-004A/P2-004A1 rather than claiming it itself.

## 9. Governance / State Consistency

- `CURRENT_STATE.md` and `plan.md` both correctly describe P2-003/P2-004A/P2-004A1/P2-005 as REVIEW/awaiting (regression) re-review, and both explicitly reserve P2-007 and Phase 3 as unauthorized (`CURRENT_STATE.md:11,62-67,75-76,89-90,92-98`; `plan.md:10,106-107,141`).
- `DECISION_QUEUE.md`'s "Open Decisions" remains correctly "None"; `DECIDED — DECISION-P2-REMEDIATION-001` remains recorded and consistent with `DECISIONS.md` ADR-012.
- `tasks/P2-004A-staging-cleanup-command.md` and `tasks/P2-004A1-upload-attempt-lease-contract.md` both carry `Status: REVIEW` (not DONE/VERIFIED); `tasks/P2-005-media-metadata-probe.md` carries `Status: REVIEW`.
- No task file, `CURRENT_STATE.md`, or `plan.md` claims any of the four reviewed surfaces is VERIFIED or DONE beyond what this and the prior review have actually established (P2-003 and, as of this review, P2-005).
- No reference to `Transcription`, `ProcessingJob`, queues, Redis, Horizon, or worker code exists in any file this cycle changed (confirmed by direct inspection of `CleanupStaging.php`, `MediaIngestionService.php`, `StagingCleanupCandidateObserved.php`, and the changed tests/config/docs). The 524,288,000-byte upload boundary and the supported-media matrix are untouched.
- No unrelated governance boundary was reopened, and no prior closure decision (P2-001, P2-001A, P2-002, P2-002A/B/C, P2-004, P2-006) was contradicted by anything found in this cycle.
- **New governance item this cycle must record:** per §2, this is the third consecutive CHANGES_REQUESTED cycle for P2-004A and P2-004A1. Work should record this and move both tasks to BLOCKED per `.ai/guidelines/orchestration-policy.md:35` rather than authorizing a fourth autonomous repair attempt.

## 10. Exact Safe Next Action

1. Work may **not** close P2-004A or P2-004A1 as VERIFIED/DONE.
2. Work **may** record P2-003's regression surface and P2-005 as VERIFIED based on this review, updating `CURRENT_STATE.md` accordingly, subject to the State-to-Action Contract's normal closure step (this review's evidence alone does not authorize closure — Work performs the state-record update).
3. Because this is the third CHANGES_REQUESTED cycle for P2-004A and P2-004A1, Work should escalate both to **BLOCKED** per `.ai/guidelines/orchestration-policy.md:35` and record New Finding D as the blocking evidence, rather than routing a fourth autonomous repair cycle to the implementation owner. Unblocking requires one of the three remediation paths in §5 ("What must change before VERIFIED"), which includes an explicit Product Owner decision as one valid path — this review does not select among them.
4. P2-007 remains blocked pending resolution of P2-004A/P2-004A1. Nothing in this review authorizes P2-007 or Phase 3.
