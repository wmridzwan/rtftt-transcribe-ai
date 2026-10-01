# REVIEW - P7-009-CORR-01 Corrective Cycle 1 - Independent Re-Review

## Review Status

**VERIFIED** (reviewer verdict only — not DONE; closure is a Human Product Owner act).

Task File: `tasks/P7-009-CORR-01-upload-transcription-initiation-bridge.md`
Binding authority: `DECISION-P7-009-CORR-01-UPLOAD-TRANSCRIPTION-BRIDGE-001`,
`DECISION-P7-009-CORR-01-CYCLE1-001`, and the previous independent review
`reviews/P7-009-CORR-01-INDEPENDENT-REVIEW.md` (VERIFIED; MEDIUM F-1, LOW F-2,
LOW F-3 accepted non-blocking).

Reviewer: Claude Code — a **fresh review session that did not implement
Corrective Cycle 1**. Independence is by independent reconstruction of evidence
(every verdict-bearing command below was re-run by the reviewer), per
`.ai/guidelines/ai-development-os.md`.

Date: 2026-10-02.

Evidence legend: **[R]** repository file/diff read by the reviewer;
**[E]** command executed by the reviewer in this session;
**[PG]** reasoning against PostgreSQL READ COMMITTED semantics — **not
executed** (no PostgreSQL exists in this environment); **[T]** theoretical
hardening only; **[I]** reported by the implementer and not relied on.

### Reviewer boundary

- No application code, test, governance record, task state, or VPS
  configuration was modified. Nothing was deployed or committed.
  `git status --short` listed the identical 16 paths before and after the
  review (the only addition is this file). Scratch probe lives outside the repo
  (scratchpad) and is not committed.
- P7-009 Phase B = NOT AUTHORIZED. P7-007 = NOT AUTHORIZED. P7-012 = NOT
  AUTHORIZED. This review authorizes no deployment.
- **Reviewer error, disclosed.** My first run of the scratch probe used
  `phpunit --no-configuration`, so `phpunit.xml`'s test environment
  (`sqlite :memory:`, `APP_ENV=testing`) was not loaded and `RefreshDatabase`
  ran `migrate:fresh` against the **local, gitignored dev database
  `database/database.sqlite`** (`.env`: `APP_ENV=local`, `DB_CONNECTION=sqlite`).
  Its prior contents were reset (file mtime 2026-10-02 01:44:59 matches the
  run). Post-state [E, read-only inspection]: 33 migrations applied (= 33
  migration files), 0 rows in `users`/`media_files`/`transcriptions`/
  `processing_jobs`/`jobs`/`sessions`, `freelist_count=0`, `page_count=65`. The
  zero freelist suggests the prior file was comparable to an empty migrated
  schema (little or no data), but I cannot prove what it held. Impact: a
  gitignored local dev artifact only — not tracked, not production, not the
  VPS, not a governance record, no repository file changed. If it held data you
  want, restore it from a backup; otherwise `php artisan migrate` is already in
  the fresh state. The probe was then re-run with the repo `phpunit.xml`
  (in-memory) and a fail-fast guard; the dev DB mtime was verified unchanged by
  that and every later command.

---

## 1. Baseline

- HEAD `83b666d` on `main`. Working tree [E `git status`]: 9 modified, 7
  untracked (listed below), unchanged by the review.
