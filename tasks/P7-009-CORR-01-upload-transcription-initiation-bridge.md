# P7-009-CORR-01 — Real Upload → Transcription Initiation Bridge

## Status

**CURRENT (2026-10-02, Corrective Cycle 2 — HPO reconciliation,
`DECISION-P7-009-CORR-01-CYCLE2-001`):**

```
Corrective Cycle 2 technical status:  VERIFIED   (independent re-review verdict
                                       accepted by the HPO; not DONE)
Production AC14:                       NOT PASSED / AWAITING CORRECTED RELEASE
                                       VALIDATION
```

The first target-host AC14 attempt **FAILED** at result persistence
(`PERSISTENCE_FAILED`, PostgreSQL `invalid input syntax for type integer:
"185.832677"`); the corrective implementation is independently VERIFIED but not
committed, not deployed and not exercised on production PostgreSQL. AC14 is
**not** PASS and must be re-executed on the target host from a new immutable
release after separately authorized deployment. See "Corrective Cycle 2" and
"Independent Re-Review, HPO Acceptance and Reconciliation" at the end of this
file. The prior states below are preserved as history.

Prior state (Corrective Cycle 2 implemented, 2026-10-02; superseded, preserved as
history): **REVIEW — CORRECTIVE IMPLEMENTATION COMPLETE; AWAITING INDEPENDENT
RE-REVIEW.** Not VERIFIED. Not DONE.

Prior state (historical, preserved): **VERIFIED / ACCEPTED FOR CLOSURE** (HPO,
2026-10-02, `DECISION-P7-009-CORR-01-CLOSURE-001`). Not DONE. **AC14 = NOT YET
EVIDENCED** (post-deployment, target-host only).

History: implementation complete 2026-10-01; authorized for contract +
implementation + testing + independent-review preparation by
`DECISION-P7-009-CORR-01-UPLOAD-TRANSCRIPTION-BRIDGE-001` (HPO, 2026-10-01).
First independent review: `reviews/P7-009-CORR-01-INDEPENDENT-REVIEW.md`
(VERIFIED; F-1 MEDIUM, F-2 LOW, F-3 LOW).

Corrective Cycle 1 (`DECISION-P7-009-CORR-01-CYCLE1-001`, findings F-1 and F-2 of
`reviews/P7-009-CORR-01-INDEPENDENT-REVIEW.md`) implemented 2026-10-02; see
"Corrective Cycle 1 — Implementation Notes" below. Independent re-review:
`reviews/P7-009-CORR-01-CYCLE1-INDEPENDENT-REVIEW.md` (VERIFIED; BLOCKER 0,
HIGH 0, MEDIUM 0; AC1–AC13 PASS; AC14 NOT YET EVIDENCED; F-1 CLOSED; F-2 CLOSED;
F-3 accepted non-blocking). The HPO accepted the review and re-review and
authorized the canonical change set to be committed and pushed. See
"Closure — Carry-forward and Next Sequence" at the end of this file.

P7-009 Phase B = NOT AUTHORIZED. P7-007 restore drill = NOT AUTHORIZED.
P7-012 = FINAL_GATE_ONLY, NOT AUTHORIZED.

## Ownership

