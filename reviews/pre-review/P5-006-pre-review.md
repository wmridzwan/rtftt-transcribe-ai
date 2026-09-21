# P5-006 — Internal Adversarial Pre-Review

Task: P5-006 — Translation Workspace UI
Date: 2026-09-21
Reviewer: Claude Code as implementation owner (internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification. The implementer also authored the
preceding independent review of P5-003/002B/004/005, so an independent review by a different
reviewer is required.

## Acceptance criteria

| AC | Result | Evidence |
|---|---|---|
| 1. Select a target and initiate translation | PASS | Feature tests (start, invalid target, not-completed); browser tests 1–2 |
| 2. Progress and failure states accurate and accessible | PASS | `role=status` progress, `role=alert` failure; state derived only from persisted rows; live queued→translating→completed (browser 10); `awaiting-dispatch` state for a stranded queued row |
| 3. Toggle without mutating the source | PASS | Feature test (source rows identical after render); browser 5 (DB rows identical after toggling) |
| 4. Copy full and per segment | PASS | Browser 6 (real clipboard, Unicode) |
| 5. Unicode round-trips verbatim | PASS | Feature test (ta/zh); browser 4, 6, 7, 9 (zh, ta, ms) |
| 6. Ownership isolation, cross-user denied | PASS | Feature tests for all four routes; browser 17 with a verified second user |
| 7. Browser evidence, tests, Pint, PHPStan | PASS | `P5-006-BROWSER-VERIFICATION-EVIDENCE.md`; see Evidence |

## HPO-mandated rules

- **Retry only when the canonical taxonomy says retryable:** the view's `canRetry` comes from
  `TranslationRetry::isEligible()` on the row loaded for that request; the retry action
  re-checks on a **reloaded** row after authorizing. Non-retryable failures render no retry
  form; a hand-crafted POST is refused (feature + browser 12).
- **Authorization at the action/controller layer, before any service call, then reload, then
  eligibility:** `StartTranslationRequest::authorize()` (runs before validation),
  `TranslationActionController::retry()`, `TranslationWorkspaceController::show()/status()`.
- **No sensitive leakage:** failure text comes from `TranslationFailure::userMessage()` (fixed
  strings); exception messages/causes are never rendered; a test asserts no message contains
  a token/endpoint/`Bearer`; the browser run asserts the failed page contains no worker token,
  worker URL, or stack text. The failure code is shown as a reference and remains persisted.

## Adversarial checks (mutation evidence, each fence removed in place then restored)

| Mutation | Tests failing |
|---|---|
| `retry()` `authorize` removed | 1 |
| Start request authorization removed | 2 |
| `show()` `authorize` removed | 1 |
| `status()` `authorize` removed | 1 |
| `canRetry` gating removed | 6 |
| retry eligibility re-check removed | 1 |

Also: authorization runs before validation (a stranger posting an invalid target gets 403, not
a validation error); a stale page cannot force a retry (the persisted failure changed to
non-retryable between render and click → refused, state untouched).

## Files not touched (baseline hygiene)

`routes/web.php` (dirty baseline) is untouched: routes live in `routes/translation.php`. The
only edit to a dirty baseline file is one `@if ($canExport)` Translate link in
`resources/views/transcriptions/show.blade.php`; the commit stages a HEAD-based blob containing
only that change so no Phase 3/4 baseline hunk is swept in.

## Evidence

- Full suite: 606 tests, 605 passed, 1 skipped (pre-existing), 2 warnings.
- Workspace Pest file: 39 tests, 272 assertions. PHPStan 0 errors. Pint clean on changed files
  (run with explicit paths, never `--dirty`, to avoid reformatting the dirty baseline).
- Playwright: 18/18 passed (see the evidence file).
- Not run: the real provider/model gate (P5-008).

## Residual findings (for the independent reviewer / HPO)

1. **`PERSISTENCE_FAILED` is non-retryable, so under the HPO's "no Retry for non-retryable"
   rule a transient database-lock failure while saving a result becomes a permanent dead end
   for that (transcript, target).** The taxonomy is the frozen P5-001 contract; changing it is
   an HPO decision. Recommend considering `PERSISTENCE_FAILED` as retryable.
2. **Job `$timeout` / `failed()` handler and the worker `--timeout`/`retry_after` runbook**
   remain open (deferred to P5-008); a killed job is recovered only by the explicit
   `translation:recover-stale-attempts` command, which nothing schedules.
3. **Pre-existing, not P5-006:** transcript-page `showRenameModal` ReferenceError in the browser.
4. **Zero-segment transcript** can be translated (one wasted call, empty completed translation).
5. The workspace polls every 2 s while a translation is queued/translating, including in a
   background tab; acceptable for Phase 5, a Phase 7 capacity item.
6. Copy uses the async Clipboard API (secure context: HTTPS or localhost). No fallback for
   plain-HTTP remote hosts.
7. The Playwright concurrency check is browser-level on a single-threaded PHP server; true
   parallelism is covered by the two-process Pest test.
8. `verification/` is entirely untracked baseline (P4 harness files included); only the
   `p5-006*` files were committed. Generated files (`p5-006-fixtures.json`, `artifacts/`) are
   not committed.

## Verdict

PRE_REVIEW_PASS. Eligible for `IMPLEMENTED_PENDING_REVIEW`; independent review by a different
reviewer is required.
