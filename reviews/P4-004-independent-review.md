# P4-004 — Independent Review: Transcript Search + Copy

Reviewer: Claude Code (independent reviewer role per AGENTS.md / ADR-015 /
`.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-20
Scope: `tasks/P4-004-transcript-search-copy.md` only. This review does not
implement fixes, does not modify implementation code or tests, does not mark
P4-004 DONE, and does not authorize P4-003 or P4-006. It is independent of the
P4-002 and P4-005 reviews conducted alongside it in the same session; each
task's verdict stands on its own contract. P4-004's pass does not compensate
for, and is not compensated by, either sibling task's result.

## 1. Contract Checked

`tasks/P4-004-transcript-search-copy.md` (Status at review start: `REVIEW`;
authorized under `DECISION-P4-WAVE1-AUTHORIZATION-001`; dependency P4-001 =
DONE, satisfied).

## 2. Implementation Inspected

- `app/TranscriptExperience/TranscriptCopy.php` (full file read).
- `app/Http/Controllers/TranscriptionController.php::show()` (full file read).
- `app/Models/Transcription.php::segments()` relationship definition.
- `resources/views/transcriptions/show.blade.php` — the `transcriptSearch`
  Alpine component and surrounding markup (full file read, including the
  inline `<script>` block).
- `tests/Unit/TranscriptExperience/TranscriptCopyTest.php` (full file read).
- `tests/Feature/TranscriptSearchCopyTest.php` (full file read).

## 3. Client-Side Boundary

No server route or controller action is invoked per keystroke: the Alpine
component binds `x-model="query"` and a single `this.$watch('query', () =>
this.refresh())`, which runs entirely in the browser over
`this.segmentEls()` (elements already present in the loaded DOM). No `fetch`,
`axios`, `wire:model.live`, or similar call exists anywhere in the search
logic. No new route, no new database index, and no new query added to
`TranscriptionController`. **Confirmed: search is genuinely client-side over
already-loaded persisted segments (D4-04).**

## 4. Unicode Search — Direct Code Audit

Read the `refresh()`/`render()` implementation line by line rather than
relying on the rendering-only feature tests (which do not exercise the JS at
all):

- Latin case-insensitivity: `this.query.toLocaleLowerCase()` vs.
  `text.toLocaleLowerCase()`, then `indexOf`. Correct for Latin text.
- Chinese/Tamil: neither script has a case distinction, so
  `toLocaleLowerCase()` is a no-op on that text and matching reduces to exact
  substring `indexOf`/`slice` on the original characters — no folding-related
  corruption is possible for these scripts.
- Surrogate pairs / UTF-16 code units: matching and slicing operate
  consistently on the same string representation throughout (`indexOf`
  returns a code-unit offset into `text`; `slice(cursor, position.start)` and
  `slice(position.start, position.start+length)` use that same offset and the
  query's own code-unit length). Because the match boundary is always defined
  by where the literal query string was found — not by an independently
  computed character index — a slice can never bisect a valid code point that
  the match itself does not already span. No corruption case was found for
  any input, including characters that use surrogate pairs.
- Offsets are not shifted by whitespace normalization: no `trim()`/`replace()`
  is applied to the segment text before matching; only the query is not
  trimmed either (see LOW-1 below for a related but distinct consequence of
  that).

**No correctness defect found.** This assessment rests on direct code
inspection, per the review brief's instruction not to assume unverified
guarantees; there is no browser-executed test for `zh`/`ta` search in this
repository (see §9), which is contractually expected, not a gap this review
treats as blocking.

## 5. Highlight Safety (Security-Relevant)

Read `render()` and `copyText()` in full and grepped the whole `<script>`
block for `innerHTML`, `x-html`, and `document.write` — none present.

- `render()` always starts with `el.textContent = ''` (clears via the safe
  DOM API), then rebuilds content exclusively via `document.createTextNode()`
  for non-matched spans and `document.createElement('mark')` +
  `mark.textContent = ...` for matches. Every character of transcript text
  that reaches the DOM does so through a `textContent` assignment or a
  `Text` node — neither path is ever interpreted as markup by the browser.
- The initial raw text captured in `init()` (`el.dataset.rawText =
  el.textContent`) is itself read via `textContent`, not `innerHTML`, so even
  the original Blade-escaped HTML entities in the page source
  (`{{ $segment->text }}`) are decoded once into a plain string and never
  re-interpreted as HTML afterward.
- Traced the hostile inputs specified in the review brief:
  `<img src=x onerror=alert(1)>` and `<script>alert(1)</script>` as segment
  text — Blade's `{{ }}` escapes these into HTML entities in the page's raw
  HTML source; the browser's initial parse therefore renders them as inert
  text already (this is standard Blade/browser behavior, not something
  P4-004 added); `el.textContent` read by `init()` yields the literal
  decoded string (e.g., an actual `<` character), and every subsequent
  highlight re-render reinserts that same literal string via
  `createTextNode`/`mark.textContent`. At no point is a string parsed as
  HTML. **No DOM-injection / XSS path exists in the highlighting
  implementation.**

## 6. Match Semantics

- Empty query (`''`): `if (query !== '')` skips populating `positions`, so
  `total` stays `0`, `matchCount` becomes `0`, and every segment is
  re-rendered as a single plain text node (no `<mark>`) — zero matches, zero
  highlights, count `0`. Correct.
- Result count: `matchCount` is the sum of `positions.length` across all
  segments, computed fresh on every `refresh()`. Correct.
- Next/previous: `(currentIndex + 1) % matchCount` and `(currentIndex - 1 +
  matchCount) % matchCount` — both correctly wrap in both directions,
  including from the first match back to the last.
- Clear: sets `query = ''`, which triggers the same `refresh()` path as an
  empty query, removing all highlights.
- Multiple matches within one segment: the `while` loop advances
  `from = at + query.length` after each hit, so overlapping occurrences of a
  match are not double-counted (standard, unspecified-by-contract, and
  reasonable non-overlapping substring counting).
- Matches across multiple segments: `total` accumulates across the
  `segmentEls().forEach(...)` loop and `render()` is called once per segment
  with a running `offset`, so global match indices used by `applyCurrent()`
  align correctly with the marks' actual DOM order (segments are rendered in
  `segment_index` order by the Blade `@foreach`, and `querySelectorAll`
  preserves DOM order).
- No-match state: `matchCount === 0` disables Previous/Next
  (`x-bind:disabled="!hasMatches"`) and `countLabel` returns `'No matches'`.

**One inconsistency found by direct trace, not covered by any test:**
`refresh()` uses the raw (untrimmed) `this.query` for the actual matching
(`if (query !== '')`), while the `countLabel` getter checks
`this.query.trim() === ''` to decide whether to show a count at all. A
whitespace-only query (e.g., a single space) is therefore **not** treated as
empty by the matching logic — it will search for and highlight literal space
characters and set `matchCount` to a real (non-zero) count — while the label
displayed to the user renders blank (as if there were no active search). This
is a minor, non-blocking UI inconsistency; the task's contract only defines
behavior for a literally empty query (`''`), not a whitespace-only one, so
this is not a contract violation. Recorded as LOW-1.

## 7. Search / Active-Highlight Separation (P4-003 Non-Preemption)

Confirmed by full-file read of `show.blade.php`: there is no `<audio>`,
`<video>`, `currentTime` binding, click-to-seek handler, or "active segment"
CSS class/state anywhere in the view or script. The only highlight mechanism
present is the search `<mark>` element with a `data-match-index` attribute
and an orange "current match" class — this is scoped entirely to search
navigation, not playback state, and does not touch `data-segment-index`
beyond using it as a lookup key for `copySegment()`. **P4-004 does not
implement P4-003, does not own active-segment semantics, and does not
introduce any structure that would prevent an active-playback highlight layer
from being added independently later** (the two would apply distinct CSS
classes to distinct elements — `<mark>` for search vs. presumably the
segment container `<div data-segment-text>` for active-playback — with no
overlap in the current markup).

## 8. Copy Source of Truth

- Full transcript: `TranscriptionController::show()` computes
  `$fullTranscriptText = TranscriptCopy::fullText($transcription->segments)`.
  `Transcription::segments()` is defined as
  `$this->hasMany(TranscriptionSegment::class)->orderBy('segment_index')`
  (`app/Models/Transcription.php:78`) — ordering is **guaranteed by the
  relationship definition itself**, not incidental default database-insertion
  order, satisfying the review brief's explicit concern in §20. `TranscriptCopy::fullText()`
  then does nothing but `implode("\n", ...)` over the segments it is given,
  with no re-sorting of its own (correctly relying on the caller's guaranteed
  order). Confirmed no timestamps are included.
- Segment copy: `TranscriptCopy::segmentText()` returns `(string)
  $segment->text` — the persisted segment text only, no timestamp.
- Unicode: `TranscriptCopyTest.php` asserts verbatim preservation for
  `Selamat datang` (ms), `欢迎` (zh), `வணக்கம்` (ta), and the literal string
  `und`. `TranscriptSearchCopyTest.php` additionally asserts the same mixed
  string renders correctly in the live view. Both independently reproduced
  (§11).

## 9. Clipboard Behavior

`copyText()` prefers `navigator.clipboard.writeText(text)` (guarded by
`window.isSecureContext`) with a `.then(onSuccess, onFailure)` pair — this
correctly handles promise rejection without an unhandled rejection and always
resolves to setting `copyStatus` to a user-visible string (`'Copied'` /
`'Copy failed'`). The synchronous `document.execCommand('copy')` fallback
path, and the whole function, is wrapped in `try { ... } catch (error) {
this.copyStatus = 'Copy failed'; }`. **No uncaught error path exists in either
branch.** `copyStatus` is bound to an `aria-live="polite"` element, giving
accessible feedback for both success and failure. Consistent with the task's
own "Browser Verification" clause: real clipboard behavior is not, and is not
claimed to be, proven by these backend/Blade-rendering tests — that
distinction is honestly preserved in the task's "Known Limitations" section
and is not contradicted anywhere in the implementation notes.

## 10. Accessibility

- Search input: `aria-label="Search transcript"`, visible placeholder,
  standard `<input>` (keyboard-operable natively).
- Previous/Next/Clear/Copy buttons: all native `<button type="button">`
  elements (keyboard-operable, focusable by default); icon-only-meaning
  buttons (`Previous`, `Next`, per-segment `Copy`) carry an explicit
  `aria-label`; `Copy transcript` has visible text so needs none.
- Match count (`countLabel`) and copy status (`copyStatus`) are both bound to
  elements with `aria-live="polite"`.
- This is a reasonable baseline consistent with the task's explicitly scoped
  "Accessibility Baseline" section; this review did not extend into a full
  WCAG audit, per the review brief's own instruction not to over-scope this
  section.

## 11. Test Reproduction

Independently re-executed (not read from the report):

```
vendor/bin/pest tests/Unit/TranscriptExperience/TranscriptCopyTest.php tests/Feature/TranscriptSearchCopyTest.php
→ 7 passed, 19 assertions
```

Exact match to the implementer's reported figures. Distinguishing what is
actually proven:

- **Proven by automated tests:** copy-payload ordering, newline-joining,
  no-timestamp behavior, and Unicode-verbatim preservation
  (`TranscriptCopyTest.php`, pure PHP unit tests — strong, direct assertions);
  that the search/copy controls and the `transcriptSearch` Alpine
  initialization markup are present in the rendered HTML for a
  completed-with-segments transcript, absent for a no-segments transcript,
  and that multilingual text renders correctly in the DOM
  (`TranscriptSearchCopyTest.php` — rendering-presence assertions only).
- **Not proven by any test in this repository (deferred, per contract, to
  browser-level verification in P4-006):** actual runtime search matching,
  case-insensitivity, Chinese/Tamil matching, empty-query behavior, count
  display, next/previous navigation and wrap, highlight DOM construction, and
  live clipboard writes. This review substituted a full manual code audit
  (§4–§9) for that missing coverage, as instructed, and found no defect
  beyond the LOW-1 whitespace inconsistency.

## 12. Cross-Task Scope Audit

- No translation, editing, or server-side search was found anywhere in the
  diff (`app/TranscriptExperience/TranscriptCopy.php` and the view/controller
  changes are the entirety of this task's footprint).
- `WorkspaceAvailability`/`WorkspaceState` (P4-001's canonical "workspace read
  model" primitives) are **not consumed anywhere** in the application
  (confirmed by a repository-wide grep — zero references outside their own
  `app/TranscriptExperience/{WorkspaceAvailability,WorkspaceState}.php`
  files). P4-004 instead gates search/copy visibility with an ad hoc
  `@if ($transcription->segments->isEmpty())` check in the Blade view. This
  is behaviorally equivalent today only because of a separate, already-
  independently-verified Phase 3 invariant
  (`app/Actions/TranscriptionResultWriter.php`: segments are inserted only
  inside the same transaction that sets `status = Completed`, so a
  transcription can never have segments without also being `Completed`) — not
  because P4-004 itself re-derives or asserts that invariant. No task
  acceptance criterion mandates use of the `WorkspaceAvailability` helper
  specifically, so this is not a contract violation, but it does mean the
  P4-001 "workspace read model" dependency interface that P4-004 was
  specified to consume is not actually wired up, and the availability logic
  now exists in two unreconciled places (the unused helper, and this ad hoc
  check). Recorded as LOW-2.
- No schema/migration change.

## 13. Shared Regression

See the Wave 1 summary report (§D) for the full-suite reproduction; no
regression attributable to this task. `TranscriptionController::show()`'s
only change is the addition of the `$fullTranscriptText` variable passed to
the view — the retry banner, tabs, export menu, and all other pre-existing
`show.blade.php` content were read in full and are structurally unchanged
apart from the new search/copy block replacing the previous plain segment
list inside the same `@else` branch.

## 14. Findings Summary

| ID | Severity | Finding | Blocking? |
|----|----------|---------|-----------|
| LOW-1 | LOW | A whitespace-only search query is treated as a real (non-empty) search by the matching logic (highlights literal spaces, non-zero match count) while the displayed count label shows blank text (computed from a trimmed check) — a minor UI inconsistency not addressed by the contract's literal empty-query (`''`) definition. | No |
| LOW-2 | LOW | P4-004 does not consume the P4-001 `WorkspaceAvailability`/`WorkspaceState` "workspace read model" primitives; search/copy gating is reimplemented ad hoc via `segments->isEmpty()`, relying implicitly (not explicitly) on the separately-verified Phase 3 completed-segments invariant. Behaviorally correct today; leaves the canonical primitive unconsumed and duplicated logic across two locations. | No |

No BLOCKER, HIGH, or MEDIUM finding was identified.

## 15. Acceptance Criteria Assessment

All 17 acceptance criteria were checked. AC1–AC9 (search semantics) are
satisfied on the strength of the direct code audit in §4–§7, since no
executable test exists for them in this repository (contractually
anticipated, see §11). AC10–AC14 (copy semantics) are satisfied and directly
proven by `TranscriptCopyTest.php`. AC15 (read-only) is satisfied — no code
path in this task's diff mutates `Transcription`, `TranscriptionSegment`, or
`MediaFile`. AC16 (tests/Pint/PHPStan) and AC17 (no unrelated functionality
changed) are satisfied — see the Wave 1 summary report §D for the
independently reproduced quality-gate results.

## 16. Final Verdict

```text
P4-004 = VERIFIED
```

No BLOCKER, HIGH, or MEDIUM finding. LOW-1 and LOW-2 are non-blocking and
recorded as historical observations / candidate follow-up work. P4-004 is
eligible to return to the Human Product Owner for closure consideration. This
review does not itself authorize P4-003 or P4-006.