Implementation Owner: Claude Code (role changed explicitly for this task by
the HPO, per `.ai/guidelines/ai-development-os.md` "A role may be changed
explicitly for a specific task").
Reviewer: MUST be a fresh, independent review context/agent that did not
implement this task. The implementer must not review or verify its own work.

## Authorized Phase

Phase 7 — Production Hardening (corrective finding raised by
`reviews/P7-009-TARGET-HOST-READINESS-CONFIRMATION-002.md`, finding H-1).

## 1. Finding

Production: real upload → `MediaFile` (`status=Uploaded`) → no `Transcription`,
no `ProcessingJob`, no queue dispatch. Evidence: `MediaUploadController::store`
and `MediaIngestionService::ingest` stop at persistence; the only
`TranscriptionOrchestrator::request()` callers were `TranscriptionRetry` and
verification commands; the only user-facing "create transcription" route
(`POST /transcriptions`) is `DemoTranscriptionController`, which fabricates a
fake media row and a Completed transcription. Infrastructure is not at fault.

## 2. Binding product decision

Explicit user-initiated transcription after upload (HPO selected). Automatic
transcription after upload and operator-only initiation are rejected.

```
Upload → MediaFile(Uploaded) → Media Detail → Start Transcription
→ real Transcription(Draft) → TranscriptionOrchestrator::request()
→ Transcription=Queued + ProcessingJob=Queued → ProcessTranscription on the
configured transcription queue → provider/worker → persisted result+segments
→ Completed
```

Domain invariant preserved: `MediaFile != Transcription`. A media file may have
several transcription attempts over time (deliberate re-transcription).

## 3. Scope

In: one authenticated POST route on Media Detail; one initiation action class;
one controller; the Start Transcription control on the media detail view;
server authorization; processable-state validation; same-request idempotency;
production restriction of the demo path; tests; governance records.

Out (protected): MediaFile / Transcription / queue / worker-protocol / provider
/ translation / revision / deployment architecture; auto-transcription;
registration; diarization or any AI feature; new user-selectable options;
schema changes (none made — see §7); Phase B / drill / P7-012; unrelated
refactors; any VPS action.

## 4. Lifecycle (existing, unchanged)

`TranscriptionStatus`: Draft → Queued → Preparing → Transcribing →
Completed | Failed | Cancelled. The initiator creates the `Transcription` in
`Draft` (the existing requestable initial state; `TranscriptionFactory::draft()`
precedent). `TranscriptionOrchestrator::request()` alone moves Draft→Queued,
creates/reuses the `ProcessingJob`, and dispatches `ProcessTranscription`.
No lifecycle or queue logic is duplicated outside the orchestrator. No inference
runs in the HTTP request.

## 5. Initiation contract

`POST /media/{mediaFile}/transcriptions` (`media.transcriptions.store`), inside
the existing `auth`+`verified` group:

1. Authenticate (route middleware).
2. Authorize `MediaFilePolicy@update` on the media (owner or admin — the same
   policy that already governs acting on media; non-owner non-admin ⇒ 403).
3. Processable state: `status ∈ {Uploaded, Ready}`, `purged_at` null, servable
   bytes exist (`hasPhysicalFile()`), `scan_verdict !== 'infected'`. Otherwise
   safe 422-class refusal (redirect back with an understandable error; no rows
   created).
4. Under a transaction, lock the parent `media_files` row, then reuse an
   existing non-terminal transcription for that media (Draft/Queued/Preparing/
   Transcribing) or create one: `user_id` = media owner (the orchestrator
   requires `transcription.user_id === media.user_id`), `media_file_id`,
   `title` = media display name, `language` = null (auto-detect canonical
   default), `model` = `config('transcription.model')`, `status` = Draft.
5. Call `TranscriptionOrchestrator::request()` (outside the lock transaction;
   it has its own).
6. Redirect to `transcriptions.show`.

User options: none exposed; canonical defaults only (§8 of the HPO decision).

## 6. Idempotency / double submit

- "Same initiation" = a request for a media file that already has an active
  (non-terminal) transcription → the active transcription is reused, no new row,
  and the orchestrator reuses the active `ProcessingJob`. Double-click, browser
  resend, network retry, and concurrent identical requests converge on one
  active transcription and one active attempt.
- "Deliberate re-transcription" = media whose transcriptions are all terminal
  (Completed/Failed/Cancelled) → a new `Transcription` is created. Never
  permanently blocked.
- Concurrency mechanism: the parent `MediaFile` row is locked
  (`lockForUpdate`) in the transaction that does find-or-create, so concurrent
  initiations serialize on PostgreSQL (target store, D7-01/B); the loser
  re-queries after the winner commits and sees the winner's Draft/Queued row.
  Downstream, `TranscriptionOrchestrator::request()` is already
  idempotent per transcription (row lock + active-attempt reuse + the
  `processing_jobs` partial unique index on active attempts, P3-006/P3-007).
- Known limit: there is no database-level unique constraint on
  "one active transcription per media". SQLite (dev/test) serializes writers
  coarsely, so the race is only truly exercised on PostgreSQL. A partial unique
  index `transcriptions(media_file_id) WHERE status IN (active)` would be
  defense-in-depth but is a SCHEMA CHANGE and is NOT authorized; recorded as an
  HPO option (INFO finding), not implemented.
- Client side: submit button disabled on submit (cosmetic only; server
  guarantee above).

## 7. Schema

No migration. Existing schema suffices for same-request idempotency via the
parent-row lock. If review disagrees, STOP and return to HPO with options:
(a) partial unique index on active transcription per media (recommended
hardening); (b) client-supplied initiation token column; (c) keep lock-only.

## 8. Demo path

`DemoTranscriptionController` and its `GET /transcriptions/create` page are not
registered in `APP_ENV=production`; production `GET /transcriptions/create`
redirects to the real upload page. The controller additionally aborts 404 in
production (defense in depth when a route cache was built under another env).
Code and tests remain for local/test environments (ADR-006 prototype scope).

## 9. Acceptance criteria (numbering per the binding HPO decision AC1–AC14)

The HPO decision `DECISION-P7-009-CORR-01-UPLOAD-TRANSCRIPTION-BRIDGE-001` is
authoritative for AC numbering and meaning. AC1–AC13 are verifiable by the
automated suite before review; AC14 can only be evidenced after the corrective
release is reviewed, closed, and deployed.

| AC | Requirement | Verified by |
|---|---|---|
| 1 | Real MediaFile reused; no fake/duplicate media | initiation feature test (media count unchanged) |
| 2 | Upload alone does not dispatch; explicit action exists | upload regression + bridge test (Queue::fake, no job after upload); media detail shows control |
| 3 | Real Transcription linked to authorized user + media, canonical defaults | feature test |
| 4 | Uses `TranscriptionOrchestrator::request()` | feature test with orchestrator binding spy + job assertion; no queue logic in controller |
| 5 | ProcessingJob Queued created/reused | feature + double-submit tests |
| 6 | `ProcessTranscription` dispatched to configured queue; no sync inference | `Queue::fake` assertions on queue name |
| 7 | Authorization: owner ok, other user 403, admin per policy | feature tests |
| 8 | Double submit safe; re-transcription later allowed | sequential + pre-existing active + terminal tests; concurrency note §6 |
| 9 | Non-processable states rejected safely | feature tests (Deleted/Failed/Processing, purged, missing bytes, infected) |
| 10 | Demo isolation in production | route-registration + controller-guard tests |
| 11 | Real pipeline integration with fake provider | end-to-end test through `ProcessTranscription` with `RecordingTranscriptionProvider` |
| 12 | Upload contract unchanged | existing upload suites green |
| 13 | Regression | targeted + broad suites green |
| 14 | Production-candidate verification after deployment: a real uploaded media file can progress Uploaded → Start Transcription → Queued → Running → Completed, with visible transcription output | NOT YET EVIDENCED — post-review, post-deployment target-host acceptance (new immutable release); not part of the implementation test run. First target-host attempt (2026-10-02, operator-reported): upload, Start Transcription, queue claim and real inference passed; **result persistence FAILED** — see Corrective Cycle 2. Not PASS. Corrective Cycle 2 is independently VERIFIED and HPO-accepted (`DECISION-P7-009-CORR-01-CYCLE2-001`), but the corrected release is not deployed: **NOT PASSED / AWAITING CORRECTED RELEASE VALIDATION.** |

## 9A. Governance / Non-Scope / Authorization Boundary

Binding, and deliberately not an acceptance criterion:

- P7-009 Phase B must not be started (NOT AUTHORIZED).
- P7-007 restore drill must not be started (NOT AUTHORIZED).
- P7-012 must not be started (FINAL_GATE_ONLY, NOT AUTHORIZED).
- No capacity/stress execution; no final production-readiness declaration.
- Successful completion of this task does not authorize Phase B. The sequence
  after VERIFIED → DONE (HPO) is: deploy a new immutable release → AC14
  target-host acceptance → re-run the Target-Host Readiness Confirmation →
  only `TARGET_HOST_READY` lets the HPO separately authorize Phase B.
- No deployment, production mutation, schema change, or commit is performed by
  the implementation task itself.

## 10. Test matrix

`tests/Feature/Transcription/TranscriptionInitiationBridgeTest.php` (new) plus
adjustments to the demo tests only where the demo path becomes env-conditional.
Existing suites run unchanged: media upload/ingestion, media management,
orchestration, ProcessTranscription, retry, authorization, page render.

## 11. Rollback / regression concerns

Additive route/controller/action/view only; rollback = redeploy previous
release. Risk: demo-route removal in production changes the sidebar-less
`/transcriptions/create` URL (now redirects). Risk: lock-only idempotency on
non-PostgreSQL stores (documented, §6).

## 12. Review requirement

Independent review (not the implementer) verifies AC1–AC13 on the delivered code and confirms AC14 remains a pending post-deployment criterion, no scope creep,
no demo masquerade, orchestration boundary, authorization, concurrency
(especially the lock-based idempotency on PostgreSQL), upload regression, and
no unauthorized Phase 7 execution. Passing automated tests alone does not close
this task.

## 13. Phase 7 gating effect

Does not authorize P7-009 Phase B. After REVIEW → VERIFIED → DONE (HPO), a new
immutable release is deployed, AC14 production-candidate verification is performed, and the
Target-Host Readiness Confirmation is re-run. Only `TARGET_HOST_READY` permits
the HPO to authorize Phase B. Canonical sequence unchanged:
TARGET_HOST_READY → HPO authorizes Phase B → capacity/stress/real-environment
→ P7-007 drill → real-host verification → P7-012.

## Implementation Notes

Files: `app/Actions/TranscriptionInitiator.php` (new), `app/Http/Controllers/MediaTranscriptionController.php`
(new), `routes/web.php` (new `media.transcriptions.store`; demo routes env-conditional),
`app/Http/Controllers/DemoTranscriptionController.php` (production 404 guard),
`resources/views/livewire/media/show.blade.php` (Start Transcription control),
`tests/Feature/Transcription/TranscriptionInitiationBridgeTest.php` (new, 26 tests).
No migration, no dependency, no worker/provider/queue change.

Verification: bridge suite 26/26; full `pest` (memory_limit=2G; default 128M CLI
limit exhausts on the full suite) 1279 tests, 1273 pass, 5 skipped, 1 failure =
`LogContextTest::it_counts_attempt_ordinals_across_attempts_for_the_same_transcription`
— pre-existing random-factory flake already recorded in
`reviews/PHASE7-WAVE3A-INDEPENDENT-REVIEW.md` (fails/passes independent of this
change); Pint clean; PHPStan 0 errors.

## Corrective Cycle 1 — Implementation Notes

Authority: `DECISION-P7-009-CORR-01-CYCLE1-001`. Scope: F-1 and F-2 only. No
migration, no dependency, no worker/provider/queue/orchestrator change, no
commit, no deployment.

### F-1 — stranded Queued/Draft attempt has a UI recovery path

- Recovery reuses the existing `POST /media/{mediaFile}/transcriptions`
  (`media.transcriptions.store`) → `TranscriptionInitiator` →
  `TranscriptionOrchestrator::request()`. The initiator reuses the media's
  active Transcription; the orchestrator reuses its active ProcessingJob and
  re-dispatches. No second Transcription, no second active ProcessingJob, no
  synchronous inference, no schema change, and nothing is marked Failed.
- `MediaTranscriptionController` catches a post-commit failure
  (`Throwable` other than `TranscriptionException`), `report()`s it, and
  redirects to the existing stranded transcription with guidance instead of a 500.
- A **Resume Transcription** control (same endpoint, CSRF, `can('update')`) is
  shown on Media Detail and Transcription Detail for Draft/Queued only.
  Preparing/Transcribing/terminal states never show it.
- Repeat resume is idempotent at the row level. Each resume enqueues one more
  `ProcessTranscription` message for the same attempt (review F-3); the
  handler's claim fence skips duplicates. A normally-Queued attempt waiting for a
  worker also shows Resume — there is no `dispatched_at` to tell "waiting" from
  "never dispatched" without a schema change.

### F-2 — Retry cannot create competing active work

- `TranscriptionRetry::retry()` now, inside its transaction, takes the parent
  `media_files` row lock and refuses (rolling back the CAS and new attempt) if a
  different Draft/Queued/Preparing/Transcribing transcription exists for the media.
  Refusal is a `TranscriptionException` ("Another transcription is already
  active for this media file."), surfaced by the existing controller convention
  as a redirect-back `error` flash. A pre-check gives the same answer before the
  transaction opens.
- `isEligible()` keeps its original failure-based meaning. The new public
  `isBlockedByActiveTranscription()` is a separate state, so the page says
  "Another transcription is already active…" and not "This failure is not
  retryable." Retry is allowed again as soon as the competing one is terminal.
- Lock order is deliberately CAS write → media lock. Media-lock-first was
  measured to fail `TranscriptionRetryConcurrencyTest` (read before first write
  → SQLite snapshot-upgrade BUSY) in 4 of 4 runs. PostgreSQL reasoning
  (READ COMMITTED; Start ∥ Retry, Retry ∥ Retry, no lock cycle with Start or
  `RetentionPurge`) is in the `TranscriptionRetry` class docblock.

### Verification (implementer-run, not independent)

`TranscriptionCorrectiveCycle1Test` 16/16; bridge suite and retry suites incl.
the two-process SQLite harness green; related suites 278/278; full `pest`
(memory_limit=2G) 1295 tests / 1290 passed / 5 skipped / 0 failures; Pint and
PHPStan clean. Mutation checks: removing the in-transaction guard fails the
rollback test; removing the controller catch fails the dispatch-failure test.

Not executed here: any PostgreSQL concurrency run (no PostgreSQL available;
`lockForUpdate()` is a no-op on SQLite). Target-host AC14 checklist should add
two parallel Start POSTs, and Start ∥ Retry on a Failed transcription, on PostgreSQL.

Governance records (`CURRENT_STATE.md`, `DECISIONS.md`, `DECISION_QUEUE.md`) were
not edited by this cycle; the Cycle 1 decision was recorded afterwards by the
closure reconciliation (see below).

## Closure — Carry-forward and Next Sequence

Authority: `DECISION-P7-009-CORR-01-CLOSURE-001` (HPO, 2026-10-02), recorded in
`DECISIONS.md` and `DECISION_QUEUE.md`, together with
`DECISION-P7-009-CORR-01-CYCLE1-001`.

State: **VERIFIED / ACCEPTED FOR CLOSURE**. Not DONE. AC1–AC13 PASS; **AC14 NOT
YET EVIDENCED** and is not marked PASS here; P7-009 Phase B is not marked
authorized. The commit/push authorization does not deploy anything.

Accepted non-blocking carry-forward findings (source: Cycle 1 re-review §11):

| ID | Severity | Finding | Carried forward to |
|---|---|---|---|
| RR-1 | LOW | A stale Resume page can initiate a new transcription after the previous transcription has already become terminal. | real-host / product UX verification |
| RR-2 | LOW | Retry (transcription row → media row) and media deletion (media row → transcription row via FK cascade) acquire locks in opposite order; possible PostgreSQL deadlock under concurrent execution. | Phase 7 real-host / concurrency verification |
| RR-3 and later | INFO | Retained as documented informational findings (Retry endpoint generic 500 on post-commit dispatch failure; Resume visibility vs. processable check; Resume for a healthy Queued attempt; RR-6 governance lag, resolved by this reconciliation; log-before-push; carried-over F-4/F-5/F-6/F-9/F-10). | — |

Post-commit sequence (HPO-directed; not started by this record):

1. deploy a new immutable release;
2. do not modify the existing release in place;
3. execute AC14 on the target host;
4. perform the PostgreSQL concurrency checks listed by the reviewer
   (`reviews/P7-009-CORR-01-CYCLE1-INDEPENDENT-REVIEW.md` §14);
5. rerun Target-Host Readiness Confirmation;
6. only `TARGET_HOST_READY` may permit a later HPO authorization of P7-009
   Phase B.

Not authorized by this closure: P7-009 Phase B; P7-007 restore drill; P7-012;
final production readiness.

## Corrective Cycle 2 — Production AC14 Failure: `processing_seconds` Integer Persistence

Authority: HPO instruction in chat, 2026-10-02 ("P7-009-CORR-01 — Corrective
Cycle 2"), treated as a production-discovered corrective defect inside the
currently authorized P7-009 corrective scope. No `DECISION-…` ID was supplied, so
none is recorded here; the reconciliation step that follows review should record
one (as was done for Cycle 1). Scope: this defect only. No migration, no
dependency, no worker/provider/queue/orchestrator change, no VPS action, no
manual production-database change, no commit, no deployment.

### Lifecycle trail (history preserved; nothing rewritten)

```
VERIFIED / ACCEPTED FOR CLOSURE (2026-10-02)
→ release deployed; production AC14 attempt: upload PASS, Start Transcription PASS,
  queue dispatch PASS, worker claim PASS, faster-whisper large-v3 inference PASS
  (language ms, p=0.91, POST /transcribe 200), result persistence FAIL
  (QueryException, failure_code PERSISTENCE_FAILED,
  `invalid input syntax for type integer: "185.832677"`)
→ root cause confirmed in the repository (below)
→ corrective implementation (below)
→ corrective verification (below; implementer-run, not independent)
→ REVIEW: awaiting independent re-review
```

The production evidence above was supplied by the operator; the implementer did
not access the target host and did not reproduce the PostgreSQL error itself (no
PostgreSQL server is available locally).

### Root cause (verified against the repository, not only the production diagnosis)

```
fractional elapsed seconds
→ TranscriptionResultWriter::persist() (pre-fix line 89)
→ bound as a string by Laravel → `processing_seconds` integer column
→ PostgreSQL rejects "185.832677"
```

- Defective expression: `max(0, $completedAt->diffInSeconds($startedAt, true))`.
  Carbon 3.13.2 (`composer.lock`;
  `vendor/nesbot/carbon/src/Carbon/Traits/Difference.php:404`) declares
  `diffInSeconds(): float` (milliseconds ÷ 1000). `max(0, float)` keeps the float.
- Why it is fractional: `$startedAt` is `$locked->started_at`, re-read from the
  database at whole-second precision; `$completedAt` is `now()` at microsecond
  precision. The elapsed value therefore carries the completion instant's
  microseconds (consistent with `.832677` in the production value).
- One value feeds both columns: `transcriptions.processing_seconds` and
  `processing_jobs.processing_seconds` (same `$processingSeconds`). Both are
  `unsignedInteger` (`2026_09_09_000003_…:23`, `2026_09_09_000005_…:21`), which
  Laravel's PostgreSQL grammar emits as `integer` (`PostgresGrammar::typeInteger`).
- Float → string: `Connection::bindValues()` binds anything that is not `int` as
  `PDO::PARAM_STR`
  (`vendor/laravel/framework/src/Illuminate/Database/Connection.php:750-763`), so
  PostgreSQL receives the text `185.832677` for an `integer` column. A whole float
  (e.g. `185.0`) binds as `"185"` and is accepted, so the defect depends on the
  fractional part.
- Why it was latent: the model casts `processing_seconds` as `integer`
  (`Transcription.php:61`, `ProcessingJob.php:60`) but Eloquent applies that cast
  on read, not on write. SQLite (dev/test) accepts a REAL in an `INTEGER`-affinity
  column and the cast hides it on read-back. The existing assertion
  (`TranscriptPersistenceTest`) was `processing_seconds >= 0` through the cast
  attribute, and the shared fixtures leave `started_at` null.

### Canonical integer semantics

```
Chosen normalization: round to the nearest whole second, half away from zero
                      (PHP `round()` default), then `(int)`; `max(0, …)` retained.
Reason:               smallest deterministic rule that matches the repository's
                      existing convention for float seconds → integer-seconds
                      storage.
Existing contract/evidence:
  - No document or test defines a normalization rule for `processing_seconds`
    (searched tasks/, docs/, PROJECT_CONTEXT.md, reviews/, tests/). The column
    contract is "integer seconds"; it is unchanged (no schema change).
  - Nearest precedent: `MediaMetadataProbeService::parseProbeOutput()` stores
    `media_files.duration_seconds = (int) round((float) $formatDuration)`, and
    `processing_seconds / duration_seconds` is the documented RTF
    (PROJECT_CONTEXT.md §15, `Transcription::getRealTimeFactorAttribute()`), so
    both operands now use the same rule. Duration milliseconds in the job logs
    use `(int) round(… * 1000)`.
  - Display (`getFormattedProcessingTimeAttribute`) formats whole seconds with
    `intdiv`; it assumes an integer and does not constrain the rule.
Boundary behavior:
  185.000000 → 185      185.100000 → 185      185.499999 → 185
  185.500000 → 186      185.832677 → 186      185.999999 → 186
  0.400000 → 0 (sub-second run); the diff is absolute, so a negative value
  cannot occur and `max(0, …)` stays only as the existing guard.
```

**Semantic choice flagged for independent review.** Truncation (floor) is the
alternative; it would give `185.832677 → 185` and would equal the difference of
the persisted `started_at`/`completed_at` exactly, because those timestamps are
stored at whole-second precision (the stored `completed_at` is truncated, so the
displayed timestamp difference can be 1 s smaller than `processing_seconds`
under rounding). Rounding was chosen for consistency with `duration_seconds` and
the RTF numerator; the difference is at most 1 s, which is already within the
±1 s resolution of the whole-second `started_at`. If the reviewer prefers floor,
the change is a one-token swap and the expected values in the boundary dataset
change accordingly.

### Corrective change

- `app/Actions/TranscriptionResultWriter.php` (one expression plus a two-line
  comment): `max(0, (int) round($completedAt->diffInSeconds($startedAt, true)))`.
  One computed value feeds both the transcription and the attempt row, so both
  columns are fixed by the single change. No schema, model, orchestrator, queue,
  provider or worker change.
- `tests/Feature/Transcription/TranscriptPersistenceTest.php` — regression
  coverage: 7 new test cases (1 production-value test + 6 dataset rows); the 9
  existing tests are unchanged.

### Regression coverage

Driven through the real writer with a frozen clock at microsecond precision
(`travelTo(Carbon::parse('2026-10-02 08:03:05.832677'))`, `started_at`
`2026-10-02 08:00:00` re-read from the database as in production → 185.832677 s):

1. Production value: asserts a precondition that the measured elapsed time is
   fractional; captures the `update … processing_seconds` statements via
   `DB::listen` and asserts none binds a float and that the integer `186` is
   bound; reads the stored values with the query builder (bypassing the casts)
   and asserts integer `186` in both tables; asserts the transcription is
   `Completed` with its text and `completed_at`, the segment is persisted, and
   the `ProcessingJob` is `Completed` with progress 100 and no failure code.
2. Rounding boundaries (dataset, 6 rows): 185.000000, 185.100000, 185.499999,
   185.500000, 185.999999 and 0.400000 → stored integer.

Fail-before / pass-after (measured): against the unmodified writer 6 of the 16
tests in the file failed (the production-value test: `Expecting 185.832677 not to
be float`; the boundary rows stored `185.1`, `185.499999`, `185.5`, `185.999999`
and `0.4` — REAL values in the INTEGER columns; the `185.000000` row passes
before the fix because `185.0` binds as `"185"`, which PostgreSQL also accepts).
After the fix 16/16 pass.

### Sibling-path audit

Repository-wide search (excluding vendor/node_modules) for `diffIn*`,
`floatDiffIn*`, `secondsSince/Until`, `microtime`, `hrtime`, and review of every
integer column fed by a computed value:

| Path | Classification | Evidence / action |
|---|---|---|
| `TranscriptionResultWriter` → `transcriptions.processing_seconds`, `processing_jobs.processing_seconds` | DEMONSTRATED SAME DEFECT | fixed in this cycle |
| `MediaMetadataProbeService` → `media_files.duration_seconds` | NOT AFFECTED | already `(int) round(…)` |
| `ProcessTranscription` / `ProcessTranslation` / providers `duration_ms` | NOT AFFECTED | `(int) round(…)`, logged only, no column |
| Python worker `processing_seconds` (`worker/main.py`, `round(…, 3)`) | NOT AFFECTED | not read or persisted by the Laravel side (`WorkerResponse*` carry no such field) |
| `DemoTranscriptionController`, seeders, factories | NOT AFFECTED | integer literals / `numberBetween` |
| `transcription_segments.start_seconds/end_seconds` | NOT AFFECTED | widened to `decimal(12,3)` in P3-005; fractional by design |
| `ProcessingJob.progress_percentage`, `media_files.sample_rate/channels/file_size_bytes`, revision `version/position`, `segment_index` | NOT AFFECTED | integer literals, counts or explicit `(int)`; no Carbon-derived value |
| `StagingClaimCasProtocolTest`, `diffForHumans()` in `CleanupStaging` | NOT AFFECTED | test assertion / console display, nothing persisted |
| Translation pipeline (`TranslationResultWriter`) | NOT AFFECTED | no elapsed-seconds column exists on translations |

No POTENTIAL PATTERN ONLY path was found.

### Verification (implementer-run, not independent)

All commands from the repository root via PowerShell (`php` is not on the Git
Bash PATH here). `phpunit.xml` forces `APP_ENV=testing` / `DB_DATABASE=:memory:`;
the `database/database.sqlite` modification time was checked unchanged after the
runs.

| Command | Result |
|---|---|
| `php artisan test --compact tests/Feature/Transcription/TranscriptPersistenceTest.php` (before fix) | 16 tests, 10 passed, **6 failed** (expected; reproduces the defect) |
| same command (after fix) | 16 tests, 16 passed, 69 assertions |
| `php artisan test --compact tests/Feature/Transcription` (persistence, segment, atomic-completion, orchestration, ProcessTranscription job, retry, claim/retry concurrency harnesses, bridge and Cycle 1 suites) | 169 tests, 169 passed, 737 assertions |
| `php -d memory_limit=2G vendor\bin\pest --compact` (full suite, run A) | 1302 tests, 1297 passed, 5 skipped, 0 failed, 4 warnings (no detail emitted by the reporter; none from the touched file) |
| `php -d memory_limit=2G vendor\bin\pest --stop-on-warning` (full suite, run B) | 1302 tests, 1296 passed, 1 error = `LogContextTest::it_counts_attempt_ordinals_across_attempts_for_the_same_transcription` (UNIQUE `processing_jobs.transcription_id`), the pre-existing random-factory flake recorded in `reviews/PHASE7-WAVE3A-INDEPENDENT-REVIEW.md`; that file passes 6/6 in six isolated reruns and does not touch the writer |
| `vendor\bin\pint --dirty --format agent` | passed |
| `vendor\bin\phpstan analyse --memory-limit=1G --no-progress` | 0 errors |

Not executed and not represented as PASS: any PostgreSQL run (no server
available; the PostgreSQL rejection is established from the driver binding
behaviour above, not reproduced); `Phase3IntegrationVerification` (needs live
Redis and real faster-whisper); AC14 on the target host; the PostgreSQL
concurrency checks already carried forward from Cycle 1. The P7-009 Phase A
capacity harness (`verification/p7-009/`) is not part of this corrective scope.

### Review requirements for the independent re-review

Independent context, not the implementer. Verify: (1) the root-cause chain
against source; (2) the rounding-rule decision above; (3) fail-before/pass-after
by reverting only the writer expression and re-running
`TranscriptPersistenceTest.php`; (4) no schema, dependency or other code change
(the diff is the writer expression, the test file and governance text);
(5) the sibling-path classification; (6) that AC14 stays NOT PASS until
re-executed on the target host from a new immutable release (the existing
release is not modified in place); (7) that Phase B, P7-007 and P7-012 remain
NOT AUTHORIZED.

### Governance state after the implementation cycle (historical; superseded by the reconciliation below)

`P7-009-CORR-01` = REVIEW — **CORRECTIVE IMPLEMENTATION COMPLETE; AWAITING
INDEPENDENT RE-REVIEW.** Not VERIFIED, not DONE. AC1–AC13 remain as previously
recorded; AC14 = first attempt FAILED, not PASS. P7-009 Phase B, the P7-007
restore drill and P7-012 remain NOT AUTHORIZED; `TARGET_HOST_NOT_READY` stands.
`DECISIONS.md` and `DECISION_QUEUE.md` were not edited by this cycle.

### Independent Re-Review, HPO Acceptance and Reconciliation (2026-10-02)

Authority: `DECISION-P7-009-CORR-01-CYCLE2-001` (HPO, 2026-10-02), recorded in
`DECISIONS.md` and `DECISION_QUEUE.md`. This closes the "no `DECISION-…` ID"
note in the Authority paragraph above and the governance lag flagged as F-2.

Lifecycle trail (continuation; history above is unchanged):

```
→ REVIEW: awaiting independent re-review
→ independent re-review (fresh context): VERIFIED
  reviews/P7-009-CORR-01-CYCLE2-INDEPENDENT-REVIEW.md
  BLOCKER 0 / HIGH 0 / MEDIUM 0 / LOW 1 / INFO F-2..F-7
→ HPO accepts the verdict; governance reconciled (this record)
→ VERIFIED (Corrective Cycle 2); not DONE; AC14 NOT PASSED
→ next: separately authorized commit/push → new immutable corrected release
  → separately authorized deployment → AC14 rerun on the target host
```

State: Corrective Cycle 2 technical status = **VERIFIED**. `P7-009-CORR-01` is
not DONE: `VERIFIED → DONE` is a separate HPO closure act
(`.ai/guidelines/orchestration-policy.md`), none was given, and AC14 — one of the
binding acceptance criteria — is NOT PASSED. Production AC14 = **NOT PASSED /
AWAITING CORRECTED RELEASE VALIDATION**: the corrected implementation is not
deployed and has not been exercised against production PostgreSQL. Corrective
Cycle 2 implementation may proceed to the next separately authorized
release/deployment step.

Accepted `processing_seconds` semantic (integer-seconds field retained, no
schema change): `(int) round($fractionalSeconds)` — 185.000000 → 185, 185.100000
→ 185, 185.499999 → 185, 185.500000 → 186, 185.832677 → 186, 185.999999 → 186.
This replaces the "flagged for independent review" status of the rounding choice
above: the reviewer approved it ("INTEGER NORMALIZATION VERDICT: APPROVED") and
the HPO accepted it as canonical.

Accepted non-blocking findings (source: Cycle 2 re-review §H):

| ID | Severity | Finding | Disposition |
|---|---|---|---|
| F-1 | LOW | `round()` may differ from the whole-second persisted `completed_at - started_at` by up to 1 second. | Accepted by the HPO as non-blocking; floor would be a new HPO decision (one-token change). |
| F-2 | INFO | Governance lag: no Cycle 2 decision record. | Resolved by this reconciliation. |
| F-3 | INFO | The `processing_jobs` UPDATE and post-persist code after the former failure point have never run on PostgreSQL for this path. | Evidenced only by the AC14 rerun. |
| F-4 | INFO | Test limits: SQLite only (PostgreSQL integer enforcement not exercised; bound-PHP-type assertion compensates); tests pin `round`. | Carried forward. |
| F-5 | INFO | Pre-existing, unchanged: `processing_seconds` derives from `Transcription.started_at` (not reset by retry), not the attempt's own `started_at`. | Carried forward; no work authorized. |
| F-6 | INFO | `LogContextTest` TD-008 random-factory flake (pre-existing; reproduced on the unfixed writer). | TD-008 OPEN in `docs/TECHNICAL_DEBT_REGISTER.md`; unchanged. |
| F-7 | INFO | `max(0, …)` redundant after the absolute diff. | Harmless pre-existing guard; unchanged. |

No PostgreSQL execution occurred locally; the PostgreSQL rejection is established
from source and driver-binding evidence. No follow-up task is created or started
by this record.

Boundary (unchanged): P7-009 Phase B = NOT AUTHORIZED; P7-007 = NOT AUTHORIZED;
P7-012 = FINAL_GATE_ONLY, NOT AUTHORIZED; `TARGET_HOST_NOT_READY` stands. Not
authorized by this reconciliation: any commit or push, any deployment, the AC14
rerun, marking AC14 PASS, closing the task as DONE, final production readiness.
