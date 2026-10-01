# REVIEW - P7-009-CORR-01 - Real Upload → Transcription Initiation Bridge

## Review Status

**VERIFIED** (reviewer verdict only — not DONE; closure is a Human Product Owner act).

Task File: `tasks/P7-009-CORR-01-upload-transcription-initiation-bridge.md`
Binding authority: `DECISION-P7-009-CORR-01-UPLOAD-TRANSCRIPTION-BRIDGE-001`
(HPO, 2026-10-01) and the reconciled task contract above.

Implementation Owner: Claude Code (role changed explicitly for this task by the
HPO, per the task file).
Reviewer: Claude Code — a **fresh review session that did not implement this
task**. Independence is by independent reconstruction of evidence (every
verdict-bearing command below was re-run by the reviewer), not by vendor or
model identity, per `.ai/guidelines/ai-development-os.md`.

Date: 2026-10-02.

Reviewer boundary kept: no production code, test, governance record, task
state, or VPS configuration was modified; nothing was deployed or committed.
`git status --short` listed the same 11 paths before and after the review
(only this file is added by the review). P7-009 Phase B = NOT AUTHORIZED;
P7-012 = NOT AUTHORIZED; P7-007 restore drill = NOT AUTHORIZED.

Evidence legend: **[R]** repository file/diff read by the reviewer;
**[E]** command executed by the reviewer in this session; **[I]** reported by
the implementer in the task file and not relied on; **[CI]** none exists for
this uncommitted change set.

---

## 1. Baseline

