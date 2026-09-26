# P6-008 — Independent Review (Revision History / Audit Surface)

Reviewer: Claude Code (independent reviewer role; did not implement P6-008).
This review was reconstructed fresh from repository evidence, not from the
Builder report's claims alone. Every quantitative claim below (test counts,
Pint, PHPStan, browser results) was independently re-executed in this review
session unless explicitly marked otherwise.

## A. Baseline

- HEAD: `d8a7e01ffe95b8cf5d2c857214ddee65a649c28d`
- Branch: `main`
- Working tree at review start: pre-existing uncommitted residue from Step
  A/B/C/C2, P6-005 closure, and P6-008 governance work (modified `AGENTS.md`,
  `BLOCKERS.md`, `CURRENT_STATE.md`, `DECISIONS.md`, `DECISION_QUEUE.md`,
  `plan.md`, `tasks/P6-005-split-merge-translation-invalidation.md`;
  untracked `docs/`, `reviews/P6-005-INDEPENDENT-REVIEW.md`,
  `verification/LARGE-V3-FULL-CHAIN-E2E.md`,
  `verification/REAL-MODEL-FULL-CHAIN-E2E.md`) plus the Builder-owned P6-008
  changes. None of this residue was reset, cleaned, or overwritten by this
  review. The P6-008 implementation is attributable and reviewable
  independently of the residue: `git diff --stat HEAD` isolates exactly 12
  changed/added paths, of which 7 are P6-008 application/route/view files and
  test file, and the remainder are pre-existing governance-doc residue
  unrelated to P6-008 code.

## B. Approved Scope

Reconstructed from `tasks/P6-008-revision-history-audit-surface.md`, cross-
checked against `DECISION_QUEUE.md` (`DECISION-P6-008-READY-001`, lines
4251–4322) and `DECISIONS.md` ("P6-008 READY Promotion", lines 2100–2131):

- HPO-008-A: arbitrary eligible same-transcription historical revision
  activation (no artificial recent-only/bounded-history restriction) —
  DECIDED.
- HPO-008-B: D6-05 lightweight audit surface only (list + active marker +
  persisted metadata) — DECIDED.
- HPO-008-C: retro-wiring P6-003/P6-004 staleness persistence — EXCLUDED —
  DECIDED.
- HPO-008-D: proceed — DECIDED.

The READY contract contains no unresolved candidate scope; all four owner
decisions are recorded DECIDED in both `DECISION_QUEUE.md` and `DECISIONS.md`
independently of the task file's own restatement. Scope is: a read-only,
version-ordered revision-history/audit surface with an active marker and
persisted metadata only, plus an `update`-authorized, CAS-fenced
explicit-activation operation that reuses the existing `RevisionRepository
::activate()` primitive.

## C. Acceptance Criteria Matrix

