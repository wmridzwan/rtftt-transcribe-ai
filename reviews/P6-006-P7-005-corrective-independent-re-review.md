# REVIEW - P6-006 + P7-005 - Corrective Independent Re-Review

## Review Status

COMPLETE

## Task

Task Files: tasks/P6-006-advanced-navigation-search-filter.md, tasks/P7-005-observability-foundation.md

Implementation Owner: OpenCode
Reviewer: Claude Code (independent, fresh session — no reliance on implementer's corrective report)

Prior artifacts consulted: reviews/P6-006-independent-review.md (CHANGES_REQUESTED),
reviews/P7-005-independent-review.md (CHANGES_REQUESTED), commit 51fd233 (both original
reviews), commit 61facd7 (P6-006 corrective), commit 10e4448 (P7-005 corrective).

## Review Scope

Independent reconstruction of evidence: task contracts, original review findings, corrective
diffs, current source, current tests, committed browser-verification harnesses (re-executed
live, not just read), and repository state as of branch `phase5-7/parallel-2026-09-21`.

## Findings

### BLOCKER

None.

### HIGH

None.

### MEDIUM

None.

### LOW / INFO

1. **[INFO] Full PHP suite has pre-existing order-dependent flakiness, unrelated to either
   task.** Two independent full-suite runs on the current tree produced different failure
   sets each time (`MediaManagementTest` file-size/Flysystem-on-Windows issue,
   `ProcessTranscriptionJobTest` state-leak failures on one run; `TranscriptionQueueOrchestrationTest`
   / `TranscriptionRetryTest` failures reported by a parallel evidence-gathering pass on
   another run). Neither failing test's file was touched by 61facd7 or 10e4448, and every
   failing test passes when run in isolation or as its own file. This is a suite-hygiene issue,
   not a regression introduced by either corrective commit. Recommend a follow-up task to
   find the shared/static state causing cross-test leakage, but it does not block either
   task here.
2. **[INFO] ADR-017 `device` field absent from `LogContext` output.** `DECISIONS.md`
   (ADR-017 minimum correlation fields) lists `device` alongside `request_id`,
   `attempt_number`, `duration_ms`, etc. `device` is not emitted anywhere in the app (not
   just in P7-005's `LogContext`) — it isn't tracked anywhere in the transcription domain.
   P7-005's task file explicitly places "ADR-017 minimum correlation fields; P3
   logging/job contracts" in **Non-Scope**, and its Scope only requires "the ADR-017
   minimum correlation fields where they are available" — `device` is not available. Not
   a P7-005 defect; carry forward as an input to whichever task introduces device/compute
   metadata (see Handoff section).

## Acceptance Criteria Verification — P6-006

- [x] Segment rows use their own dedicated selectors (`data-filter-language`,
      `data-nav-seconds`), leaving `data-seek-seconds` / `data-segment-language` unique to
      the original P4 seek button / language badge — VERIFIED (grep + live Playwright run).
- [x] Filter count reflects the currently visible set immediately on every language change,
      including repeated switches — VERIFIED (code inspection: `filteredCount` computed
      synchronously in the same pass that sets `row.hidden`; live Playwright test "language
      filter count reflects the visible set immediately" passed).
- [x] Keyboard navigation uses stable `segment_index` identity, independent of playback's
      `aria-current`, and does not trap/skip on overlapping or zero-length segments —
      VERIFIED (code inspection of reordered `resolveNavIndex()`; live Playwright test
      "overlapping and zero-length timings never trap or skip navigation" passed against a
      dedicated `overlap` fixture).
- [x] No-media keyboard navigation gives real, non-faked visible/announced feedback —
      VERIFIED (live Playwright test "without playable media navigation still shows a
      distinguishable selection" passed; asserts zero media players present and
      `aria-current` stays null, i.e., playback is not faked).
- [x] Committed browser harness is independently reproducible — VERIFIED. I ran the full
      documented runbook from a clean shell (seed → server → Playwright) myself; see Test
      Verification below.
- [x] Modifier-key guards and accessibility hint association — VERIFIED (live Playwright
      tests "modifier combinations and typing do not trigger navigation" and "navigation
      instructions are associated with the transcript region" both passed).
- [x] Read-only, independent of translation/revision/split-merge — VERIFIED. `git show
      61facd7 --stat` touches only the Blade view, the task file, one Pest test, and
      `verification/*` — no translation/revision/split-merge/schema files.

## Acceptance Criteria Verification — P7-005

- [x] Real emitted translation/transcription failure records carry `failure_code`,
      transcription/translation identity, target language/model, correlation context —
      VERIFIED (code inspection of `LogContext::forTranslation`/`forTranscription` calls in
      `ProcessTranslation.php`/`ProcessTranscription.php`; `tests/Feature/Observability/JobLogRecordTest.php`
      asserts the actual emitted context array, not a helper-only unit test).
- [x] Observability enrichment failures are non-fatal to the underlying job — VERIFIED.
      `LogContext::forTranscription/forTranslation/attemptNumber` are wrapped in
      try/catch returning safe defaults; a feature test injects a DB failure in the
      attempt-ordinal lookup and asserts the transcription job still completes.
- [x] Distinct, non-overwriting correlation identifiers — VERIFIED. `http_request_id`
      (HTTP correlation, `AssignRequestId::ATTRIBUTE`), `request_id` (ADR-017 worker
      transport id, untouched), and `queue_job_id` (`$this->job?->getJobId()`) are three
      separate keys built by a `correlationContext()` helper that only adds, never
      overwrites. HTTP-to-job propagation is explicit (`httpRequestId` passed as a job
      constructor argument at dispatch time, captured once via
      `AssignRequestId::currentId()`), not implicit/global state leaking into jobs.
- [x] `X-Request-Id` present on 404 / 419 / `/up` / matched routes — VERIFIED both by
      reading `bootstrap/app.php` (`$middleware->prepend(AssignRequestId::class)`) and by
      the new feature tests exercising the real Laravel HTTP kernel for each of those
      response types. Middleware is response-header-only (sets an attribute + a response
      header); it does not touch session/auth/CSRF logic, so no authorization-behavior risk
      from the placement change.
- [x] Strict id validation, `--probe-worker`, retention documentation — VERIFIED (regex
      anchor `\z` fix with a dedicated trailing-newline test; `--probe-worker`/`--strict`
      flags exist with passing tests; `LOG_STRUCTURED_DAYS` retention documented in
      `OBSERVABILITY.md` and `config/logging.php`).
- [x] No secret/transcript/media payload leakage — VERIFIED by inspection; log context
      fields are limited to ids, enum values, language/model names, and timings.
- [x] Remains product-semantic-neutral / within early-hardening scope — VERIFIED. No
      product behavior, authorization, or transcription/translation domain logic changed;
      all changes are logging context, a global response header, and job constructor
      metadata.

## Test Verification

Tests reviewed: `tests/Feature/TranscriptNavigationFilterTest.php`,
`tests/Feature/TranscriptPlaybackTest.php`, `tests/Feature/Observability/JobLogRecordTest.php`,
`tests/Feature/Observability/RequestCorrelationTest.php` (and related LogContext/diagnostics
tests), `verification/p6-006/advanced-navigation-filter.spec.js`,
`verification/p4-004/transcript-search-copy.spec.js`, `verification/p4-006/phase4-integration.spec.js`,
`verification/p4-003/transcript-playback.spec.js`.

Commands independently executed by this reviewer (fresh shell, this session):

- `vendor/bin/pint --test --format agent` → passed, 0 issues.
- `vendor/bin/phpstan analyse --memory-limit=1G` → passed, 0 errors.
- `php artisan test --compact` (full suite) → run twice; 661 tests, first run 657 passed /
  3 failed (unrelated tests, see LOW #1), second run 660 passed / 1 skipped / 0 failed.
  No failure in either run touched a file changed by 61facd7 or 10e4448.
- `php artisan test --compact tests/Feature/TranscriptNavigationFilterTest.php` and
  `TranscriptPlaybackTest.php` → both fully green in isolation.
- P6-006 browser harness, executed end-to-end exactly per
  `verification/p6-006/README.md`: seeded `database/p6-006-verification.sqlite`, started
  `php -S 127.0.0.1:8123 -t public verification/p4-003-server-router.php`, ran
  `playwright test -c verification/playwright.p6-006.config.js` → **7/7 passed**
  (independently reproduced, not taken from the implementer's report).
- P4-004 regression suite (seeded `p4-006-verification.sqlite`, same server pattern) →
  **3/3 passed**.
- P4-006 regression suite (same DB) → **13/14 passed**; the one failure (`V4-08 real audio
  playback via authorized stream`, `currentTime` stays `0`) matches the documented,
  pre-existing environmental audio flake. Critically, the H-1 regression-surface tests
  (`V4-01`, `V4-10`, `V4-11`, `V4-35`) all passed.
- P4-003 regression suite (seeded `p4-003-verification.sqlite`) → the serial suite aborted
  after the same audio-flake failure (9 tests reported "did not run" due to
  `describe.serial`); re-ran the seek-locator test in isolation
  (`--grep "timestamp button seek targets exact persisted milliseconds"`) → **1/1 passed**,
  confirming the H-1 regression surface is intact independent of the unrelated audio flake.
- Reverted all fixture/result files (`verification/p4-003-fixtures.json`,
  `verification/p4-006-fixtures.json`, `verification/p6-006/p6-006-browser-results.json`)
  regenerated by these runs back to their committed state; no working-tree changes left
  behind by this review.

Result: PASS (with the pre-existing, unrelated flake noted above, itself independently
reproduced and diagnosed as environmental/order-dependent, not caused by either corrective
commit).

## Architecture Review

Status: PASS

Notes: P6-006 remains confined to the transcript-show view/JS and its own verification
harness — no schema, translation, revision, or split/merge code touched. P7-005 remains
confined to logging context, a global response-header middleware, and job dispatch
metadata — no domain/status/lifecycle code touched. Both stay within their task contracts'
declared Scope/Non-Scope boundaries.

## Security and Authorization Review

Status: PASS

Notes: `AssignRequestId` only reads/sets a request attribute and a response header; it does
not gate, redirect, or short-circuit requests, so global (prepended) registration carries no
authorization risk — confirmed both by code reading and by the passing 404/419 tests (a CSRF
failure still returns 419, not something else). No secrets, tokens, transcript text, or media
payloads appear in any log context reviewed.

## Regression Risk

Status: PASS

Notes: See Test Verification. The only observed suite instability is pre-existing and
unrelated to files touched by either corrective commit (confirmed by diffing which files
61facd7/10e4448 touch against which tests fail, and by each failing test passing in
isolation).

## Required Changes

None.

## Reviewer Conclusion

### Verdict Table

| Task | Verdict |
|---|---|
| P6-006 — Advanced Navigation + Search/Filter | VERIFIED |
| P7-005 — Observability Foundation | VERIFIED |

### Closure status of every original HIGH/MEDIUM finding

**P6-006**
- H-1 (Phase 4 selector collision) — CLOSED. Row hooks renamed to `data-filter-language`/
  `data-nav-seconds`; `data-seek-seconds`/`data-segment-language` unique again; confirmed
  live via P4-003/P4-004/P4-006 suites.
- M-1 (filter count staleness) — CLOSED. Count computed synchronously with `hidden`
  assignment; live test confirms immediate correctness across repeated switches.
- M-2 (overlap/zero-length navigation trap) — CLOSED. Stable `segment_index`-based
  resolution now takes priority over playback's `aria-current`; live test with a dedicated
  overlap/zero-length fixture confirms no trap/skip.
- M-3 (no-media feedback) — CLOSED. Dedicated nav highlight + `aria-live` status region,
  decoupled from playback; live test confirms visible/announced feedback with zero media
  players present and no faked `aria-current`.
- M-4 (browser reproducibility/evidence durability) — CLOSED. Harness files are tracked
  (not gitignored); this reviewer independently reproduced the full documented runbook
  from scratch and got the same results the implementer reported.

**P7-005**
- H-1 (failure logs not enriched) — CLOSED. Verified via code and a feature test asserting
  the actual emitted `Log::spy()` context, not a helper-only test.
- M-1 (observability must be non-fatal) — CLOSED. try/catch isolation verified by code and
  by a feature test that injects a DB failure into the enrichment path and confirms the job
  still completes.
- M-2 (correlation semantics/overwrite risk) — CLOSED. Three distinct, additive keys;
  explicit bounded HTTP→job propagation via constructor argument, not ambient state.
- M-3 (response coverage) — CLOSED. Global (prepended) middleware; verified present on
  404, 419, `/up`, and matched routes both by reading registration and by live-kernel
  feature tests; no authorization impact.

### Residual findings

Two INFO-level items (see Findings), neither blocking: (1) pre-existing, unrelated
test-suite order-dependent flakiness worth a follow-up hygiene task; (2) ADR-017's `device`
field is absent everywhere in the app, not just P7-005 — explicitly out of P7-005's scope,
carry forward to whatever task introduces compute-device tracking.

### Independence / scope

- P6-006 remains genuinely independent of translation/revision/split-merge/schema work —
  confirmed by diff scope.
- P7-005 remains within its early-hardening authorization — no product-semantic,
  authorization, or domain-lifecycle changes found.

### Evidence independently reproduced by this reviewer

Pint, PHPStan, full Pest suite (x2), targeted P6-006/P4 Pest tests, and — critically — the
entire committed P6-006 Playwright harness plus the P4-004/P4-006/P4-003 regression
Playwright suites, executed live from a fresh shell against freshly seeded databases,
not read from the implementer's report.

### HPO closure

Both tasks may proceed to Human Product Owner closure as DONE. This review does not
close either task itself.

### Carry-forward input for P6-001/P6-002 and later Phase 7 work

- `data-seek-seconds` and `data-segment-language` are now confirmed-reserved Phase 4
  hooks; any editing/revision/split-merge UI must not reuse them on container/row elements.
- `http_request_id` / `request_id` / `queue_job_id` naming is settled for now but the
  P7-005 task file itself flags that later Phase 7 metrics/tracing work must still reconcile
  final naming conventions — do not treat this as permanent without re-confirming.
- ADR-017's `device` field has no home yet in the domain model; whichever task next touches
  transcription provider/worker metadata should decide whether/where to add it.

## Handoff

Do not mark either task DONE (Human Product Owner action). Do not start P6-001/P6-002 or
additional Phase 7 work as a result of this review.
