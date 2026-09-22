# P4-004 — Transcript Search + Copy

## Status

DONE

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: Claude Code (independent review; per AGENTS.md agent model)

## Authorization

This contract was authored under `DECISION-PHASE4-AUTHORIZATION-001` (Phase 4
authorized for task-contract authoring, 2026-09-19), audited/finalized on
2026-09-19, and authorized for implementation under
`DECISION-P4-WAVE1-AUTHORIZATION-001` (HPO, 2026-09-19): BACKLOG → READY. P4-001
is DONE, satisfying this task's dependency. Only P4-002, P4-004, and P4-005 are
authorized in Wave 1; P4-003 and P4-006 remain BACKLOG.

## Authorized Phase

Phase 4 — Transcript Experience baseline (ADR-019)

## Batch

Batch 1 (proposed; to be confirmed by the implementation authorization)

## Objective

Provide client-side in-transcript search with match identification/highlighting
and navigation, plus copy actions for the full transcript and for individual
segments, Unicode-safe for the supported languages.

## Context

- ADR-019; `PHASE4-PLANNING.md` (D4-04 decided)
- P4-001 (search/copy semantics and workspace read model)
- Persisted segments: `TranscriptionSegment.text`, per-segment `language`
- Supported languages: `ms`, `en`, `zh`, `ta`, `und`

## Scope

Implement only:

- client-side search over the currently loaded persisted segments (D4-04); no
  server request per keystroke, no database index;
- Unicode-safe matching across Latin, Chinese, and Tamil text;
- match identification, highlighting, result count, and next/previous
  navigation;
- copy full transcript and copy individual segment;
- read-only behavior.

## Search Semantics

- Case-insensitive for Latin text; substring matching (not whole-word).
- Unicode-safe for Chinese and Tamil; matching must not corrupt multi-byte text
  or split surrogate pairs.
- Literal substring matching; no whitespace normalization that shifts offsets.
- Empty query: no active search, zero matches, no highlights, count `0`.
- Result count is displayed.
- Next/previous navigation wraps around.
- Clearing search removes all search highlights.
- Match highlighting wraps matched substrings in a safe element using DOM
  text-node manipulation; transcript text must never be injected as HTML.
- Search highlights and the active playback highlight (P4-003) may coexist; the
  search highlight must not alter active-segment semantics.

## Copy Semantics

- Copy full transcript: plain text only, segments joined in `segment_index`
  order by a single newline; no timestamps.
- Copy individual segment: plain text of that segment only; no timestamp.
- Unicode preserved verbatim; copied text must equal persisted text.
- No-speech/empty transcript: copy is unavailable/no-op with accessible
  feedback.
- Clipboard failure is handled without throwing and with accessible feedback.
- No rich/HTML clipboard formats are required.

## Accessibility Baseline

- Search input and copy controls are keyboard operable with visible focus.
- Match count and copy success/failure are announced accessibly (e.g.
  `aria-live`).
- No regression of ordinary accessibility.

## Out of Scope

Do not implement:

- server-side full-text search or any search index;
- cross-transcript or global search;
- transcript or segment editing (D4-05; Phase 6);
- translation (Phase 5);
- export changes (P4-005);
- any schema/migration change.

## Dependencies

Requires:

- P4-001 (Transcript Experience Contract) — VERIFIED and closed DONE.

## Acceptance Criteria

1. Search operates client-side over the loaded persisted segments; no server
   request is issued per keystroke.
2. Search is case-insensitive for Latin and substring-based.
3. Search matches Chinese and Tamil substrings correctly.
4. An empty query produces zero matches, zero highlights, and count `0`.
5. The result count reflects the number of matches.
6. Matches are highlighted without HTML injection of transcript text.
7. Next/previous navigation moves between matches and wraps.
8. Clearing search removes all search highlights.
9. Search highlights do not change active-segment semantics.
10. Copy full transcript yields plain text, segments in `segment_index` order,
    newline-separated, no timestamps.
11. Copy segment yields only that segment's plain text, no timestamp.
12. Copied Unicode text equals persisted text for `ms`/`en`/`zh`/`ta`/`und`.
13. No-speech/empty transcript copy is a safe no-op with accessible feedback.
14. Clipboard failure is handled without an uncaught error.
15. Transcript content remains read-only.
16. Relevant tests pass; Pint clean; PHPStan 0 errors.
17. No unrelated functionality is changed.

## Test Requirements

Minimum tests:

- search match detection for Latin (case-insensitive), Chinese, and Tamil;
- empty query and no-match behavior;
- result count and navigation/wrap;
- safe highlight construction (no HTML injection from transcript text);
- copy full transcript ordering/newlines/no timestamps;
- copy segment content;
- Unicode round-trip for copy.