- `.ai/rules/` does **not** exist in this repository (CLAUDE.md: "continue
  without it").
- Governance read [R]: `CLAUDE.md`, the corrective task contract (incl. the
  "Corrective Cycle 1 — Implementation Notes"), the previous review, the
  `DECISIONS.md` / `DECISION_QUEUE.md` / `CURRENT_STATE.md` diffs,
  `TranscriptionStatus`, `TranscriptionLifecycle`, `MediaFilePolicy`,
  `TranscriptionPolicy`.
- **Scope check — PASS [E `git status`, R].** Not changed in the working tree:
  any migration, `composer.json`/`composer.lock`, `config/`,
  `TranscriptionOrchestrator`, `ProcessTranscription`, models, enums,
  policies, upload code, worker/provider/queue code. No schema change.

| Path | State | In previous review? |
|---|---|---|
| `app/Actions/TranscriptionInitiator.php` | new, 93 lines | yes — **unchanged** (93 lines, same as previously reviewed) |
| `routes/web.php` | modified, 13 lines | yes — **unchanged** (same +11/−2) |
| `app/Http/Controllers/DemoTranscriptionController.php` | modified (+3) | yes — unchanged |
| `app/Http/Controllers/MediaTranscriptionController.php` | new, 54 lines | **changed** (26 → 54: Throwable catch) |
| `app/Actions/TranscriptionRetry.php` | modified (+85) | **new in delta** |
| `app/Http/Controllers/TranscriptionController.php` | modified (+15) | **new in delta** |
| `resources/views/transcriptions/show.blade.php` | modified (+22/−1) | **new in delta** |
| `resources/views/livewire/media/show.blade.php` | modified (+38) | **changed** (+18 → +38: Resume control) |
| `tests/Feature/Transcription/TranscriptionInitiationBridgeTest.php` | new (26 tests) | yes — unchanged count |
| `tests/Feature/Transcription/TranscriptionCorrectiveCycle1Test.php` | new (16 tests) | **new in delta** |
| `tasks/P7-009-CORR-01-…md`, two pre-existing untracked review artifacts, `CURRENT_STATE.md`, `DECISIONS.md`, `DECISION_QUEUE.md` | governance | see §12 / RR-6 |

The "unchanged" determinations rest on line counts and diff-hunk comparison
against the previous artifact's own description, because nothing is committed.

---

## 2. Delta reviewed

1. **`MediaTranscriptionController::store`** (`:19-49`): `authorize('update')`
   first; `TranscriptionException` → redirect to Media Detail with the message;
   any other `Throwable` → `report()`, then look up the media's newest
   Draft/Queued transcription → redirect to it with an error flash, else
   redirect to Media Detail with a retry message. No 500.
2. **Resume control** — Media Detail (`livewire/media/show.blade.php:3-29,
   39-49`): `canStartTranscription` XOR `canResumeTranscription`; Resume =
   first Draft/Queued transcription + physical file + `can('update')`.
   Transcription Detail (`TranscriptionController.php:79-82`,
   `transcriptions/show.blade.php:424-436`): `resumeEligible` = status ∈
   {Draft, Queued} + media present + `can('update', mediaFile)`. Both forms POST
   to the **existing** `media.transcriptions.store` — no new route.
3. **`TranscriptionRetry::retry`** (`:111-201`): after the existing CAS and the
   new attempt insert, takes `MediaFile … lockForUpdate()` and refuses (throws,
   rolling the whole transaction back) if another Draft/Queued/Preparing/
   Transcribing transcription exists for the media. A pre-check
   (`isBlockedByActiveTranscription`, `:96-106,136`) gives the same answer
   before the transaction opens. `isEligible()` is unchanged (failure-based).
4. **Retry blocked UI** (`transcriptions/show.blade.php:406-407`,
   `TranscriptionController.php:62`): explanatory text instead of the button.

---

## 3. F-1 — queued-but-undispatched recovery

Scenario reproduced **without mocks** [E, probe P-A]: the `jobs` table was
renamed so the real `database` queue driver throws during
`PendingDispatch::__destruct` (confirmed in `vendor/.../PendingDispatch.php:295`:
the push happens in the destructor, after `Log::info('Transcription dispatched
to queue.')` in `TranscriptionOrchestrator::dispatch` — see INFO RR-7). The
`TranscriptionOrchestrator::request()` transaction had already committed.

| # | Required property | Result | Evidence |
|---|---|---|---|
| 1 | Stranded state recoverable via a normal supported product path | **PASS** | Start → redirect to the stranded transcription with an error flash [E P-A]; Resume visible on both Media Detail and Transcription Detail [E P-A, repo tests]; Resume re-dispatches [E P-A] |
| 2 | Reuses the same Transcription | **PASS** | `TranscriptionInitiator.php:70-79` returns the existing active row; `Transcription::count() == 1`, same id [E P-A, repo test] |
| 3 | Reuses the canonical active ProcessingJob | **PASS** | `TranscriptionOrchestrator.php:63-74` reuses the Queued/Running attempt; same attempt id, `ProcessingJob::count() == 1` [E] |
| 4 | No second MediaFile | **PASS** | Initiator only reads/locks `MediaFile`; never creates one [R]; media count unchanged in repo tests [E] |
| 5 | No uncontrolled duplicate active attempts | **PASS** | one active Transcription + one active attempt after N resumes [E P-A]; `processing_jobs_active_attempt_unique` partial index as backstop [R, prior review] |
| 6 | Invokes the existing orchestrator, no duplicated queue logic | **PASS** | sole call site `TranscriptionInitiator.php:89`; controller/views contain no queue or lifecycle logic [R] |
| 7 | Repeated recovery is idempotent | **PASS** | two further POSTs → still 1 Transcription / 1 attempt; messages are for the same attempt id [E P-A, repo test] |
| 8 | Authorization server-side | **PASS** | `authorize('update', $mediaFile)` before any state change (`MediaTranscriptionController.php:17`); hiding the button is cosmetic [R, E] |
| 9 | Non-owner cannot resume another user's transcription | **PASS** | non-owner POST → 403, nothing created/dispatched; non-owner GET of the transcription → 403 [E P-G, repo test]; admin may resume, rows stay owned by the owner [E P-G] |
| 10 | No unrecoverable generic 500 | **PASS for Start/Resume**; see RR-3 for the Retry endpoint | Start/Resume path returns a redirect [E P-A]. The **Retry** endpoint still returns 500 on a post-commit dispatch failure (pre-existing; `TranscriptionActionController::retry` catches only `TranscriptionException`) but the state it leaves (T=Queued, new attempt Queued) is now **recoverable** through Resume [E P-D] |
| 11 | Clear Resume action where meaningful | **PASS** | amber panel on Transcription Detail; "Resume Transcription" button on Media Detail; Draft and Queued both covered [E repo tests] |
| 12 | Preparing/Transcribing not exposed as resumable | **PASS** | view logic is `[Draft, Queued]` only [R]; parameterized test asserts no Resume for Preparing/Transcribing/Completed/Failed/Cancelled on both pages [E] |

**Ambiguity — dispatch actually succeeded but the caller saw an
exception/timeout; Resume then pushes a second message.** Verified harmless
[E P-A, two real `queue:work --once` runs on the `database` queue]: after two
messages for the *same* attempt, the recording provider was invoked
**exactly once**; Transcription=Completed, attempt=Completed, 4 segments, one
Transcription, one ProcessingJob. Mechanism [R `ProcessTranscription.php:95-125,
247-264`]: terminal-status skips, `hasNewerAttempt` skip, and the atomic
`UPDATE processing_jobs SET status='running' WHERE id=? AND status='queued'`
claim — the loser sees zero affected rows and returns without work. Existing
suites exercise the same fences (`duplicate delivery does not duplicate
inference…`, `an attempt already claimed by another worker is not processed`,
`a stale attempt is ignored…`) and pass [E].

A Resume direct-POSTed for a **Running** transcription is also a no-op: the
initiator reuses it, the orchestrator reuses the Running attempt and enqueues a
message that fails the claim [R].

---

## 4. F-2 — Retry versus an active transcription (PostgreSQL, READ COMMITTED)

### 4.1 Statement orders

Start (`TranscriptionInitiator::start`, then `request()` in its own tx):

```
BEGIN
 S1  SELECT * FROM media_files WHERE id=M LIMIT 1 FOR UPDATE
 S2  SELECT * FROM transcriptions WHERE media_file_id=M AND status IN (draft,queued,preparing,transcribing) ORDER BY id DESC LIMIT 1
 S3  INSERT INTO transcriptions (… 'draft')              -- only if S2 returned nothing
COMMIT                                                   -- then request(T): SELECT T FOR UPDATE; [draft→queued]; reuse/INSERT attempt
```

Retry (`TranscriptionRetry::retry`):

```
(pre)  eligibility reads; pre-check EXISTS(other active for M)     -- best-effort, outside the tx
BEGIN
 R1  UPDATE transcriptions SET status='queued',… WHERE id=T1 AND status='failed'   -- CAS; row lock on T1
 R2  INSERT INTO processing_jobs (T1, 'queued')
 R3  SELECT * FROM media_files WHERE id=M LIMIT 1 FOR UPDATE                      -- shared mutex with Start
 R4  SELECT EXISTS(… media_file_id=M AND id<>T1 AND status IN (active))           -- new snapshot per statement
 R5  if exists: throw → ROLLBACK (R1, R2 undone together)  else COMMIT
(post) dispatch
```

### 4.2 Required properties — proved [PG]

| Property | Verdict | Basis |
|---|---|---|
| The `media_files` row is the shared serialization point for Start **and** Retry | **TRUE** | S1 and R3 are both `FOR UPDATE` on row M (`TranscriptionInitiator.php:58-61`; `TranscriptionRetry.php:167-170`) |
| The competing-active check occurs while that lock is held | **TRUE** | R4 follows R3 in the same transaction; the closure does not release the lock before commit/rollback |
| Under READ COMMITTED R4 sees work committed by a transaction that held M first | **TRUE** | R3 returns only after the holder commits/rolls back; R4 is a *new statement* and takes a new snapshot. `config/database.php` sets no isolation override and a grep for `isolation` / `default_transaction` / `READ COMMITTED` / `REPEATABLE` / `SERIALIZABLE` over `app/`, `config/`, `bootstrap/`, `database/`, `routes/` finds no override (the only hit is the Retry docblock; same assumption the previous review recorded — the target host's `SHOW default_transaction_isolation` is on the §14 checklist) |
| Refusal rolls back the CAS and the new attempt atomically | **TRUE** | R5 throws inside `DatabaseManager::transaction`, which rolls back and rethrows. Executed on one connection: `retry rolls back its state change and attempt when competing work appears before the media lock` [E]; the four-status probe [E P-C] shows T1 stays `Failed` with one attempt and nothing queued |
| Other active transcription excluded/included correctly | **TRUE** | `whereKeyNot(T1)` + `whereIn(status, Draft, Queued, Preparing, Transcribing)`; the retrying transcription is `Failed` (terminal) so it never matches. Different-media active work does not block [E P-I]; Cancelled/Completed competitors do not block [E P-H, repo test] |
| Terminal-status definition matches the canonical lifecycle | **TRUE** | `TranscriptionStatus` has exactly 7 cases: active = {Draft, Queued, Preparing, Transcribing}; terminal = {Completed, Failed, Cancelled} — the same split used by `TranscriptionOrchestrator.php:42-46`, `ProcessTranscription.php:95-99`, `TranscriptionLifecycle::VALID_TRANSITIONS`, and the initiator's own `ACTIVE_STATUSES` |
| Retry remains valid when no competitor exists | **TRUE** | `retry queues a new attempt normally…` and `retry becomes available again once the competing transcription is terminal` [E] |

### 4.3 Interleavings (S, R as above; M = media row; T1 = Failed)

**Case A — Start begins first.** S1 acquires M. R may run R1/R2 (T1 locked,
uncommitted `queued`) and then blocks at R3. S2 does not see T1 as active
(uncommitted CAS is invisible to S), finds nothing, S3 inserts T2 (Draft),
COMMIT. R3 returns; R4 sees T2 → R5 rolls back. **End state: T2 active alone;
T1 still Failed; no second attempt.** If Start commits before R's pre-check,
the pre-check refuses earlier; if between the pre-check and R1, R4 still
catches it. *No duplicate.*

**Case B — Retry begins first and wins M.** R1–R3 complete, R4 finds nothing,
COMMIT. S1 (blocked) then acquires M; S2 sees T1 `queued` (committed) → reuses
T1; `request(T1)` reuses R's attempt. **End state: T1 active, one attempt**
(two queue messages, same attempt — F-3). *No duplicate.*

**Case C — Retry has done the CAS but not yet the media lock; Start holds M.**
This is the case that makes the *guard placement* load-bearing: S cannot see T1
as active, so S **will** create T2. Only R4-after-R3 prevents two active
transcriptions, and it does: R4 runs after S has committed T2, observes it, and
rolls back R1/R2. **End state: T2 alone.** *No duplicate, no committed CAS.*
The reviewer was asked not to assume the CAS-then-lock order is right: it is
right *because* the check sits behind the lock, independent of how far R got.

**Case D — two different Failed transcriptions T1, T2 of one media retried
concurrently.** R_a and R_b both complete R1/R2 (different rows, no conflict)
and queue at R3; one (say R_a) wins M. R_a's R4 sees T2 as `failed` (R_b's CAS
is uncommitted) and T1 excluded → no competitor → COMMIT. R_b then acquires M;
R4 sees T1 `queued` → throws → rolls back R_b's CAS and attempt. **End state:
exactly one of T1/T2 active.** R4 is a plain read, so R_a never waits on R_b's
T2 row lock. *No duplicate, no deadlock, no lost retry*: R_b's retry is
refused only because a legitimate competing retry exists, and is available once
T1 is terminal.

**Case E — two Starts (S, S') and one Retry (R).** M is the single mutex, so
the *first holder decides* and every later holder reads committed state.
  - R first: T1 reused by S and S'; one active, one attempt.
  - S first: S creates T2; S' (next) reuses T2; R refuses whether it takes M
    before or after S' (R4 sees T2 either way). One active.
  - R holding its CAS while S holds M: as Case C.
**No ordering yields two active Transcriptions or two active attempts.**

**Further orderings examined.** Newer active transcription completes before
Retry obtains M: R4 sees it terminal → retry proceeds → T1 sole active. Retry
refused at the pre-check and the competitor then finishes: the user retries
again (deterministic, contract-compliant). Same-transcription double retry:
second CAS matches zero rows and converges on the winner's attempt — covered by
the existing two-process SQLite harness, which still passes with the new code
[E].

### 4.4 Deadlock analysis

Wait-for edges: Retry holds **T1** and waits for **M**. Start holds **M** and
waits for nothing (S2 is a plain read; S3 inserts a new row; the FK key-share
locks on `media_files`/`users` are self-compatible with S1's `FOR UPDATE`).
`TranscriptionOrchestrator::request`, `ProcessTranscription`, and
`TranscriptionResultWriter` lock only transcription/attempt rows;
`RetentionPurge` locks only M (`RetentionPurge.php:133-155`). Hence **no cycle
among Start, Retry, orchestrator, worker, and purge** (Cases A–E).

**One additional cycle exists — see RR-2 (LOW).** The Retry docblock states
"nothing takes the media lock and then waits on a transcription row". Deleting a
media file does exactly that: `transcriptions.media_file_id` is
`cascadeOnDelete()` (`2026_09_09_000003_create_transcriptions_table.php:14`), so
`DELETE FROM media_files` (`MediaActionController::destroy`,
`Livewire\Media\Show::delete`) takes M and then cascades to T1.

### 4.5 Sufficiency statement

**The media-row-lock design is sufficient for the authorized production paths**
(Start by owner/admin, and Retry), on PostgreSQL READ COMMITTED, to guarantee
that Start and Retry never produce two simultaneous active Transcriptions or two
simultaneous active ProcessingJobs for one media file, never commit a CAS that
should have been refused, and never lose a legitimate retry. **A database unique
constraint is not required**, and none was demanded or authorized. The
CAS-then-lock order is correct. (Taking the lock before the CAS would also be
correct on PostgreSQL and would remove the RR-2 cycle; the implementer measured
it failing `TranscriptionRetryConcurrencyTest` on SQLite with a
snapshot-upgrade BUSY — a SQLite-only artifact. Whether to trade that for RR-2
is an HPO/owner call, not a defect.)

**Evidence class:** §4.2–4.5 are **[PG] reasoning, not execution.**
`lockForUpdate()` is a no-op on SQLite (ADR-013), there is no PostgreSQL, `psql`,
or Docker here (ports 5432/5433 closed [E]), and no existing multi-process
harness can exercise a media-row lock on SQLite. What **was** executed: the
decision logic and atomic rollback on one connection (repo test, probe P-C), the
existing two-process SQLite Retry∥Retry same-transcription CAS harness with the
new code, and the four-status refusal matrix.

---

## 5. Failure semantics (executed unless marked)

| Scenario | Outcome | Deterministic / contract-compliant |
|---|---|---|
| Dispatch throws before any message is accepted | Rows committed Queued/Queued; Start redirects to the transcription with an error flash; Resume re-dispatches the same rows [E P-A real driver failure; repo test with mocked `dispatch`] | Yes |
| Dispatch succeeded but caller saw failure; Resume | Second message, same attempt; claim fence skips it; provider once [E P-A, two real workers] | Yes |
| Resume clicked repeatedly | N messages, one Transcription, one attempt [E] | Yes (F-3) |
| Media disappears between Start and worker | Worker fails the attempt `MediaMissing` (Failed; retry per taxonomy) [R `ProcessTranscription.php:146-150`]. If it is purged while stranded, Resume is refused with a flash, no rows, no dispatch [E P-E]; retention only selects `Completed` transcriptions [R `RetentionPurge.php:53-56`] | Yes (INFO RR-4) |
| Active transcription becomes terminal between render and **Resume/Start** POST | A **new** Transcription is created (all-terminal ⇒ deliberate re-transcription, contract §6) [E P-B: 2 transcriptions] | Deterministic and §6-compliant, but see **RR-1** |
| Same, for **Retry** | Refused if a competitor is still active at the lock; allowed if it is terminal at the lock [PG] | Yes |
| Old Failed retried while newer active work exists | Refused at pre-check and again authoritatively; CAS rolled back [E, 4 statuses] | Yes |
| Newer active completes before Retry takes M | R4 sees terminal; retry proceeds, one active [PG] | Yes |
| Retry's own post-commit dispatch fails | HTTP 500 (pre-existing); T1=Queued + new attempt Queued; Resume recovers on the same rows [E P-D] | Recoverable (RR-3) |

---

## 6. UI review (rendered logic, server enforcement checked separately)

| State | Media Detail | Transcription Detail | Server-side, independent of visibility |
|---|---|---|---|
| no transcription / all terminal | **Start** (if bytes + status ∈ Uploaded/Ready + `can('update')`) | — | initiator re-checks processability under the media lock |
| Draft / Queued | **Resume** (if bytes + `can('update')`); Start hidden | **Resume** panel | idempotent re-dispatch of existing rows |
| Preparing / Transcribing | neither | neither | direct POST is a safe no-op (claim fence) |
| Completed / Cancelled | Start (re-transcribe) | none | — |
| Failed, retryable, no competitor | Start | **Retry** | CAS + media lock + competitor check |
| Failed, retryable, competitor active | neither (competitor is active) | explanatory text, no button | retry refused server-side [E P-C] |
| Failed, not retryable | Start | "This failure is not retryable." | `isEligible()` unchanged |

Start and Resume are mutually exclusive by construction (`canStart` requires no
active transcription; `canResume` requires a Draft/Queued one) [R]. No state
shows contradictory actions. Authorization and concurrency protection are
enforced in the controller/action, never by hiding a button [E P-G, P-C].

---

## 7. F-3 confirmation

Unchanged and still non-blocking. Duplicate messages for one attempt are
produced by repeated Start/Resume/Retry-then-Start, and by Resume of a healthy
Queued attempt (the implementer disclosed there is no `dispatched_at` to
distinguish "waiting" from "never dispatched" without a schema change). All
assumptions hold [E P-A + existing suites]: (1) same attempt id; (2) later
messages no-op through terminal checks and the atomic claim; (3) no duplicate
persistence (provider once, segments once); (4) no concurrent inference (only
one claimant per attempt). No fix is requested.

---

## 8. Regression of the original bridge

All re-confirmed [E 26/26 bridge suite + related suites + probe]: real
uploaded `MediaFile` reused (count unchanged); upload alone does not
transcribe; Start is explicit; a real Transcription is created (Draft→Queued);
`TranscriptionOrchestrator::request()` remains the single authority;
ProcessingJob canonical; `ProcessTranscription` goes through the configured
queue (`Queue::assertPushedOn('transcription', …)`; a real worker consumed it
from the `database` queue in P-A); owner OK / non-owner 403 / admin allowed /
guest → login; demo path unavailable in production (routes unregistered,
controller 404 guard — files unchanged since the previous review); later
re-transcription after all-terminal still creates a new Transcription; the
real-pipeline test (fake provider) still completes; no schema change; no
worker/provider redesign.

---

## 9. Test evidence

Environment: PHP 8.4.24 (Herd, Windows), SQLite `:memory:`,
`QUEUE_CONNECTION=sync`, `memory_limit=2G`. **No PostgreSQL / Docker** (ports
5432 and 5433 closed [E]; `pdo_pgsql` present but unusable).

| Command (reviewer-run) [E] | Result |
|---|---|
| `pest tests/Feature/Transcription/TranscriptionCorrectiveCycle1Test.php` | 16 passed / 86 assertions |
| `pest tests/Feature/Transcription/TranscriptionInitiationBridgeTest.php` | 26 passed / 125 assertions |
| `pest tests/Feature/Transcription` (full dir, includes the two-process SQLite retry harness) | **162 passed** / 699 assertions |
| Related: Authorization, DemoTranscription, FolderAuthorization, MediaIngestion, MediaManagement, MediaUploadContract, Ownership, PageRender, TranscriptionDetail, TranscriptionManagement, `Feature/Queue`, `Feature/Retention`, `Unit/Transcription` | 225 tests, 222 passed, 3 skipped (`RedisQueueIntegrationTest`: Redis unreachable), 672 assertions |
| Full `pest` | **1295 tests, 1290 passed, 5 skipped, 0 failures**, 5155 assertions, 4 PHPUnit warnings with empty detail (same count as the previous review). Skips: 3 Redis queue integration, 1 `RedisPostureTest`, 1 FFprobe — all environment-gated, identical count to the previous review |
| `pint --test` — changed PHP files, and repo-wide | passed |
| `phpstan analyse` (repo config) | 0 errors |
| Reviewer scratch probe (PHPUnit class outside the repo, repo `phpunit.xml`) | 12 passed / 78 assertions |

**Baseline discrepancy (not a defect).** The review request cited the
implementer's baseline as `tests/Feature/Transcription` 151/151 and full suite
1284 / 1279 passed. I measured **162/162** and **1295 / 1290**. 1295 − 16
(new Cycle 1 tests) = 1279, the previous review's total, and 1295/1290 equals
the figure in the task file's own implementation notes, so the request's
numbers appear to be stale/mistranscribed; my reproduced numbers are the
evidence. No failure anywhere. The `LogContextTest` random-factory flake (TD-008)
recorded by the previous review did not fail in this run; it remains pre-existing
and unrelated.

**Probe (scratch, not committed) — what it adds beyond the shipped tests:**
P-A real (unmocked) queue-driver failure → redirect → Resume → duplicate
message → two real workers → provider once; P-B stale Resume; P-C retry
refused for Draft/Queued/Preparing/Transcribing; P-D Retry-originated dispatch
failure → Resume recovers; P-E purged media; P-F media status Processing;
P-G admin resume and non-owner 403s; P-H Cancelled competitor does not block;
P-I different-media active work does not block.

**Test adequacy.** Strong: the stranded-state flow, idempotent Resume,
ownership, retry refusal/rollback, view logic. Gaps (none violates an AC): the
repo dispatch-failure test mocks `dispatch()` rather than a real driver (my
P-A covers that); retry refusal is only parameterized over Queued/Draft in-repo
(P-C covers all four); no test for stale Resume (RR-1); no executable
concurrency test (cannot exist on SQLite).

---

## 10. Acceptance criteria AC1–AC14 (after the corrective delta)

| AC | Requirement | Result | Note |
|---|---|---|---|
| 1 | Real MediaFile reused; no fake/duplicate media | **PASS** | initiator unchanged; counts asserted [E] |
| 2 | Upload alone does not dispatch; explicit action exists | **PASS** | upload untouched; Start/Resume explicit [E] |
| 3 | Real Transcription, authorized user + media, canonical defaults | **PASS** | initiator unchanged [R, E] |
| 4 | Uses `TranscriptionOrchestrator::request()` | **PASS** | sole call site `TranscriptionInitiator.php:89`; Resume goes through it [R, E] |
| 5 | ProcessingJob Queued created/reused | **PASS** | [E] |
| 6 | `ProcessTranscription` on the configured queue; no sync inference | **PASS** | queue-name assertions [E]; real `database` worker run in P-A; production sync prohibition per D7-02 unchanged |
| 7 | Authorization: owner ok, non-owner 403, admin per policy | **PASS** | server-side for Start, Resume, Retry [E P-G] |
| 8 | Double submit safe; re-transcription later allowed | **PASS** | now also covers Resume and Retry∥Start (concurrency by [PG] reasoning) — note RR-1 |
| 9 | Non-processable states rejected safely | **PASS** | [E, plus P-E/P-F] |
| 10 | Demo isolation in production | **PASS** | files unchanged since previous review; tests green [E] |
| 11 | Real pipeline integration with fake provider | **PASS** | [E] |
| 12 | Upload contract unchanged | **PASS** | upload/ingestion suites green [E] |
| 13 | Regression | **PASS** | §9 |
| 14 | Post-deployment production-candidate verification: real upload → Start Transcription → Queued → Running → Completed with visible output | **NOT YET EVIDENCED** | target-host only, post-deployment; not simulated, not claimed. The in-process end-to-end test and P-A are not substitutes |

---

## 11. Findings

No BLOCKER. No HIGH. No MEDIUM. Two LOW, several INFO.

### RR-1 — LOW — A "Resume" on a stale page after the transcription finished starts a *new* transcription
- Location: `MediaTranscriptionController::store` / `TranscriptionInitiator::start` (the endpoint carries no intended-transcription identity); the two Resume forms (`livewire/media/show.blade.php:44-48`, `transcriptions/show.blade.php:432-435`).
- Evidence: probe P-B [E] — a Queued transcription completes after the page rendered; POST of the Resume form creates a second Transcription (count 2) for the media. The detail pages do not poll (no `wire:poll`/`setInterval`/meta refresh found [R]), so a stale page is the normal case until reload; the panel says "If it stays here, resume dispatch."
- Consequence: a duplicate full inference and an unintended extra transcript. One explicit click, one new transcription, visible redirect to a Queued transcription — never uncontrolled or concurrent. It is contract-§6-compliant (all prior transcriptions terminal ⇒ deliberate re-transcription) and not a regression of any AC.
- Blocks commit/deployment preparation? **No.** Optional hardening (code only, no schema): include the intended transcription id in the Resume form and have the server redirect to that transcription instead of creating a new one when it is no longer active.

### RR-2 — LOW — Lock-order inversion between Retry (T→M) and media delete (M→T via FK cascade)
- Location: `app/Actions/TranscriptionRetry.php:140-177` (and docblock `:34-38`); `database/migrations/2026_09_09_000003_create_transcriptions_table.php:14` (`cascadeOnDelete`); `MediaActionController::destroy`, `Livewire\Media\Show::delete`.
- Evidence **[PG], not executed:** R holds T1 (CAS) and requests M `FOR UPDATE`; a concurrent `DELETE FROM media_files WHERE id=M` has locked M and its cascade then needs T1 → mutual wait → PostgreSQL's deadlock detector (default `deadlock_timeout` 1 s) aborts one with SQLSTATE 40P01. Window ≈ the milliseconds between R's CAS and R's M-lock request. The docblock's "no lock cycle exists" is accurate for Start/orchestrator/worker/purge but not for media delete.
- Consequence: at most one transient HTTP 500, atomically rolled back — no corruption, no duplicate, no unrecoverable state. If the delete is the victim, the file bytes are already removed (pre-existing ordering in `destroy`: storage delete before DB delete), the row remains, and the user can re-run delete. Requires the same owner/admin to Retry and Delete one media within ~ms.
- Blocks? **No.** Options: accept the residual; or take the media lock *before* the CAS (consistent M-first order; the implementer found it conflicts with a SQLite-only snapshot-upgrade in `TranscriptionRetryConcurrencyTest`, so it would need that harness reconsidered); correct the docblock either way.

### RR-3 — INFO — Retry endpoint still returns a generic 500 on post-commit dispatch failure
- Location: `TranscriptionActionController::retry` catches only `TranscriptionException`. Pre-existing, outside Cycle 1's stated scope (F-1 was Start). State is recoverable via Resume [E P-D]. Align with the Start controller in a later cycle if desired.

### RR-4 — INFO — Resume visibility is looser than the server's processable check
- Transcription Detail offers Resume without checking media presence/status; Media Detail offers Resume with bytes present but any media status, whereas Start requires Uploaded/Ready. The server refuses safely with a flash and no rows/dispatch [E P-E, P-F]. Practically unreachable: production only writes `Uploaded`, and retention selects only `Completed` transcriptions.

### RR-5 — INFO — Resume is offered for a healthy Queued attempt
- Disclosed by the implementer; no `dispatched_at` exists to tell "waiting" from "never dispatched". Harmless (F-3 fence); copy is hedged.

### RR-6 — INFO — Governance records lag the implementation
- `DECISION-P7-009-CORR-01-CYCLE1-001` is cited in the task file, tests and code comments but is **not** present in `DECISIONS.md` / `DECISION_QUEUE.md`; `CURRENT_STATE.md` still describes the task as "REVIEW — … awaiting independent review" with no Cycle 1 mention. The AI-OS requires decisions to live in the repo, not only in chat. Not a code defect; recommend the owning process records the decision and this verdict in the same change/closure. (The HPO's chat instruction is itself binding per the source-of-truth order.)

### RR-7 — INFO — Pre-existing: "dispatched to queue" is logged before the push
- `TranscriptionOrchestrator::dispatch` logs `Transcription dispatched to queue.` and only then lets the `PendingDispatch` destructor push, so on a failed push the log line is misleading (the exception does propagate — P-A proves the catch works). Orchestrator is protected scope and unchanged; no action requested.

### RR-8 — INFO — Carried over, unchanged
- The previous review's F-4 (orchestrator checks terminal status on the caller's stale model — a sub-millisecond window between initiator commit and orchestrator transaction; Resume does not widen it), F-5 (concurrency reasoned, not executed — still true, now also for Retry), F-6 (optional partial unique index — not required; if ever adopted, Retry would need explicit constraint-violation handling), F-9 (route-cache env dependency), F-10 (cosmetic flash).
- Maintainability: the active-status list is now repeated in the initiator, retry, the controller, and two view fragments; identical today.

---

## 12. Architecture, security, governance summary

- **Architecture:** compliant. Orchestrator still the single owner of Draft→Queued, attempt creation/reuse and dispatch; Retry reuses the orchestrator's `dispatch()`; no queue logic in controllers/views; no new route.
- **Security:** server-side `authorize('update')` precedes any state change on Start/Resume; Retry authorizes on the transcription; CSRF on both forms; no new client-supplied parameters; no new data exposure.
- **Governance state after this review:** task contract still states "Cycle 1 requires a fresh independent re-review" — this is it. `CURRENT_STATE.md`, `DECISIONS.md`, `DECISION_QUEUE.md`, the task file Status, `plan.md` were **not modified by this review**; reconciling them is left to the owning process (RR-6).

---

## 13. Required changes

None required for VERIFIED. HPO-discretion items: RR-1 (recommended small hardening), RR-2 (accept or reorder), RR-3, RR-6 (record the decision).

## 14. Additions to the AC14 / target-host checklist (all PostgreSQL-only, none executed here)

1. Real upload → Start → Queued → Running → Completed with visible transcript (AC14 proper).
2. Two parallel Start POSTs → 1 Transcription, 1 active attempt; `SHOW default_transaction_isolation` = `read committed`.
3. **Start ∥ Retry** on a Failed transcription: exactly one active transcription afterward (Cases A–C).
4. **Two Failed transcriptions of one media retried concurrently:** exactly one becomes active (Case D).
5. Controlled dispatch-failure drill (stop Redis briefly): Start → redirect with guidance; restore Redis; Resume → completes; no duplicate rows.
6. Optional: Retry ∥ media delete to observe how a 40P01 surfaces (RR-2).
7. `GET /transcriptions/create` redirects to `/media/upload`; `POST /transcriptions` not routable.

Then the Target-Host Readiness Confirmation is re-run; only `TARGET_HOST_READY` lets the HPO separately authorize Phase B.

---

## 15. Reviewer Conclusion

**VERIFIED.**

- **Corrective Cycle 1 resolves F-1 and F-2.** F-1: the stranded Queued/Draft state is recoverable through a supported, authorized, idempotent product path that reuses the same Transcription and the same ProcessingJob and invokes the existing orchestrator; the Start path no longer returns an unrecoverable 500. F-2: Retry now serializes with Start on the media row lock and refuses (with atomic rollback) when another non-terminal transcription exists; the guard placement is correct under READ COMMITTED for Cases A–E.
- AC1–AC13: PASS. **AC14: NOT YET EVIDENCED** (post-deployment, target-host-only).
- **No remaining finding blocks commit or deployment preparation.** RR-1 and RR-2 (LOW) and RR-3…RR-8 (INFO) are documented for the HPO.
- The media-row-lock concurrency guarantee is **sufficient for the authorized production paths** and is established by **PostgreSQL reasoning, not by an executed PostgreSQL run.**
- P7-009 Phase B = **NOT AUTHORIZED**. P7-007 restore drill = **NOT AUTHORIZED**. P7-012 = **NOT AUTHORIZED** (FINAL_GATE_ONLY).
- VERIFIED does not mean DONE; only the Human Product Owner closes the task. This review does not authorize deployment, a commit, or any production action.
