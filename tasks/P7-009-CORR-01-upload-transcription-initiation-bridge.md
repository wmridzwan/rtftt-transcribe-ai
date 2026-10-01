# P7-009-CORR-01 — Real Upload → Transcription Initiation Bridge

## Status

**VERIFIED / ACCEPTED FOR CLOSURE** (HPO, 2026-10-02,
`DECISION-P7-009-CORR-01-CLOSURE-001`). Not DONE. **AC14 = NOT YET EVIDENCED**
(post-deployment, target-host only).

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
| 14 | Production-candidate verification after deployment: a real uploaded media file can progress Uploaded → Start Transcription → Queued → Running → Completed, with visible transcription output | NOT YET EVIDENCED — post-review, post-deployment target-host acceptance (new immutable release); not part of the implementation test run |

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