| AC | Requirement | Result | Evidence |
|---|---|---|---|
| AC1 | History list renders every durable revision in version order, unambiguously identifies active revision | PASS | `RevisionService::history()` delegates to the unmodified, order-by-version `EloquentRevisionRepository::historyFor()` (`app/Editing/Persistence/EloquentRevisionRepository.php:55-64`); Blade partial renders `data-history-version` per entry and `data-history-active` only on the matching row (`resources/views/transcriptions/partials/revision-history.blade.php:34-63`); independently re-run PHP test asserts v1 position < v2 position; independently re-run browser tests 1–2 confirm DOM order and the single active badge. |
| AC2 | Each entry shows only persisted audit metadata (version, author, created, parent, segment count, active); no fabricated field | PASS | Every displayed field traces to a persisted source: `version`/`createdAt`/`parentRevisionId`/`segmentCount()` from the `TranscriptRevision` domain object (unmodified P6-002 type); `historyAuthors()` reads `users.name` by id with an id-fallback, never fabricating a name (`TranscriptionController.php` new private method); `historyStalenessCauses()` reads only `stale_caused_by_revision_id`/`target_language` from persisted `translations` rows, no inference. Inspected line-by-line; no field lacks a persistence source. |
| AC3 | Arbitrary eligible activation via `update`+CAS; stale base rejected as conflict, no silent merge, no history rewrite | PASS | `RevisionService::activateHistorical()` authorizes `update`, defaults expected base to the persisted pointer, throws `RevisionConflictException::staleBase()` on mismatch *before* any write, then delegates the pointer move to the unmodified `EloquentRevisionRepository::activate()`, which re-checks the CAS condition again inside a `lockForUpdate` transaction (`app/Editing/Persistence/EloquentRevisionRepository.php:151-181`) — double-checked CAS, not a single check. No new revision row is created by activation (repository `activate()` only does `$transcription->active_revision_id = ...; save()`). Independently re-run PHP tests: linear activation (v1↔v2), sibling-branch activation beyond undo reach, and stale-base conflict all pass with unchanged row counts. Independently re-run browser tests 4–7 confirm the product-path activation, reload durability, sibling-branch activation, and the conflict path (no pointer move on tampered `expected_base`). |
| AC4 | Cross-transcription, invalid, and non-owner activation rejected | PASS | `activateHistorical()` rejects when `$target === null \|\| $target->transcriptionId !== $transcriptionId` with `InvalidArgumentException` before any write; the repository `activate()` independently re-checks `transcription_id` on the target row as a second fence. `Gate::forUser($user)->authorize('update', ...)` throws `AuthorizationException` for a non-owner, matching `TranscriptionPolicy::update()` (owner-or-admin), which is not weaker than the existing undo/redo/split/merge authorization pattern. Independently re-run PHP tests cover unknown-revision, cross-transcription, and non-owner (403 at HTTP, `AuthorizationException` at service) cases. |
| AC5 | `view`-fenced read; viewing never mutates | PASS | `TranscriptionController::show()` calls `RevisionService::history()`, which authorizes `view` (owner/admin) via the unmodified P6-002 method — P6-008 did not create a new read path or weaken this gate. Independently re-run PHP test asserts `updated_at` timestamp and revision row count are unchanged after a plain read; admin-view test passes. |
| AC6 | Undo/redo semantics unchanged | PASS | `git diff` on `RevisionService.php` is purely additive (`activateHistorical()` appended after `requireBase()`); `undo()`/`redo()` bodies are byte-identical to pre-P6-008 (confirmed by reading the surrounding, unmodified code at lines 245-320). `EloquentRevisionRepository.php`, `RevisionRepository.php` interface, and `TranscriptRevision.php` show zero diff (`git diff --stat` for those paths is empty). Independently re-run full PHP suite: 882 total / 881 passed / 1 pre-existing skip / 3340 assertions — matches Builder's reported figures exactly, with no regression. |
| AC7 | Machine-source state distinguishable | PASS | Blade partial renders a `data-history-machine-source` note both when no revisions exist and when the pointer is `null` with existing revisions (`revision-history.blade.php:22-31`), consistent with the frozen presentation convention already used elsewhere in the workspace. Independently re-run browser test 3 confirms. |
| AC8 | Translation-staleness presentation matches persisted P6-005 markers exactly | PASS | `historyStalenessCauses()` reads only the persisted `stale_caused_by_revision_id` column (added by the already-DONE, unmodified P6-005 schema) and does not touch `TranslationStalenessWriter` or any Phase 5 write path (`RevisionService`'s constructor-injected `stalenessWriter` is untouched by this diff — confirmed by inspecting the class header, which is pre-existing). Independently re-run browser test 8 confirms the history marker (`data-history-stale-cause="ms"`) agrees with the pre-existing toolbar marker (`data-translation-stale="ms"`) from the same persisted row. |
| AC9 | Full suite green, Pint clean, PHPStan 0, browser evidence retained | PASS | Independently re-executed: full `php artisan test --compact` → 882 total/881 passed/1 skipped/3340 assertions (matches Builder); `vendor/bin/pint --test --format agent` → clean; `vendor/bin/phpstan analyse` (memory_limit=1G) → 0 errors; fresh Playwright rerun of `verification/playwright.p6-008.config.js` against a freshly reseeded `database/p6-008-verification.sqlite` → 9/9 passed (10.2s), matching the committed `verification/p6-008/p6-008-browser-results.json` test-for-test. |
| AC10 | No unauthorized expansion; diff audit | PASS | See §I below. |

No AC is collapsed into a single verdict; each was evaluated on its own evidence.

## D. Findings

No BLOCKER, MAJOR, or MINOR finding.

- **OPTIONAL-1** — `historyAuthors()`/`historyStalenessCauses()` run one extra
  query each per workspace `show()` render (users lookup, translations
  lookup). At current transcript/segment scale this is negligible and matches
  the existing pattern of per-request read-model assembly already used by
  `comparison->build()` on the same page. Not a defect; noted only as a future
  optimization candidate if history lists grow large. Non-blocking.

## E. Authorization Review

PASS. `history()` requires `view` (owner or admin, `TranscriptionPolicy::view`)
and `activateHistorical()` requires `update` (owner or admin,
`TranscriptionPolicy::update`) — the same policy class and same gate calls
used by the pre-existing, previously-verified undo/redo/split/merge
operations (`RevisionService.php`, `TranscriptRevisionController.php`). Both
enforcement points were independently exercised: HTTP-level (403 for
non-owner GET and POST) and service-level (`AuthorizationException` for
`activateHistorical` and `history`) in the re-run
`RevisionHistoryActivationTest.php`. Admin allow-path is separately tested and
passes. No bypass path was found — the controller calls `$this->authorize()`
before any request-body work, and the service independently re-checks via
`Gate::forUser()`, so a controller-level bypass would still be caught at the
service boundary (defense in depth, matching the existing undo/redo/split
pattern exactly).

## F. CAS / Concurrency Review

PASS. `activateHistorical()` performs a first compare against the currently
persisted pointer (read-then-compare in the service), then delegates to
`EloquentRevisionRepository::activate()`, which performs the authoritative
second compare-and-set inside `DB::transaction(..., 5)` with
`Transaction::lockForUpdate()` on the transcription row — the same,
unmodified primitive independently verified in the P6-002 closure. A stale
`expected_base` is rejected before any write at the service layer, and the
repository re-verifies the live pointer under lock before writing, so a
race between the service's read and the repository's write is still caught
(no TOCTOU gap: if a concurrent writer changes the pointer between the
service's check and the repository's lock acquisition, the repository's own
compare against the now-stale `$currentRevisionId` argument will fail and
throw `RevisionConflictException`). Row count and pointer are asserted
unchanged on conflict in both the automated test and the fresh browser
re-run. Genuine independent-process race coverage for the underlying
`activate()` primitive remains in the untouched P6-002 test suite (not
re-derived here, consistent with "P6-008 exposes, does not redefine, this
primitive" — re-verifying two-process races on an unmodified primitive is
out of P6-008's diff scope). This is not merely a passing happy-path test:
the stale-base test explicitly forces a conflict via a prior `undo()` call
and independently asserts pointer and row-count invariants hold.

## G. Browser Evidence Review

PASS. The evidence in `verification/p6-008/P6-008-BROWSER-VERIFICATION-EVIDENCE.md`
and `verification/p6-008/p6-008-browser-results.json` was independently
reproduced in this review: freshly reseeded `database/p6-008-verification.sqlite`
via `verification/p6-008-seed.php`, a freshly started real PHP dev server
(`verification/p4-003-server-router.php`), and a fresh `node_modules/.bin/playwright.cmd
test -c verification/playwright.p6-008.config.js` run — 9/9 passed, test-for-test
matching the committed evidence file's spec titles and outcomes. The evidence
covers real workspace rendering, an active marker, historical activation
through the actual form submission (not a mocked request), reload durability,
sibling-branch activation, a tampered-`expected_base` conflict path submitted
through the real product form, staleness-marker cross-check against the
existing P6-005 toolbar marker, and non-owner 403 fencing. The evidence
belongs to P6-008: dedicated fixtures/config/spec files under
`verification/p6-008/` and `verification/playwright.p6-008.config.js`, not
reused from an unrelated Phase 4/5/6 run (the shared `p4-003-server-router.php`
is generic router-only infrastructure, not test evidence, and is not modified
by this diff).

## H. Test Results

Fresh, independently executed in this review session (not solely relying on
the Builder's reported numbers):

- `php artisan test --filter=RevisionHistoryActivationTest --compact`:
  12/12 passed, 66 assertions.
- Full `php artisan test --compact`: 882 total, 881 passed, 1 skipped
  (pre-existing 2FA skip), 3340 assertions, 2 warnings (pre-existing) —
  identical to the Builder's reported figures.
- `vendor/bin/pint --test --format agent`: `{"tool":"pint","result":"passed"}`.
- `vendor/bin/phpstan analyse` (memory_limit=1G, PHPStan level per
  `phpstan.neon`): `{"tool":"phpstan","result":"passed","errors":0}`.
- Playwright `verification/playwright.p6-008.config.js`: 9/9 passed (10.2s),
  freshly reseeded database, freshly started server — not a re-read of the
  committed JSON.

No test category was left un-rerun; every item in the READY contract's
Verification Requirements was independently reproduced by this reviewer.

## I. Diff Audit

`git diff --stat HEAD` isolates exactly these Builder-owned application/test
paths: `app/Editing/RevisionService.php` (+56, purely additive method),
`app/Http/Controllers/TranscriptRevisionController.php` (+57, one new action
+ three new private response helpers, mirroring the existing undo/redo/
conflict/error/notice pattern), `app/Http/Controllers/TranscriptionController.php`
(+65, two new private read-model helpers wired into the existing `show()`),
`resources/views/transcriptions/show.blade.php` (+1 include line),
`routes/web.php` (+2, one new POST route), plus the new test file and the
new `resources/views/transcriptions/partials/revision-history.blade.php` and
`verification/p6-008*` artifacts (untracked, additive). No migration file, no
`config/*.php` file, no `.env`/environment file, and no P6-009 file appears
anywhere in the diff or the untracked-file list. `EloquentRevisionRepository.php`,
`RevisionRepository.php`, `TranscriptRevision.php`, `RevisionConflictException.php`,
and all pre-existing P6-001..P6-007 test files show zero diff. AC10 is
independently confirmed, not merely trusted from the Builder's own table.

The remaining changed/untracked paths in the working tree
(`AGENTS.md`, `BLOCKERS.md`, `CURRENT_STATE.md`, `DECISIONS.md`,
`DECISION_QUEUE.md`, `plan.md`, `tasks/P6-005-*.md`, `docs/`,
`reviews/P6-005-INDEPENDENT-REVIEW.md`, `verification/*-E2E.md`) are
pre-existing governance residue from prior P6-005/Step-A work, not
Builder-owned P6-008 changes, and are outside this review's scope.

## J. Already-Active No-Op Verdict

**Accepted.** Activating the currently-active revision is itself an eligible
target under HPO-008-A ("arbitrary eligible same-transcription activation");
it is not excluded by any ancestry or freshness restriction. Returning success
with no write and no notice-of-error is consistent with treating the
operation as idempotent pointer-assignment rather than a state-transition
command: the postcondition the caller asked for ("target X is active") is
already true, so there is nothing to reject and nothing to conflict against.
This preserves every invariant — no revision is created, the pointer and row
count are provably unchanged (verified in the automated test), and no CAS
check is skipped (the stale-base check still runs and can still reject a
concurrently-stale request before the no-op branch is reached). It does not
create a hidden mutation and is not contractually ambiguous under AC3's
"succeeds through an update-authorized, CAS-fenced operation" language,
since success-with-no-write is a valid form of success for an already-true
postcondition. No finding is raised.

## K. Lifecycle

previous: `IMPLEMENTED_PENDING_REVIEW`

result: **VERIFIED**

## L. Final Verdict

**VERIFIED**

All AC1–AC10 PASS. No BLOCKER/MAJOR/MINOR finding. No unauthorized scope
expansion. Full suite, Pint, PHPStan, and browser evidence were independently
reproduced fresh in this review session and match the Builder's reported
results exactly.

## M. Files Changed (review-owned)

This review created only this file:
`reviews/P6-008-INDEPENDENT-REVIEW.md`. No implementation, test, or task file
was modified by this review. The verification database created during
independent reproduction (`database/p6-008-verification.sqlite`) is
gitignored (matching the existing `p4-003-verification.sqlite` ignore
pattern) and is reproducible scratch output, not a durable review artifact.

## N. Exact Next Legal Action

**HPO reviews this independent verdict (VERIFIED) and, if concurring, closes
P6-008 as DONE.** This review does not close P6-008 as DONE, does not start
P6-009, and does not authorize any Phase 7 work.