- HEAD `83b666d` on `main` ("docs: reconcile canonical project governance
  state"). Working tree holds exactly the corrective change set [E `git status`]:

  | Path | State | Role |
  |---|---|---|
  | `app/Actions/TranscriptionInitiator.php` | new (93 lines) | initiation action |
  | `app/Http/Controllers/MediaTranscriptionController.php` | new (26 lines) | controller |
  | `routes/web.php` | modified (+11/−2) | new route; demo routes env-conditional |
  | `app/Http/Controllers/DemoTranscriptionController.php` | modified (+3) | production 404 guard |
  | `resources/views/livewire/media/show.blade.php` | modified (+18) | Start Transcription control |
  | `tests/Feature/Transcription/TranscriptionInitiationBridgeTest.php` | new (26 tests) | tests |
  | `tasks/P7-009-CORR-01-…md` | new | contract |
  | `CURRENT_STATE.md`, `DECISIONS.md`, `DECISION_QUEUE.md` | modified | governance records |
  | `reviews/P7-009-TARGET-HOST-READINESS-CONFIRMATION-002.md` | untracked | the prior 2026-09-30 review that raised H-1; **not** part of the implementation |

- **Scope check — PASS.** No migration, no `composer.json`/lock change, no
  worker/provider/queue/config change, no upload-path change (`MediaUploadController`,
  `MediaIngestionService` untouched), no orchestrator/job/policy/enum change
  [E `git diff --stat`, `git status`]. Every changed production line is inside
  the contract §3 scope.
- Governance read [R]: `AGENTS.md`, `.ai/guidelines/ai-development-os.md`,
  `.ai/guidelines/orchestration-policy.md`, `CURRENT_STATE.md`, `plan.md`,
  `RTFTT-MASTER-ROADMAP.md`, `DECISIONS.md` (incl. ADR-013, ADR-026/D7-01..02),
  `DECISION_QUEUE.md`, the corrective task contract, and
  `reviews/P7-009-TARGET-HOST-READINESS-CONFIRMATION-002.md` (finding H-1 / M-2).
  `.ai/rules/` does **not** exist in this repository, so no area rules apply
  (CLAUDE.md: "continue without it").
- Governance state in the diff [R]: all three governance records state
  P7-009 Phase B NOT AUTHORIZED, P7-007 drill NOT AUTHORIZED, P7-012
  FINAL_GATE_ONLY NOT AUTHORIZED, and that VERIFIED does not authorize
  deployment. `plan.md`, `RTFTT-MASTER-ROADMAP.md`, and `AGENTS.md` are
  unchanged and remain accurate (P7-009 IN_PROGRESS Phase A only).

---

## 2. Diff reviewed (actual code, not the implementer's summary)

**`TranscriptionInitiator::start()`** (`app/Actions/TranscriptionInitiator.php:55-92`)
inside `DatabaseManager::transaction`: `MediaFile::whereKey()->lockForUpdate()->first()`
(58-61) → processable check (63) → look up an existing transcription for that
media with status ∈ {Draft, Queued, Preparing, Transcribing} (70-77) → else
`Transcription::create` (user_id = media owner, media_file_id, title =
display_name, language = null, model = `config('transcription.model')`,
status = Draft) (79-86). After commit it calls
`TranscriptionOrchestrator::request()` (89) and returns `refresh()`.

**`MediaTranscriptionController::store`**: `authorize('update', $mediaFile)`,
delegate to the initiator, catch `TranscriptionException` → redirect to
`media.show` with `error`; else redirect to `transcriptions.show`. No queue or
lifecycle logic.

**Routes** (`routes/web.php:34-40, 69`): inside the existing `auth`+`verified`
group; production → `Route::redirect('/transcriptions/create','/media/upload')`
(name kept), non-production → the Phase 1 demo GET/POST routes;
`POST /media/{mediaFile}/transcriptions` → `media.transcriptions.store`.

**Demo controller** (`DemoTranscriptionController.php:23`):
`abort_if(app()->environment('production'), 404)`.

**View** (`show.blade.php:3-14, 24-29`): control shown only when physical bytes
exist, no active transcription, media status ∈ {Uploaded, Ready}, and
`can('update')`; a POST form with CSRF; client-side double-click disable
(cosmetic).

---

## 3. Concurrency / idempotency analysis (highest priority)

Target store is PostgreSQL (D7-01 = Option B; `.env.example` and the readiness
review show pg 16 on the target host). Default PG isolation is READ COMMITTED
and `config/database.php` sets no override [R].

### 3.1 Claim-by-claim verification

| # | Claim / check | Result | Evidence |
|---|---|---|---|
| 1 | Media row lock is taken inside a DB transaction | **TRUE** | `database->transaction(function…)` wraps the `lockForUpdate()->first()`; the lock is released only at commit/rollback (`TranscriptionInitiator.php:57-87`) |
| 2 | Existing-active lookup happens only after the lock | **TRUE** | the lock query is fully evaluated (`->first()`) at 58-61 before the lookup at 70-77 in the same closure |
| 3 | Creation is in the same serialized critical section | **TRUE** | `Transcription::create` at 79-86 is the closure's return expression, same transaction |
| 4 | Concurrent PG requests cannot both create separate active Transcriptions via this path | **TRUE under READ COMMITTED** (see 3.2); **NOT executed** — no PostgreSQL in the review environment (F-5) | reasoned from PG semantics |
| 5 | Orchestrator cannot create duplicate active ProcessingJobs | **TRUE** | `request()` locks the transcription row, reuses an existing Queued/Running attempt (`TranscriptionOrchestrator.php:53-83`); defense-in-depth: pg partial unique index `processing_jobs_active_attempt_unique` (`2026_09_26_130000_add_pg_partial_unique_indexes.php`) |
| 6 | No unrecoverable state if dispatch fails after commit | **PARTIAL** — recoverable only by direct resubmission, not via the UI (F-1) | probe P2 [E] |
| 7 | "Non-terminal" set matches the canonical lifecycle | **TRUE** | `TranscriptionStatus` has 7 cases = {Draft, Queued, Preparing, Transcribing} active + {Completed, Failed, Cancelled} terminal; `TranscriptionLifecycle::VALID_TRANSITIONS` and `StaleTranscriptionAttemptRecovery` agree (Failed re-enters only through `TranscriptionRetry`) |
| 8 | Admin and owner initiation share the same serialization | **TRUE** | one controller/initiator path; `user_id` is always the media owner (probe P5 [E]: admin-initiated row `user_id` = owner) |
| 9 | No other production-facing path creates a competing active transcription for the same media | **TRUE for creation; FALSE for reactivation** — `TranscriptionRetry` can reactivate an earlier Failed transcription without the media lock or a per-media check (F-2) | grep of `app/`: other `Transcription::create/factory` sites are verification commands, the capacity harness (`WorkloadRunner`), the seeder, and the demo controller (own fabricated media); probe P3 [E] |

### 3.2 Why the row lock is sufficient for the authorized path (PostgreSQL)

Two Start requests A, B for media M with no active transcription:

1. A: `BEGIN; SELECT … FROM media_files WHERE id=M FOR UPDATE` → acquires lock.
   B: same statement → blocks.
2. A: finds no active row, `INSERT` T1 (Draft), `COMMIT` → lock released.
3. B: unblocks. In READ COMMITTED every statement takes a fresh snapshot, so
   B's next `SELECT … FROM transcriptions WHERE media_file_id=M AND status IN (active)`
   sees A's committed T1 → B reuses T1. One Transcription.
4. Both then call `orchestrator->request(T1)`. That method locks the
   transcription row and reuses any active attempt, so either ordering yields
   exactly one `ProcessingJob` (and the pg unique index would reject a second
   active one).

`lockForUpdate()` is a real row lock on PostgreSQL. ADR-013 and the
`TranscriptionRetry` docblock record that it is a **no-op on SQLite**, which is
why those paths used compare-and-set; the corrective task correctly scopes its
guarantee to PostgreSQL ("SQLite … race only truly exercised on PostgreSQL",
contract §6). On SQLite writers are serialized coarsely, so a duplicate cannot
arise there either (a loser would get a busy error, not a duplicate).

**Assumption that must hold (and is not asserted anywhere): isolation level.**
Under REPEATABLE READ the loser's snapshot is taken at its first statement
(the blocked `FOR UPDATE`), the winner only *locks* (does not update) the media
row so no serialization error is raised, and the loser would not see T1 and
would create a duplicate. The repo sets no isolation override and PG's default
is READ COMMITTED, so the design holds as shipped. Recommend recording
`SHOW default_transaction_isolation` in the AC14 target-host checklist.

**A unique constraint is not required** for the authorized production path.
Schema work was not authorized and none was made. (See F-6 for the optional
HPO hardening and its interaction with Retry.)

### 3.3 Interleavings examined

| Interleaving | Outcome |
|---|---|
| Start ∥ Start (no active) | serialized by the media lock → one Transcription, one attempt (above) |
| Start ∥ Start (active exists) | both reuse it; one attempt; **each call re-dispatches a message for the same attempt** (F-3) |
| Admin Start ∥ Owner Start | same path, same lock |
| Start ∥ retention purge | purge also `lockForUpdate`s the media row (`RetentionPurge.php:134`); serialized — Start then sees `purged_at` and refuses, or purge waits; a purge after Start fails the job at `MediaMissing` (recoverable Failed) |
| Start (earlier Failed T1) ∥ or → Retry(T1) | **not serialized** (F-2): two active transcriptions for one media (sequential reproduction, no race needed) |
| Orchestrator called with a stale in-memory status | pre-existing weakness, practically unreachable from Start (F-4) |

---

## 4. Acceptance criteria AC1–AC14

Numbering per the binding HPO decision as reconciled in contract §9. The
HPO decision text is the authority; the durable enumeration lives only in the
task contract (see F-8a).

| AC | Requirement | Result | Evidence |
|---|---|---|---|
| 1 | Real MediaFile reused; no fake/duplicate media | **PASS** | initiator only reads/locks `MediaFile`, never creates one [R]; tests assert media count unchanged (26/26 [E]) |
| 2 | Upload alone does not dispatch; explicit action exists | **PASS** | no upload-path diff [R]; upload test: 1 media, 0 transcriptions, 0 jobs, `Queue::assertNothingPushed`; viewing detail starts nothing; Media Detail renders the control [E] |
| 3 | Real Transcription linked to authorized user + media; canonical defaults | **PASS** | `user_id`=media owner, `media_file_id`, title=display name, `language=null` (P3-001: null = auto-detect), model from config, Draft→Queued [R][E] |
| 4 | Uses `TranscriptionOrchestrator::request()` | **PASS** | sole call site is `TranscriptionInitiator.php:89`; spy asserts `request` once; controller and initiator contain no queue/lifecycle code [R][E] |
| 5 | ProcessingJob Queued created/reused | **PASS** | assertions on attempt; double-submit leaves 1 job (also probe P1 [E]); pg unique-index backstop [R] |
| 6 | `ProcessTranscription` on the configured queue; no sync inference | **PASS** | `Queue::assertPushedOn('transcription', …)`; queue name/connection read from `config('transcription.*')` in the orchestrator only (no hard-coding in new code); production boot is refused unless `QUEUE_CONNECTION=redis` (D7-02) — observed [E] when I tried to boot `APP_ENV=production` |
| 7 | Authorization: owner ok, non-owner 403, admin per policy | **PASS** | server-side `authorize('update')` (`MediaFilePolicy`: admin or owner); tests + probes: non-owner 403, nonexistent id 404, admin allowed and row owned by media owner, guest → login [E]; hidden button is not relied upon |
| 8 | Double submit safe; re-transcription later allowed | **PASS** (notes F-2, F-3, F-5) | sequential duplicate, four active-status reuse cases, three terminal-status re-transcription cases [E]; concurrency by analysis §3, not by an executed PG race |
| 9 | Non-processable states rejected safely | **PASS** | six parameterized refusals create no rows and no dispatch [E]; rule discussed in §5 |
| 10 | Demo isolation in production | **PASS** | independently confirmed under `APP_ENV=production`: `route:list` shows 20 transcription-prefixed routes vs 21 in `local`; `transcriptions.store` absent; `transcriptions/create` = `RedirectController` behind `auth`+`verified`; controller 404 guard present; tests green [E] |
| 11 | Real pipeline integration with fake provider | **PASS** | end-to-end test: Start → real `ProcessTranscription` handler (sync queue, test env) → `RecordingTranscriptionProvider` called once → Completed, `full_text`, 2 segments, attempt Completed [E] |
| 12 | Upload contract unchanged | **PASS** | no upload-code diff; `MediaUploadContractTest`, `MediaIngestionTest`, `MediaManagementTest` green in the related run [E] |
| 13 | Regression | **PASS** | §6 |
| 14 | Post-deployment production-candidate verification: real upload → Start Transcription → Queued → Running → Completed with visible output | **NOT YET EVIDENCED** | By design post-review, post-deployment, target-host only (contract §9). Not claimed, not simulated. Vocabulary: the contract's own term "NOT YET EVIDENCED"; prior reviews use "target-only" / `BLOCKED-ENVIRONMENT (target-only)` for environment-gated evidence — no new vocabulary invented. |

---

## 5. Product / domain, eligibility, authorization, demo, queue review

- **MediaFile ≠ Transcription preserved.** The existing uploaded MediaFile is
  reused; a media file may carry several Transcriptions over time. No fake
  media is created. Upload does not dispatch.
- **Eligibility rule — each condition checked against the canon.**
  `Uploaded` is the canonical state of a successfully persisted upload and is
  the **only** media status the production code ever writes
  (`MediaIngestionService.php:148`; `architecture.md:124`; `DECISIONS.md:334`).
  `Processing` and `Ready` are "reserved for later media-processing semantics";
  nothing in production writes them (only the demo controller and
  `Phase5IntegrationVerification` write `Ready`). Admitting `Ready` is
  harmless and forward-compatible (a post-processing media is at least as
  ready as `Uploaded`) and `Processing`/`Failed`/`Deleted` are correctly
  refused. `purged_at === null` and `hasPhysicalFile()` (which itself requires
  `purged_at === null` and the object to exist) match the P7-011 tombstone
  contract. `scan_verdict !== 'infected'` is redundant defense-in-depth:
  infected uploads are quarantined and rejected at ingest and never persisted
  (`MediaIngestionService.php:237`); persisted verdicts are `clean`/`skipped`.
  Not inferred from UI behavior: the server action re-checks all four under
  the media lock. (INFO F-7: `Ready` has no positive test.)
- **No new product behavior.** No options are exposed; canonical defaults only
  (`language=null`, `config('transcription.model')`). No auto-start, no
  schema, no provider/worker change.
- **Authorization.** `MediaFilePolicy@update` (admin or owner) is evaluated
  server-side before any state change; model binding by id cannot reach another
  user's media without passing the policy (403, probe P6 [E]). The route is in
  the `auth`+`verified` group [E `route:list --json`].
- **Demo path.** Production: demo GET/POST not registered; the page URL
  redirects to the real upload page; the controller independently 404s even if
  a route cache was built under another env. No production link points at demo
  creation (the only `transcriptions.create` reference is a sidebar `routeIs`
  check; `route('transcriptions.store')` appears only in the demo view, which
  production cannot reach). Local/test behavior intact: `DemoTranscriptionTest`
  green [E]. Route-cache dependency noted in F-9.
- **Queue/orchestration.** `TranscriptionOrchestrator::request()` remains
  authoritative for Draft→Queued, attempt creation/reuse and dispatch; the
  controller/initiator add no lifecycle logic. `ProcessTranscription` is
  dispatched on the configured queue/connection; inference never runs in the
  request in production (redis is mandatory via `ProductionConfigGuard`; sync
  only in the test environment).

---

## 6. Test evidence

Environment: PHP 8.4.24 (Herd, Windows), SQLite in-memory, `QUEUE_CONNECTION=sync`
in tests, `memory_limit=2G` (the default 128M CLI limit is insufficient for the
full suite — implementer note confirmed as practical, not a defect).
**No PostgreSQL server, `psql`, or Docker exists in this environment**
(port 5432 closed; `pdo_pgsql` extension present but unusable).

| Command (reviewer-run) [E] | Result |
|---|---|
| `pest tests/Feature/Transcription/TranscriptionInitiationBridgeTest.php` | 26 passed / 125 assertions |
| Related: `DemoTranscriptionTest`, `MediaUploadContractTest`, `MediaIngestionTest`, `MediaManagementTest`, all of `tests/Feature/Transcription`, `AuthorizationTest`, `PageRenderTest`, `OwnershipTest`, `TranscriptionManagementTest`, `TranscriptionDetailTest` | 262 passed / 964 assertions |
| Full `pest` (`memory_limit=2G`) | 1279 tests, **1274 passed, 5 skipped, 0 failures**, 5067 assertions (4 PHPUnit warnings with no detail from the JSON reporter; the bridge suite emitted none) |
| `pint --test` on the five changed PHP files | passed |
| `phpstan analyse` (repo config) | 0 errors |
| Reviewer scratch probe (PHPUnit class **outside the repo**, not committed; `git status` unchanged) | results in §3/§7 |

Implementer-reported [I], not relied on: 26/26, 1279 tests / 1273 pass / 5 skip / 1 failure.
My full run showed 0 failures; the difference is the flake below.

**`LogContextTest::it_counts_attempt_ordinals_across_attempts_for_the_same_transcription`
— classified separately, pre-existing, unrelated.** Reproduced in isolation:
**3 failures in 14 runs** [E] with `UNIQUE constraint failed:
processing_jobs.transcription_id`. Root cause: `ProcessingJobFactory` assigns
a random `ProcessingStatus`; when both rows drawn for one transcription are
`queued`/`running`, the second insert violates the long-standing partial unique
index `processing_jobs_active_attempt_unique` (P3-007). The test, factory and
index are untouched by this diff and the test exercises none of the changed
code. It is the already-recorded **TD-008** (order/random-dependent flakiness;
documented in `reviews/PHASE7-WAVE2-INDEPENDENT-REVIEW.md` F3 and
`reviews/PHASE7-WAVE3A-INDEPENDENT-REVIEW.md`). Not attributable to
CORR-01; no test was modified to obtain green.

**Test adequacy (honest assessment).** Strong: authorization, eligibility
matrix, spy on the orchestrator, queue-name assertion, active-reuse and
terminal-re-transcription matrices, real end-to-end handler run, production
route/controller guards. Gaps (none violate an AC): the sequential
double-submit test asserts DB counts only and not the dispatch count
(F-3); there is **no test for dispatch failure** (F-1); **no test for
Retry-vs-Start** (F-2); **no executed concurrency test** of the row lock
(F-5); `Ready` has no positive case (F-7).

---

## 7. Failure semantics (reviewer probes [E] unless stated)

| Scenario | Outcome | Recoverable / contract-compliant |
|---|---|---|
| Transcription created, then orchestrator throws before Queued | Draft remains; "active" → next Start reuses it and the orchestrator advances it | Recoverable by re-Start only via direct POST — the UI hides the button for any active status incl. Draft (F-1). Contract silent. |
| Dispatch fails after DB commit (probe P2) | HTTP 500; Transcription=`queued`, attempt=`queued`, nothing on the queue. Media Detail: **no** Start button. Transcription page: **no** Retry (offered only for Failed). `StaleTranscriptionAttemptRecovery` recovered 0 after 2 h (it handles only `Running`). A direct re-POST afterwards re-dispatches idempotently (1 transcription, 1 attempt, 1 delivery). | Data-consistent and recoverable by direct POST/operator; **no UI recovery** (F-1). Same post-commit dispatch pattern as pre-existing `TranscriptionRetry` (P3-007). Not an AC violation. |
| Media unavailable between check and execution | Check re-run under the media lock; if bytes vanish later the job fails with `MediaMissing` (Failed, retry-eligibility per existing taxonomy) | Recoverable; compliant. |
| Double-click / browser resend / timeout retry | One Transcription, one attempt; a duplicate no-op queue message is enqueued per extra POST (probe P1: 2 messages for attempt 1); handler's claim fence skips them (`ProcessTranscription.php:100-122`) | Safe; compliant (F-3). |
| Active transcription already exists | Reused; user redirected to it | Compliant. |
| Previous transcription Completed / Failed / Cancelled | New Transcription created (deliberate re-transcription); never permanently blocked | Compliant (contract §6). |
| Ineligible media | 302 to Media Detail with an understandable error; no rows, no dispatch | Compliant. |
| Non-owner / guest / unknown id | 403 / login redirect / 404 | Compliant. |

---

## 8. Findings

Severity vocabulary per repository: BLOCKER / HIGH / MEDIUM / LOW / INFO.
No BLOCKER. No HIGH.

### F-1 — MEDIUM — Queued-but-undispatched (or Draft-stuck) transcription has no UI recovery
- Location: `resources/views/livewire/media/show.blade.php:3-14` (Start hidden for any active status incl. Draft/Queued); `app/Actions/StaleTranscriptionAttemptRecovery.php:51-56` (only `Running` attempts); `resources/views/transcriptions/show.blade.php:406-412` (Retry only for Failed); `TranscriptionInitiator.php:89` / `TranscriptionOrchestrator.php:85` (dispatch after commit).
- Evidence: probe P2 — dispatch exception after commit → 500, rows `queued`/`queued`, Start hidden, no Retry, stale recovery = 0; a direct re-POST recovers (idempotent).
- Consequence: a transient queue failure at click time strands the transcription as "Queued" with no user-visible way forward until an operator/direct POST re-triggers dispatch. No data loss or inconsistency.
- Blocks corrective deployment/review? **No.** Contract is silent on dispatch failure; AC5/AC6 hold; the weakness is shared with the pre-existing post-commit dispatch in `TranscriptionRetry`/`TranscriptionOrchestrator::request()`. Recommendation to the HPO: carry as a follow-up (e.g. offer "Resume" for Queued/Draft with no progress, or a Queued-attempt sweeper analogous to the translation `dispatched_at` mechanism); the HPO may instead elect to require it before deployment — that is a product/hardening decision, not a defect in the reviewed scope.

### F-2 — LOW — Retry can reactivate an earlier Failed transcription alongside a Start-created one
- Location: `app/Actions/TranscriptionRetry.php:57-128` (no media-row lock, no per-media active check); interacts with `TranscriptionInitiator.php:70-77`.
- Evidence: probe P3 — Failed retryable T1 → Start creates T2 (active=1) → Retry on T1 → **active transcriptions for the media = 2**. Sequential; no race required. A concurrent Start ∥ Retry is likewise unserialized.
- Consequence: duplicate inference and two transcripts for one media; each transcription still keeps one-active-attempt; no corruption. "One active transcription per media" is not a documented canonical invariant (greps of `DECISIONS.md`, `architecture.md`, P3 contracts), and contract §6 scopes idempotency to identical initiations.
- Blocks? **No.** Options for the HPO: take the same media-row lock + active check in `TranscriptionRetry` (code only), or the optional schema hardening in F-6.

### F-3 — LOW — Repeated Start on an active transcription enqueues another ProcessTranscription message each time
- Location: `TranscriptionOrchestrator::request()` always calls `dispatch()` (line 85), including when it reuses an active attempt; route has no throttle.
- Evidence: probe P1 — two POSTs → 1 transcription, 1 attempt, **2** queued messages (same ids). The shipped double-submit test does not assert the push count.
- Consequence: benign — the handler's terminal checks and atomic `claimAttempt` skip duplicates; this re-dispatch is also the only recovery for F-1. An authenticated user can enqueue many no-op messages (negligible).
- Blocks? **No.**

### F-4 — INFO — (pre-existing) orchestrator checks terminal status on the caller's stale model
- Location: `TranscriptionOrchestrator.php:42-51` vs. the locked re-read at 54-57 (no terminal re-check).
- Evidence: probe P4 — a transcription completed in the DB but passed in-memory as Queued gets a new Queued attempt + dispatch (the job later skips as terminal, leaving a permanently Queued attempt row).
- Consequence: reaching this from Start requires a transcription to complete between the initiator's commit and the orchestrator's transaction (sub-second window versus minutes of inference) — practically unreachable. Orchestrator is protected scope and unchanged. Informational, not attributable to this change.

### F-5 — INFO (BLOCKED-ENVIRONMENT, target-only) — concurrency guarantee is reasoned, not executed
- No PostgreSQL in the review environment, and `lockForUpdate()` is a no-op on SQLite (ADR-013) so no SQLite multi-process harness could prove it. Consistent with the P7-002 PG-halves precedent. Recommended AC14 probe: two parallel POSTs on the target host → exactly 1 Transcription, 1 attempt; and `SHOW default_transaction_isolation` = `read committed`.

### F-6 — INFO — optional database-level backstop for "one active transcription per media" (schema; NOT authorized)
- Not required for VERIFIED (§3.2). If the HPO wants it: a pg partial unique index on `transcriptions(media_file_id) WHERE status IN ('draft','queued','preparing','transcribing')`. Note it would make `TranscriptionRetry` (Failed→Queued while another active transcription exists) raise a constraint violation, so Retry would need explicit handling — i.e., F-2 and F-6 should be decided together.

### F-7 — INFO — `Ready` admitted without a positive test; `infected` check is redundant
- `Ready` is reserved/unused in production (§5); including it is harmless. Add a single positive case if desired. `infected` rows cannot exist (rejected at ingest).

### F-8 — INFO — governance bookkeeping
- (a) `DECISIONS.md` / `DECISION_QUEUE.md` do not enumerate AC1–AC14; the only durable enumeration is task contract §9, which defers to the HPO decision. Evaluated against §9 and the AC14 text supplied with the review request.
- (b) `plan.md`, `RTFTT-MASTER-ROADMAP.md`, `AGENTS.md` do not mention CORR-01; they remain accurate and non-contradictory. HPO may reflect it at closure.
- (c) The implementation owner and this reviewer are both Claude Code; independence here rests on a fresh session and fully re-executed evidence. The HPO may weigh that.

### F-9 — INFO — demo-route removal depends on the env at route-registration time
- `docs/DEPLOYMENT-RUNBOOK.md:49` runs `route:cache` on the host "after env is final" (production env), so the demo routes are not cached. If a route cache were ever built under a non-production env, `GET /transcriptions/create` (the demo form) would reappear; the `POST` stays blocked by the controller's 404 guard. Add to the AC14 checklist: `GET /transcriptions/create` redirects to `/media/upload`; `POST /transcriptions` is not routable.

### F-10 — INFO — cosmetic
- The "Transcription queued." flash is shown even when an already-running transcription is reused.

### Pre-existing / unrelated (separate classification)
- **TD-008** `LogContextTest` random-factory flake (§6) — reproduced 3/14, unrelated to this diff, not modified, not blocking. TD-008 is OPEN/MEDIUM pre-P7-012 per `DECISION-TD-008-REPRIORITIZATION-001`.

---

## 9. Architecture, security, regression summary

- **Architecture:** compliant. Thin controller; one action class; orchestrator remains the only owner of Draft→Queued, attempt creation/reuse and dispatch; domain separation preserved; no queue name/connection hard-coded; no lifecycle duplication beyond the active-status list, which is repeated in three places (initiator constant, view, orchestrator terminal list) — a maintainability note, not a defect.
- **Security:** server-side authorization before any state change; CSRF on the POST form (framework middleware; the demo-controller test disables it explicitly); no input is accepted from the client beyond the route key; no new user-controlled options; no data exposure via 403/404 differences beyond the existing convention.
- **Regression:** upload, media management, authorization, ownership, page-render, retry, orchestration, and demo (non-production) suites green; full suite 0 failures in the reviewer run.

---

## 10. Required changes

None required for VERIFIED. Items for HPO consideration (non-blocking, recorded above): F-1 (recommended follow-up), F-2/F-6 (decide together), F-3.

---

## 11. Remaining target-host AC14 and recommended acceptance checklist

AC14 remains **NOT YET EVIDENCED** and cannot pass locally. After HPO closure and deployment of a new immutable release, evidence should include:

1. Real upload → Media Detail shows Start Transcription → click → Transcription Queued → Running → Completed with visible transcript output; `ProcessingJob` Completed; exactly one Transcription for the media.
2. Two parallel POSTs to `media.transcriptions.store` on PostgreSQL → 1 Transcription, 1 active attempt (closes F-5); `SHOW default_transaction_isolation` = `read committed`.
3. Production `GET /transcriptions/create` → redirect to `/media/upload`; `POST /transcriptions` not routable (F-9).
4. The pre-existing real user media row on the host is used or left untouched deliberately (readiness review I-2), and no demo-fabricated rows appear.
5. Optional: a controlled dispatch-failure drill to observe F-1 behavior on the target host.

Then re-run the Target-Host Readiness Confirmation; only `TARGET_HOST_READY` lets the HPO separately authorize Phase B (contract §13).

---

## 12. Reviewer Conclusion

**VERIFIED.**

- AC1–AC13: PASS. AC14: NOT YET EVIDENCED (post-deployment, target-host-only — by design).
- No unresolved BLOCKER or HIGH finding. One MEDIUM (F-1), two LOW (F-2, F-3), and INFO items are explicitly documented and do not prevent safe completion of the reviewed scope.
- The lock-based idempotency is **sufficient for the authorized production path** (PostgreSQL, READ COMMITTED, Start/Admin/Owner paths). It was verified by code reading and PG semantics, **not** by an executed concurrent PostgreSQL test (none possible here).

**Handoff / authority:**
- VERIFIED does not mean DONE. Only the Human Product Owner may close the task as DONE.
- The HPO **may** (at their discretion) authorize the corrective commit and deployment preparation. This review does **not** authorize deployment, a commit, or any production action.
- P7-009 Phase B = **NOT AUTHORIZED**.
- P7-007 restore drill = **NOT AUTHORIZED**.
- P7-012 = **NOT AUTHORIZED** (FINAL_GATE_ONLY).
- Task status, `CURRENT_STATE.md`, `DECISIONS.md`, `DECISION_QUEUE.md` were not modified by this review; updating the task Status to VERIFIED and recording this review is left to the owning process.
