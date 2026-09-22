# P4-003 — Segment Navigation + Synchronized Highlighting

## Status

DONE

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: Claude Code (independent review; per AGENTS.md agent model)

## Authorization

This contract was authored under `DECISION-PHASE4-AUTHORIZATION-001` (Phase 4
authorized for task-contract authoring, 2026-09-19), audited/finalized on
2026-09-19, and authorized for implementation under
`DECISION-P4-003-AUTHORIZATION-001` (HPO, 2026-09-20): BACKLOG → READY.
Prerequisites satisfied: P4-002 = DONE (DECISION-P4-002-CLOSURE-001) and the
browser-verification strategy is resolved (DECISION-P4-BROWSER-VERIFICATION-001).
P4-006 remains BACKLOG and unauthorized. Browser-level evidence for this task
must follow DECISION-P4-BROWSER-VERIFICATION-001 (reproducible
environment-dependent browser verification with retained evidence; no new
automation platform).

## Authorized Phase

Phase 4 — Transcript Experience baseline (ADR-019)

## Batch

Batch 1 (proposed; to be confirmed by the implementation authorization)

## Objective

Provide transcript segment navigation synchronized with media playback: clicking
a segment timestamp seeks the media, and the segment covering the current
playback position is highlighted, using the canonical precision and resolver
defined by P4-001.

## Context

- ADR-019; `PHASE4-PLANNING.md` (D4-06 decided)
- P4-001 (canonical timestamp formatter; deterministic active-segment resolver)
- P4-002 (authorized range-stream endpoint, opaque UUID identity)
- Persisted segments: `TranscriptionSegment` (`start_seconds`, `end_seconds`,
  `segment_index`, `language`), `decimal(12,3)`
- Existing view: `resources/views/transcriptions/show.blade.php`
- No `<audio>`/`<video>` element exists in the repository today

## Scope

Implement only:

- a transcript interaction surface (Livewire/Alpine + a native media element)
  bound to the P4-002 stream URL;
- click segment timestamp → seek media to the segment's `start_seconds`;
- active-segment highlighting driven by the player's current time and the
  P4-001 active-segment resolver;
- display of per-segment language labels (`ms`, `en`, `zh`, `ta`, `und`)
  without mutating persisted data;
- baseline auto-scroll per §Auto-Scroll;
- read-only behavior.

## Browser Binding Contract

- The player is a native `<audio controls>` or `<video controls>` element
  (native controls provide baseline keyboard/accessibility behavior).
- Clicking a segment timestamp sets the media element's `currentTime` to the
  exact persisted `start_seconds` (seek-numeric value from P4-001).
- The active segment is recomputed from the player's current time using the
  P4-001 resolver:
  `start_seconds <= currentTime < end_seconds`.
- Recompute on playback progress and on `seeked`; do not run a per-frame DOM
  loop. A throttled update (e.g. `requestAnimationFrame` gated to change, or a
  coarse interval) is required.
- Manual user seeking updates the active segment immediately.
- Playback pause/resume does not change active-segment semantics.
- Audio and video must have parity of behavior.

## Active Segment Semantics

Delegated to the P4-001 resolver. Required behavior:

- gaps between segments: no active segment;
- exact end boundary: inactive at `currentTime >= end_seconds`;
- zero-length segment: never active;
- before first / after final segment: no active segment;
- overlapping/malformed intervals: lowest `segment_index` wins;
- ordering follows `segment_index`, not array position.

The browser binding must not re-implement or diverge from the resolver.

## Auto-Scroll

Baseline (task-level UX detail, consistent with ADR-019):

- When the active segment changes, scroll it into view only if it is not already
  visible within the transcript viewport.
- If the user scrolls the transcript manually, suspend auto-scroll until the
  next explicit playback interaction (play, pause, or seek).
- Auto-scroll must not cause the page to jump when the active segment is
  already visible.

## Accessibility Baseline

- Segment timestamps are semantic controls (`<button>`) operable by keyboard.
- Search/copy/player controls are keyboard operable with visible focus.
- Active-segment state is conveyed programmatically (e.g. `aria-current`) and
  not by color alone.
- No regression of ordinary accessibility.

## Out of Scope

Do not implement:

- transcript or segment editing (D4-05; Phase 6);
- waveform editor, frame-accurate editing, clipping, timeline editing,
  annotations, chapter editing (D4-06);
- diarization or speaker labels;
- search or copy (P4-004);
- export changes (P4-005);
- any schema/migration change.