## Browser Verification

Search/copy state logic is covered by component/feature tests. Real clipboard
and DOM highlight behavior is verified at browser level where required (see
P4-006 §Browser Verification Strategy); backend tests must not be represented as
proving browser clipboard behavior.

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Unicode case/substring behavior | Explicit `zh`/`ta` tests; no locale-dependent folding |
| DOM injection when highlighting | Wrap text nodes only; never `innerHTML` with text |
| Clipboard failures | Try/catch + accessible fallback feedback |
| Search/highlight collision | Distinct layers; active state owned by resolver |
| Per-keystroke server load | Client-side only; assertion no server call |

## Implementation Notes

### Files Changed

- `app/TranscriptExperience/TranscriptCopy.php` (new) — plain-text copy
  assembly (ordered segments, single newline, no timestamps, Unicode verbatim).
- `app/Http/Controllers/TranscriptionController.php` — passes
  `$fullTranscriptText` to the view.
- `resources/views/transcriptions/show.blade.php` — client-side search and copy
  controls plus the inline Alpine `transcriptSearch` component.
- `tests/Unit/TranscriptExperience/TranscriptCopyTest.php` (new).
- `tests/Feature/TranscriptSearchCopyTest.php` (new).

### Important Decisions

- Search is client-side (Alpine) over the already-loaded persisted segments;
  no server request per keystroke and no database index (D4-04).
- Safe highlighting: match text is wrapped using `document.createTextNode()` and
  `<mark>` elements; transcript text is never injected as HTML.
- Copy: the full-transcript payload is assembled server-side by
  `TranscriptCopy::fullText()` (ordered, single newline, no timestamps) and
  copied via the Clipboard API with a `document.execCommand('copy')` fallback;
  failures are surfaced through an `aria-live` status without an uncaught error.
  Segment copy uses the persisted segment text only.
- Case-insensitive substring matching for Latin; Unicode-safe substring
  matching for Chinese and Tamil; empty query yields zero matches/highlights;
  navigation wraps.
- No search/copy controls are rendered when there are no segments
  (no-speech/empty state).

### Known Limitations

- DOM highlight and clipboard behavior are not unit-tested; the repository has
  no browser automation, so browser-level evidence is deferred to the
  P4-003/P4-006 strategy and is not falsely claimed here. Feature tests cover the
  rendered structure and copy payload; `TranscriptCopy` is unit-tested.
- The inline `window.transcriptSearch` is defined idempotently and relies on
  Alpine already being available (the view already used Alpine).

## Verification

Commands executed on 2026-09-19 (PHP 8.4, Pest 5.1):

- `vendor/bin/pest tests/Unit/TranscriptExperience/TranscriptCopyTest.php tests/Feature/TranscriptSearchCopyTest.php`
  → 7 passed, 19 assertions.
- Full wave suite: 422 tests, 421 passed, 1 skipped, 1400 assertions, 2 warnings.
- PHPStan 0 errors; Pint passed.

Result:

PASSED

## Review

Review File: `reviews/P4-004-independent-review.md`

Review Status: VERIFIED (independent review, 2026-09-20). No BLOCKER/HIGH/
MEDIUM findings. Non-blocking: LOW-1 (whitespace-only query is treated as a
real, non-empty search by the matching logic while the displayed count label
shows blank; contract only defines literal-empty-query behavior), LOW-2
(P4-004 does not consume the P4-001 `WorkspaceAvailability`/`WorkspaceState`
workspace read model; gating is reimplemented ad hoc via
`segments->isEmpty()`, behaviorally correct today only via the separately-
verified Phase 3 completed-segments invariant). Client-side-only search,
safe DOM text-node highlighting (no `innerHTML`/`x-html`), Unicode-safe
matching, correct wrap navigation, and guarded clipboard fallback were all
independently confirmed by direct source-code audit (no browser automation
exists in this repository; deferred per contract to P4-006). All reported
quality gates (focused suite 7/19, full suite 422/421/1/~1400/2, PHPStan 0
errors, Pint clean) were independently reproduced with an exact or
equivalent match. Eligible for Human Product Owner closure to DONE.

## Closure

Closed as DONE by the Human Product Owner on 2026-09-20
(DECISION-P4-004-CLOSURE-001), based on `reviews/P4-004-independent-review.md`
(P4-004 = VERIFIED; no BLOCKER/HIGH/MEDIUM; LOW-1/LOW-2 non-blocking,
retained).

Preserved limitation: real DOM/clipboard browser behavior is not proven by
browser automation; browser evidence follows
DECISION-P4-BROWSER-VERIFICATION-001 and is deferred to P4-006.

