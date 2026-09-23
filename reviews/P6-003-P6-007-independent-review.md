# P6-003 / P6-007 — Independent Review

Date: 2026-09-24
Tasks: `tasks/P6-003-text-editing-undo-redo.md`, `tasks/P6-007-source-translation-comparison.md`
Reviewer: Claude Code (fresh independent review; no reliance on the implementer's
batch reports or pre-review handoffs beyond locating evidence to reproduce)
Authority for review scope: `PHASE6-EDITING-DOMAIN-CONTRACT.md` (frozen);
`DECISION-P6-002-CLOSURE-001`; `DECISION-P6-007-SCOPE-001`;
`DECISION-P6-003-P6-007-READY-BATCH-001`; the two task files.

## 1. Verdict table

| Task | Verdict |
|---|---|
| P6-003 — Text Editing + Undo/Redo | **VERIFIED** |
| P6-007 — Source / Translation Comparison | **CHANGES_REQUESTED** |

No BLOCKER findings on either task. One MEDIUM finding on P6-007 (translation
provenance truthfulness — the task's own stated critical area). One
governance/evidence-trail MEDIUM finding, orthogonal to both tasks' code, is
also recorded because it bears directly on the "P6-002 is a verified frozen
foundation" premise both tasks rely on.

## 2. Findings

### P6-007 — MEDIUM: the per-row "Edited after the translation was produced" note renders even when no translation was ever persisted

**File:** [resources/views/transcriptions/partials/source-translation-comparison.blade.php:89-95](resources/views/transcriptions/partials/source-translation-comparison.blade.php:89),
[app/Comparison/ComparisonRow.php:33-39](app/Comparison/ComparisonRow.php:33)

`ComparisonRow::revisionEdited()` is a pure text comparison — `revisionAligned
&& revisionText !== machineText` — with no dependency on whether a translation
exists (`translatedText`/`hasTranslation()`). The comparison partial renders
the per-row note unconditionally on `revisionEdited()` for any aligned row,
independent of the top-of-panel `hasTranslation` gate. Given a transcription
with an edited active revision and **no translation at all** (persisted or
otherwise), the partial simultaneously renders `data-comparison-no-translation`
("No translation available") **and**, on every edited row,
`data-comparison-revision-edited-note` ("**Edited after the translation was
produced**") — asserting a translation exists and was superseded, when none was
ever produced. I confirmed this is not a hypothetical by reproducing it
directly: seeding a transcription with an edited active revision and zero
translations and rendering `transcriptions.show` emits both
`data-comparison-no-translation` and `data-comparison-revision-edited-note` in
the same response (verified with a throwaway feature test against the real
render path, then removed — not part of the committed suite).

This is exactly the failure mode the task brief calls out as the critical
P6-007 area: "Do not accept a UI that merely happens to line up rows visually
if its semantic provenance is false." The note is not visually misaligned —
it renders correctly per-row — but its **claim** is false in the no-translation
case, which the task's own AC6 requires to be handled as a distinct, factual,
first-class state.

The gap is untested: `verification/p6-007/source-translation-comparison.spec.js`
only asserts `data-comparison-no-translation` is visible and the revision text
is shown for the `none` fixture (edited revision, no translation); it never
asserts the *absence* of `data-comparison-revision-edited-note` there. The
feature-test suite (`SourceTranslationComparisonTest.php`) has no scenario that
combines an edited revision with a missing translation.

**Recommendation:** gate the per-row note on `$row->hasTranslation()` in
addition to `revisionEdited()` (or add a `ComparisonRow::hasStaleTranslationNote()`
predicate combining both), and add a feature-test/browser case for
"edited revision + no translation" asserting the note does **not** render.

This does not corrupt data, mutate anything, or misalign a translation to the
wrong text — it is a presentation-truthfulness defect confined to one
conditional note. It does not block the read-only/no-mutation/no-inferred-
staleness guarantees, which I independently verified hold (see §4). I rate it
MEDIUM rather than HIGH/BLOCKER because the practical blast radius is a single
misleading sentence in an edge case (edited-revision-with-no-translation),
not a wrong translation shown against the wrong text.

### Governance/evidence — MEDIUM (informational, does not block either task): the P6-002 "VERIFIED" closure's cited corrective re-review artifact does not exist in the repository

`DECISIONS.md` (P6-002 Closure, ~line 1611) states the closure decision rests
on "the fresh independent corrective re-review" and, in the same entry, records
a provenance note that `reviews/P6-002-corrective-independent-re-review.md`
"was not present in the working tree at reconciliation time... must be
retained/added." As of this review, that file still does not exist anywhere
in the repository (`reviews/` contains `P6-002-independent-review.md`, which
is the **CHANGES_REQUESTED** review documenting the original strict-ancestor-undo
MEDIUM finding, and `reviews/pre-review/P6-002-corrective-pre-review.md`, but
no corrective *independent* re-review). P6-003 and P6-007 both declare P6-002
"DONE" and "verified" as a frozen input they build on without re-litigating it.

I did not treat this as blocking either task because I independently
re-verified the substance myself in this review: `RevisionService::undo()`
(`app/Editing/RevisionService.php:185-198`) does walk `parentRevisionId` and
reject non-ancestor targets via `isStrictAncestor()`, so the underlying MEDIUM
finding from the original P6-002 review is in fact fixed in the code present
today, and P6-002's full test suite plus a genuine two-process race are part of
the full regression I reproduced (§4). The finding here is purely an
evidence-trail/durable-handoff gap — the closure decision cites a durable
artifact as the basis for HPO sign-off, and that artifact is absent — which
is worth surfacing per this repository's own governance rule that "important
project information must not exist only in chat output." Recommend the
missing corrective re-review artifact be authored/committed to close the gap
in the durable record, but this is not a P6-003/P6-007 code defect and I am
not reopening P6-002.

## 3. P6-003 — detailed assessment

**Frozen-semantics preservation:** confirmed. `TextEditComposer` only replaces
`text`; identity, position, timing, and language are copied verbatim from the
base segment (`app/Editing/TextEditComposer.php:65-72`), and the composed list
is re-validated as ordinary `RevisionSegmentData` (no bypass of per-segment/
cross-segment invariants). `TranscriptRevisionController` never touches
`transcript_revisions`/`transcript_revision_segments`/`transcriptions` directly —
every mutation goes through `RevisionService` (`materializeInitial`, `edit`,
`undo`, `redo`), which is itself authorization-fenced and delegates
CAS/monotonic-version/ancestry enforcement to `EloquentRevisionRepository`.
There is no in-place mutation path anywhere in the new code.

**Machine-source path:** editing from `active_revision_id = null` calls
`materializeInitial()` then `edit()` as two independently CAS-fenced
operations. This is honestly disclosed by the implementer as a residual risk
(a concurrent writer between the two calls surfaces as a conflict, leaving a
durable, recoverable, non-corrupt materialized-but-unedited revision active) —
I verified this is exactly what happens: `append()`'s CAS is keyed on the
transcription's *current* `active_revision_id` at transaction time
(`EloquentRevisionRepository.php:111-115`), so a lost race fails loudly as
`RevisionConflictException`, never silently. Feature test
`TranscriptEditingWorkspaceTest.php` confirms materialize+edit produces exactly
2 revisions (v1 machine copy, v2 edit), the machine `transcription_segments`
rows are byte-for-byte unchanged, and reload reproduces the same state.

**Existing-revision path:** `edit()` looks up the stated base, rejects an
unknown/foreign-transcription base, and appends via the repository's CAS.
`TranscriptRevisionController::store` independently re-checks
`$active->revisionId !== $expectedBase` at the HTTP boundary *before* calling
into the service — so the stale-base rejection is real at the HTTP layer, not
merely inside the service (addressing the pre-review's own focus item #1). A
stale save leaves revision-row count and the active pointer unchanged
(feature test verifies both).

**Undo/redo/branch:** `RevisionService::undo()` walks `parentRevisionId` from
the current active revision and only succeeds for a genuine strict ancestor;
self/non-ancestor targets throw `UndoUnavailableException`, and a stale
expected-token throws `RevisionConflictException` before any ancestry work.
`redo()` delegates to `redoTargetFor()` (unique-child only, `null` at a branch
point — never guessed). Feature tests and the independently-reproduced
Playwright suite (§5) both confirm: undo to ancestor, redo to child, a second
edit after undo creates a sibling branch (`parent_revision_id` shared, two
children), and redo is then correctly disabled (`RedoUnavailableException` →
`revision_error`), while both branches remain durable rows.

**Concurrency at the HTTP boundary:** confirmed for all three actions
(`store`/`undo`/`redo`) — each maps `RevisionConflictException` to a distinct
`revision_conflict` flash with a reload link, and other domain rejections to
`revision_error`, never a silent 200/merge.

**Translation-invalidation boundary:** `EditKind::Textual::stalenessReason()`
correctly resolves to `SourceTextChanged`; P6-003 introduces no
`translations.stale_at`/`staleness_reason` column, model attribute, or write
path (confirmed via `Schema::hasColumn` assertions in the feature suite and my
own migration/schema inspection — no such columns exist anywhere in the
migrations directory). `reasonForKinds()`/`EditKind::precedence()` (used by
mixed-category precedence) are exercised only by P6-002-owned unit tests, not
by any P6-003 application code path — no scope creep into P6-005 territory.

**Workspace / P6-006 / Phase 4 compatibility:** the reserved hooks
(`data-seek-seconds`, `data-segment-language`) and P6-006's own hooks
(`data-filter-language`, `data-nav-seconds`, `data-segment-index`,
`data-segment-row`) are untouched and still present on every row; P6-003 adds
only its own `data-edit-*`/`data-revision-*` hooks in a separate partial. The
`isTypingTarget()` guard in `transcriptSearch` (unchanged from P6-006) already
excludes `textarea`, so arrow-key navigation does not hijack the edit
textareas — I confirmed this both by reading the guard and via the reproduced
browser suite (typing in a field never triggers segment navigation in any of
the 8 scenarios). `nav_index` (used for `data-segment-index` and playback
resolution) is `position` when a revision is active and `segment_index`
otherwise; since `materializeInitial` copies machine segments 1:1 in order,
this substitution is behavior-preserving for navigation/search/copy/filter
and does not regress Phase 4/P6-006 semantics (per §8 of the frozen contract,
this is in fact the specified navigation-identity rule).

**Authorization/isolation:** `RevisionService` gates every mutating and
reading method through `Gate::forUser($user)->authorize('update'|'view',
$transcription)`, additionally enforced again at the controller
(`$this->authorize('update', ...)`) before any service call. A non-owner gets
403 for edit/undo/redo (feature test + reproduced browser evidence). Cross-
transcription revision ids are rejected by explicit ownership checks in
`edit()` and `undo()` (`$base->transcriptionId !== $transcription->getKey()`).

**Save/cancel UX:** entering edit mode is pure client-side Alpine state
(`editMode = true`), no request; cancel resets textareas to `defaultValue` and
sends nothing (verified: no session/revision-count change); save is a real
form POST gated by CSRF; conflict/error/notice are rendered via `aria-live`
regions (`role="alert"`/`role="status"`) as accessible feedback. Reload uses a
redirect-after-POST pattern (route redirect + flash), so browser back/refresh
after a save does not resubmit the form.

**Independent regression evidence:** full suite (797 tests, 796 passed, 1
skipped, 0 failures), Pint clean, PHPStan 0 errors — all reproduced directly
by me, matching the implementer's claims exactly (§5).

**Scope discipline:** no P6-004 timing-edit path, no P6-005 split/merge or
staleness-persistence path, no P6-008 history/audit UI beyond the single
active-revision indicator and one-step undo, no P6-009/Phase 7 work. Confirmed
by reading every new/changed file in the diff and grepping for stale/timing/
split/merge terms in the new code — none found outside of the frozen P6-001/
P6-002 domain vocabulary that already existed.

## 4. P6-007 — detailed assessment

**Presentation-only boundary:** confirmed. `ComparisonBuilder::build()` only
reads (`transcription->segments()`, `translations()->segments()`,
`RevisionService::active()` supplied by the caller) and constructs value
objects; it has no persistence call anywhere. `SourceTranslationComparisonTest`
asserts revision/segment/translation row counts and `translations.updated_at`
are byte-identical across two consecutive page loads, and that
`stale_at`/`staleness_reason` columns don't exist — I reproduced this test and
also confirmed no route/controller/view in the P6-007 diff issues a write
(the partial contains no `<form>`, no POST, and the `Compare`/`Transcript`
toggle is pure client-side `x-on:click="transcriptView = ..."` with no
network call, confirmed in the reproduced browser suite's "no mutation" case).

**Alignment correctness:** `ComparisonBuilder` aligns translation strictly by
machine `segment_index` (never array index), and aligns a revision segment to
a machine row only when its identity is exactly `machine:<index>` provenance
(`machineIndex()` regex-equivalent check). A structurally-edited revision
(identities like `new-0`/`new-1` in the test fixture) is never matched into
`revisionByMachineIndex`; it is appended as a distinct unaligned row with
`revisionAligned = false` and `machineIndex = null`, and the translation
column for such rows is never populated — confirmed both in the unit-level
builder logic and via the reproduced browser scenario (`structural` fixture:
`data-comparison-revision-state="alignment-unavailable"`, translation cell is
`—`).

**Edited-revision mismatch labeling (aside from the MEDIUM finding above):**
when a machine-provenance revision segment's text differs from the machine
text it was derived from, the panel shows an explicit top-level mismatch note
("the translation is of the original machine source... not a translation of
the edited text") and both the original machine text and the edited revision
text are shown side by side with the persisted translation still attributed
to the machine source — this is exactly the "explicit, never silently
aligned" behavior AC4 requires, and I reproduced it live (`edited` fixture).
A textually-identical revision (materialize-only, unedited) correctly shows
**no** mismatch note (`identical` fixture, reproduced).

**No-translation state:** correctly rendered as a first-class factual state
(`data-comparison-no-translation`) that does not block the machine-vs-revision
comparison; no staleness/freshness indicator is ever rendered anywhere in the
partial (grepped — no "stale"/"fresh"/"current" language in the blade file
outside of the deliberate "edited after..." note that is the subject of the
MEDIUM finding).

**Authorization:** the comparison is rendered only inside `TranscriptionController::show`,
which already gates on `TranscriptionPolicy::view`; a non-owner gets 403
before the comparison view model is even built (confirmed by feature test and
reproduced browser evidence).

**P6-006/Phase 4 compatibility:** the comparison partial uses only its own
`data-compare-*`/`data-comparison-*` hooks; it does not touch
`data-filter-language`, `data-nav-seconds`, `data-seek-seconds`, or
`data-segment-language`, confirmed by direct inspection and by a dedicated
feature test that asserts all of these are still present alongside the
comparison markup.

**Cross-task workspace interaction (P6-003 + P6-007 together):** both
features share the single `transcriptView`/Alpine root on `show.blade.php`.
`transcriptView` (`normal`/`compare`) is defined once on the outer `x-data`
and toggled only by the comparison partial's buttons; the P6-003 edit region
(`transcriptEditing`/`transcriptSearch`) is a nested, independent Alpine
component gated by its own `editMode` state and is hidden via
`x-show="transcriptView === 'normal'"` — the two state machines (`editMode`,
`transcriptView`) are distinct properties with no naming collision, and I
confirmed via the reproduced browser evidence that toggling to Compare while
mid-edit does not submit or discard the edit form (the edit form simply
becomes hidden, not unmounted — Alpine `x-show` preserves DOM/state). The
comparison view is read-only and has no controls that could accidentally
submit the edit form (no shared `<form>`, no shared button `type="submit"`
inside the comparison partial). `data-edit-*` and `data-compare-*`/
`data-comparison-*` hook namespaces are fully distinct with no overlap.

**Scope discipline:** no schema change, no translation lifecycle code, no
P6-005/P6-008/P6-009/Phase 7 work introduced. Confirmed by inspecting every
new file under `app/Comparison/` and the single new partial.

## 5. Regression evidence — independently reproduced vs. inspected only

| Gate | Status |
|---|---|
| `php artisan test --compact` (targeted: `TextEditComposerTest`, `TranscriptEditingWorkspaceTest`, `SourceTranslationComparisonTest`, `RevisionVersioningTest`, `RedoSemanticsTest`) | **Independently reproduced**: 40 passed / 183 assertions |
| `php artisan test --compact` (full suite) | **Independently reproduced**: 797 tests, 796 passed, 1 skipped, 0 failures — matches the implementer's claim exactly |
| `vendor/bin/pint --test --format=agent` | **Independently reproduced**: passed, 0 files need formatting |
| `phpstan analyse` | **Independently reproduced**: 0 errors |
| P6-003 Playwright suite (8 scenarios) | **Independently reproduced** end-to-end: seeded a fresh verification DB, ran a single clean `php -S` server, executed the real committed spec against real Chromium — 8/8 passed. (First attempt showed contamination from stray pre-existing `php.exe` processes left running in the sandbox from earlier activity, producing accumulated revision versions across fixtures; after killing all stray processes and reseeding from scratch, the suite passed cleanly. This was environmental noise in my review sandbox, not an application defect — the reseed-and-isolate re-run is the authoritative reproduction.) |
| P6-007 Playwright suite (8 scenarios) | **Independently reproduced** end-to-end under the same clean conditions: 8/8 passed |
| Migration/schema shape vs. frozen contract §10 | **Independently reproduced** by direct inspection: exact column/constraint match, no staleness columns anywhere |

All generated verification artifacts (sqlite DBs, server logs, Playwright
`test-results/`) created during this review were deleted afterward; no
application code, test, or governance file was modified by this review (one
throwaway feature-test file was created to empirically confirm the P6-007
MEDIUM finding and was deleted immediately after; it is not part of the
committed tree).

## 6. Direct answers to the review brief

1. **Does P6-003 preserve frozen revision semantics?** Yes. Append-only writes,
   expected-base CAS (re-checked independently at the HTTP boundary),
   strict-ancestor undo, unique-child redo, branch-after-undo with durable old
   history, immutable machine source, and preserved identity/position/timing/
   language on text-only edits are all real in the code and independently
   reproduced in tests and browser evidence.
2. **Is P6-007 semantically truthful about translation provenance/alignment?**
   Mostly yes, with one MEDIUM gap: the per-row "edited after the translation
   was produced" note is shown even when no translation was ever persisted,
   which is factually false in that combination. All other provenance/
   alignment rules (segment_index baseline, mismatch note for a genuinely
   edited+translated revision, no index-remapping for structurally
   incompatible revisions) are correctly and truthfully implemented.
3. **Cross-task workspace interaction:** sound — distinct Alpine state, distinct
   `data-*` hook namespaces, no shared forms, comparison mode does not disturb
   in-progress edits or vice versa.
4. **Browser evidence independently reproduced:** yes, both suites, 16/16,
   from a freshly seeded database against the real committed Playwright specs.
5. **Regression evidence:** full suite, Pint, and PHPStan independently
   reproduced and match the implementer's claims exactly.
6. **May either task proceed to HPO closure?** P6-003: yes, no blocking
   findings — recommend **VERIFIED**. P6-007: the MEDIUM finding should be
   fixed (gate the per-row note on translation existence) and re-reviewed
   before HPO closure, given the task's own brief identifies exactly this
   category of defect as the critical area for P6-007 — recommend
   **CHANGES_REQUESTED**, not a re-open of the read-only/no-mutation
   guarantees, which hold.
7. **Findings that should influence P6-004/P6-005/P6-008:** none of substance
   beyond what P6-002's review already flagged. One note for P6-005: when
   staleness persistence is eventually added, the P6-007 comparison partial's
   conditionals (particularly the "edited" branch) should be revisited
   together with the fix for the MEDIUM finding above, since both concern the
   same code path (the per-row note logic is the natural place a real
   staleness flag would later be consulted, and it must not conflate "no
   translation" with "translation exists but is stale").

## 7. Scope/governance compliance of this review

This review did not modify application code, tests, or task/governance status
files. `tasks/P6-003-text-editing-undo-redo.md` and
`tasks/P6-007-source-translation-comparison.md` remain
`IMPLEMENTED_PENDING_REVIEW`; neither task is marked VERIFIED or DONE by this
document — that transition is for the Human Product Owner, informed by this
review. No later Phase 6/7 task was started or promoted during this review.