## Dependencies

Requires:

- P4-002 (Authorized Private Media Playback) — VERIFIED and closed DONE.

## Acceptance Criteria

1. Clicking a segment timestamp seeks the media to that segment's exact
   `start_seconds`.
2. The seek target uses millisecond precision from persisted values.
3. The active segment matches the P4-001 resolver for normal, gap, boundary,
   zero-length, and overlap cases.
4. The active highlight moves correctly as playback advances and on manual seek.
5. No active segment is highlighted in a gap, before the first segment, or after
   the final segment.
6. Per-segment language labels are displayed and never mutated.
7. Audio and video behave identically.
8. Auto-scroll follows §Auto-Scroll.
9. Transcript content remains read-only.
10. Segment controls are keyboard operable with visible focus and programmatic
    active state.
11. No per-frame DOM loop; updates are throttled to change.
12. Relevant tests pass; Pint clean; PHPStan 0 errors.
13. No unrelated functionality is changed.

## Test Requirements

- Unit/feature tests for the P4-001 resolver boundary cases (owned by P4-001);
- a Livewire/component test proving click-to-seek computes the exact
  millisecond target and that the active-segment selection updates;
- a browser-level verification (see §Browser Verification) for real
  `HTMLMediaElement` seek and highlight behavior.

## Browser Verification

Backend tests cannot prove real `HTMLMediaElement` seek/highlight behavior. The
repository currently has no browser automation. P4-003 must therefore provide a
reproducible, environment-dependent browser verification with retained evidence
(step list, measured `currentTime` values, screenshots/recording) OR use a
lightweight browser test tool only if separately authorized (see P4-006
§Browser Verification Strategy and the outstanding owner decision).

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Segment boundary jitter | Resolver boundary tests; `>=` end exclusivity |
| Inaccurate seek | Exact persisted seconds; no rounding in seek path |
| Expensive per-frame DOM loops | Throttle to change; no rAF-per-frame DOM writes |
| Active/search highlight collision | Distinct visual layers; resolver owns active state |
| Auto-scroll fighting the user | Suspend on manual scroll until next playback interaction |

## Implementation Notes

### Files Changed

- `resources/views/transcriptions/show.blade.php` — native `<audio>`/`<video>`
  bound to the P4-002 `media.stream` URL; timestamp `<button>` controls carrying
  the exact persisted seek numeric; per-segment language badges; scrollable
  transcript region; Alpine `transcriptPlayback` component and the pure
  `window.p4ResolveActive` parity function.
- `app/Http/Controllers/TranscriptionController.php` — computes the authorized
  stream URL, media element type, and the playback segment payload
  (`index`/`start`/`end`) for the workspace.
- `app/Models/TranscriptionSegment.php` — additive `getSeekSecondsAttribute()`
  consuming the P4-001 `SegmentTimestamp` primitive (exact persisted numeric
  seek; never parsed from the display string).
- `tests/Feature/TranscriptPlaybackTest.php` (new).
- Verification tooling (dev-only): `verification/playwright.config.js`,
  `verification/p4-003-auth.setup.js`,
  `verification/p4-003/transcript-playback.spec.js`,
  `verification/p4-003-seed.php`, `verification/p4-003-server-router.php`.
- `P4-003-BROWSER-VERIFICATION-EVIDENCE.md` (executed evidence).

### Important Decisions

- Reused P4-002 `media.stream` (no second delivery endpoint) and P4-001
  `SegmentTimestamp` for the exact seek value.
- The browser active-segment algorithm is a literal transcription of the
  canonical P4-001 rule (`start <= t < end`, lowest `segment_index` wins;
  zero-length never active), exposed as `window.p4ResolveActive` for direct
  parity checking. The PHP resolver is not executed in the browser; parity rests
  on the identical algorithm plus shared fixture expectations.
- Active state is applied to the segment row (`aria-current="true"` + ring),
  separate from P4-004's inline `<mark>` search highlighting; the two layers
  coexist.
- Updates are driven by `timeupdate`/`seeked`/`loadedmetadata`/`seeking`; DOM
  writes occur only when the active index changes (no per-frame loop).
- Auto-scroll uses `scrollIntoView({block:'nearest'})` only when the active row
  changes, and is suspended on wheel/touch/keyboard transcript scroll until the
  next play/pause/seek interaction.

### Known Limitations

- Auto-scroll suspension detects wheel/touch/keyboard scroll intent; scrollbar
  drag is not detected (baseline).
