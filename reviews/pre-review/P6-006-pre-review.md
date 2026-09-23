# P6-006 — Builder Pre-Review Handoff

Date: 2026-09-23
Task: P6-006 — Advanced Navigation + Search/Filter
Status: `IMPLEMENTED_PENDING_REVIEW`
Authority: `DECISION-P6-006-AUTHORIZATION-001`; `DECISION-PHASE6-AUTHORIZATION-001`;
`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025 (D6-07, DC-01)
Reviewer: Claude Code (fresh independent review required)

## Independence confirmation (early-start condition)

Confirmed in `tasks/P6-006-advanced-navigation-search-filter.md`: no translation
invalidation semantics; no revision ownership assumptions beyond frozen contracts;
no dependency on P6 comparison/split-merge. P6-006 builds only on Phase 4
primitives.

## What changed

1. `resources/views/transcriptions/show.blade.php`
   - segment rows now expose `data-segment-language` and `data-seek-seconds`
     (additive);
   - the search component gained `languageFilter`, `applyFilter()`, `filterLabel`,
     `visibleRows()`, visible-only `segmentEls()`, and keyboard navigation
     (`navigateNext/Previous`, `jumpToFirst/Last`, `activateRow`);
   - the transcript region is focusable (`tabindex="0"`, `role="region"`) with
     Arrow Up/Down, J/K, Home/End handlers dispatching the existing `p4-seek`
     event so `transcriptPlayback` keeps active-highlight ownership;
   - a labelled language `<select>` and a keyboard hint were added.
2. `app/Http/Controllers/TranscriptionController.php` — computes the distinct
   per-segment languages in canonical order for the filter options.

## Deliberate design points

- DOM queries use the captured component root (never `this.$el` in handlers),
  preserving the P4-004 corrective invariant.
- Search highlights stay a separate layer from active-segment highlighting.
- No `innerHTML`/`x-html` introduced; segment text remains text nodes.
- Filtering re-syncs the search match set so counts exclude hidden rows.
- The no-speech workspace does not emit any `data-seek-seconds` literal (the
  navigation code reads `row.dataset.seekSeconds`), preserving the P4-003/P4-006
  no-timestamp-controls assertion.

## Quality / evidence

- Full PHP suite: `643 tests, 642 passed, 1 skipped, 2 warnings, 0 failures`
  (was 626; +17 new P6-006/P7-005 tests).
- New feature tests: `tests/Feature/TranscriptNavigationFilterTest.php` (6 tests).
- Pint clean; PHPStan 0 errors.
- Browser (DC-01): `verification/playwright.p6-006.config.js` +
  `verification/p6-006/advanced-navigation-filter.spec.js` executed against real
  Chromium + real Laravel: **2 passed** (keyboard navigation and language
  filter/search re-sync). Fixtures: P4-006 verification fixtures.

## Requested fresh-review focus

- keyboard navigation moves/updates the active segment and does not fire from the
  search input;
- language filter hides non-matching rows and re-syncs search counts;
- no translation/revision/split-merge coupling was introduced;
- the P4-004 `rootEl` invariant and P4-003 no-timestamp-controls surface hold;
- browser evidence is a genuine real-browser run, not a double.

P6-006 is not VERIFIED and not DONE. The implementer must not self-verify.