Canonical transition: VERIFIED → (HPO closure decision) → DONE. No
implementation or test change is authorized.

## Corrective Reopen (2026-09-20)

Post-closure corrective reopen authorized by `DECISION-P4-004-REOPEN-001`,
triggered by P4-006 final integration verification finding
`DECISION-P4-006-FINDING-001`.

Historical preservation: the original sequence (BACKLOG → READY → implementation
→ REVIEW → independent VERIFIED → HPO closure → DONE), the original independent
review (`reviews/P4-004-independent-review.md`), and the original Closure section
below remain valid at the time and are not rewritten. This reopen is based on
later real-browser evidence not available during the original review.

### Finding

Real-browser defects (P4-006): V4-18 segment copy ("Nothing to copy"; clipboard
empty) and V4-14 search next/previous current-match navigation (highlight does
not move).

### Root cause

`transcriptSearch` queried the DOM via `this.$el`. Alpine resolves the `$el`
magic to the element the current expression is bound to; inside child-element
`x-on` handlers that is the clicked button, not the `x-data` component root.
`this.rootEl.querySelector(...)` therefore searched inside the button and found
nothing. `copyFull()` and `refresh()` (from `$watch`) were unaffected, which is
why the original structure-only tests passed.

### Product correction

`resources/views/transcriptions/show.blade.php` (transcriptSearch only):
capture the component root once in `init()` (`this.rootEl = this.$el ?? this.$root`)
and use `this.rootEl` for the DOM queries in `segmentEls()`, `applyCurrent()`,
and `copySegment()`. No contract, security, or P4-003 behavior changed.

### Regression coverage

- `verification/p4-004/transcript-search-copy.spec.js` (new): search navigation
  advances/reverses/wraps; segment copy exact clipboard for Latin, Chinese, and
  Tamil; full-transcript copy unchanged.
- P4-006 V4-14 and V4-18 now PASS (corrective evidence only; P4-006 remains
  BLOCKED pending independent re-review of this correction).

### Corrective verification

- P4-004 PHP: 7 passed, 19 assertions.
- P4-003 PHP: 8 passed, 32 assertions. P4-001 30/117; P4-002 13/61; P4-005 21/116;
  Phase4IntegrationTest 3/19.
- Full suite: 433 tests, 432 passed, 1 skipped, 1453 assertions, 2 warnings.
- Pint passed; PHPStan 0 errors.
- Playwright: P4-004 regression 3 passed; P4-006 full suite 14 passed.

Result: corrective implementation complete; awaiting independent re-review. Not
VERIFIED/DONE.

### Corrective closure

Closed as DONE by the Human Product Owner on 2026-09-20
(DECISION-P4-004-CORRECTIVE-CLOSURE-001), based on
`reviews/P4-004-corrective-independent-re-review.md` (P4-004 corrective cycle =
VERIFIED; no BLOCKER/HIGH/MEDIUM; V4-14 and V4-18 PASS; P4-004 Playwright 3
passed; full P4-006 browser suite 14 passed; full PHP 433/432/1; Pint clean;
PHPStan 0 errors). Canonical transition: VERIFIED → (HPO closure decision) → DONE.
Original cycle, original review, and corrective review are all preserved.

### Corrective Independent Re-Review (2026-09-20)

Review File: `reviews/P4-004-corrective-independent-re-review.md`

Review Status: VERIFIED. No BLOCKER/HIGH/MEDIUM findings. Root cause
(Alpine `$el` resolving to the event-target element inside child-element
`x-on` handlers rather than the `x-data` root) independently confirmed as
correct and complete. Corrective diff (`this.rootEl = this.$el ?? this.$root`
in `init()`, used by `segmentEls()`, `applyCurrent()`, `copySegment()`)
independently confirmed as minimal, appropriate, and stable for this
component's lifecycle (plain controller-rendered view, no Livewire morph,
`x-show`-only tabs). V4-14 and V4-18 independently re-executed in a real
browser and both PASS; the full P4-006 browser suite (14/14) and P4-003
coexistence (search + active playback + copy + clear) were independently
re-verified with no regression. All reported quality gates (P4-004 PHP 7/19,
full suite 433/432/1/1453/2, Pint clean, PHPStan 0 errors, Playwright P4-004
3 passed, P4-006 14 passed) were independently reproduced with an exact
match. `DECISION-P4-006-FINDING-001` is technically resolved and eligible for
Human Product Owner closure; this review does not close the finding, does
not release P4-006, and does not mark P4-004 DONE.

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED. P4-006 depends on
this task being VERIFIED and closed DONE.