- Search/copy gating remains the P4-004 ad hoc `segments->isEmpty()` check
  (P4-003 does not change it).
- Browser verification observed a pre-existing, non-P4-003 page error
  (`ReferenceError: showRenameModal is not defined`, rename-modal Alpine scoping
  in the show view). Reported, not changed (outside P4-003 scope).

## Verification

Commands executed on 2026-09-20 (PHP 8.4, Pest 5.1):

- `vendor/bin/pest tests/Feature/TranscriptPlaybackTest.php` → 8 passed,
  32 assertions.
- Phase 4 regressions: P4-001 30/117; P4-002 13/61; P4-004 7/19; P4-005 21/116.
- Full suite: 430 tests, 429 passed, 1 skipped (pre-existing 2FA), 1434
  assertions, 2 warnings (pre-existing baseline).
- `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress` → 0 errors.
- `vendor/bin/pint ... --format agent` → passed.

Browser verification (authorized Playwright tooling,
`DECISION-P4-BROWSER-VERIFICATION-002`):
`node_modules/.bin/playwright test --config=verification/playwright.config.js`
→ 10 passed (19.2s). Real observed values (audio/video stream src and playback,
seek `12.345` observed exactly with difference 0, resolver parity 12/12,
boundaries 12/12, manual seek, auto-scroll, keyboard, search coexistence,
no-speech) are recorded in `P4-003-BROWSER-VERIFICATION-EVIDENCE.md` §12 and
`verification/artifacts/p4-003-browser-results.json`. No console errors; one
pre-existing non-P4-003 page error (`showRenameModal`) recorded.

Result:

PASSED — automated tests/gates and browser verification complete; ready for
independent review.

## Review

Review File: `reviews/P4-003-independent-review.md`

Review Status: VERIFIED (independent review, 2026-09-20). No BLOCKER/HIGH/
MEDIUM findings. Non-blocking: LOW-1 (the Playwright suite's interactive
checks — click-to-seek precision, resolver/boundary parity, auto-scroll,
keyboard, search coexistence — were exercised only against the audio fixture;
the video fixture was only exercised for element/src/duration/basic playback;
mitigated by the `transcriptPlayback` binding containing no audio/video
branching and by Pest-level confirmation of correct `<video>` rendering),
LOW-2 (one isolated cold-start timing flake observed during independent
reproduction of the audio playback assertion; did not recur on retry or on a
subsequent full clean run). INFO-1: the pre-existing `showRenameModal`
console error was independently confirmed via git history to predate all
Phase 3/4 work and is unrelated to and unaffected by P4-003. All reported
automated test results (P4-003: 8/32; P4-001: 30/117; P4-002: 13/61; P4-004:
7/19; P4-005: 21/116; full suite: 430/429/1/1434/2; Pint clean; PHPStan 0
errors) and the Playwright browser suite (10 passed) were independently
reproduced with an exact match, including a from-scratch re-seed with fresh
fixture UUIDs. Eligible for Human Product Owner closure to DONE.

## Closure

Closed as DONE by the Human Product Owner on 2026-09-20
(DECISION-P4-003-CLOSURE-001), based on `reviews/P4-003-independent-review.md`
(P4-003 = VERIFIED; no BLOCKER/HIGH/MEDIUM; all 13 acceptance criteria PASS; all
automated and browser evidence independently reproduced).

Canonical transition: VERIFIED → (HPO closure decision) → DONE.

History preserved, not rewritten:

```text
BACKLOG
→ DECISION-P4-003-AUTHORIZATION-001
→ READY
→ implementation
→ REVIEW
→ initial browser evidence incomplete
→ DECISION-P4-BROWSER-VERIFICATION-002 / ADR-020
→ Playwright evidence completion
→ independent review: VERIFIED
→ HPO closure
→ DONE
```

Non-blocking findings retained as historical observations (not promoted into
scope):

- LOW-1: video-fixture interactive browser evidence gap (mitigated by
  tag-agnostic, non-branching binding code; recommend closing in P4-006).
- LOW-2: one isolated cold-start Playwright timing flake that did not recur.
- INFO-1: pre-existing `ReferenceError: showRenameModal is not defined`
  (predates Phase 3/4; unrelated to P4-003).

Closure is governance/state reconciliation only; no implementation, test, or
harness change is authorized. This closure does not itself authorize P4-006 (a
separate decision does).

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED. P4-006 depends on
this task being VERIFIED and closed DONE.
