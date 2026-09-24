# Phase 6 — P6-003 Closure + P6-007 Corrective Batch Report

Date: 2026-09-24
Tasks: P6-003 (Text Editing + Undo/Redo) — closure; P6-007 (Source / Translation
Comparison) — MEDIUM corrective
Status: **P6-003 = DONE; P6-007 = IMPLEMENTED_PENDING_REVIEW** (not
self-verified, not DONE)
Authority: `DECISION-P6-003-CLOSURE-001`; `DECISION-P6-007-SCOPE-001`;
`DECISION-P6-003-P6-007-READY-BATCH-001`;
`reviews/P6-003-P6-007-independent-review.md`; DC-01; ADR-025.
Reviewed checkpoint: `985d2c5` (working tree clean at start).

## 0. Explicit confirmations

- **P6-003 was accepted VERIFIED and closed DONE** by the HPO
  (`DECISION-P6-003-CLOSURE-001`). The independent review artifact is preserved
  unchanged.
- **P6-007 was not marked VERIFIED or DONE.** It remains open and was corrected,
  then re-submitted as `IMPLEMENTED_PENDING_REVIEW` for a fresh independent
  corrective re-review.
- **The comparison remains presentation-only.** No `ComparisonBuilder` redesign,
  no machine `segment_index` alignment change, no `TranscriptComparison` /
  revision-provenance semantic change, no P6-007 ownership-boundary change.
- **No staleness was inferred or persisted.** No `translations.stale_at` /
  `staleness_reason` column, model attribute, or write path was added; the
  corrected conditional consults only persisted translation existence.
- **No later task was started or promoted.** P6-004, P6-005, P6-008, P6-009 and
  every Phase 7 task (other than the already-DONE P7-005) were untouched.
- **The P6-002 evidence gap was not fabricated.** P6-002 is not reopened; the
  record-completeness note is preserved.

## 1. P6-003 closure decision

- Independent review: `reviews/P6-003-P6-007-independent-review.md` →
  **P6-003 = VERIFIED**, no remaining BLOCKER/HIGH/MEDIUM.
- Independently confirmed: append-only text editing; expected-base concurrency
  (re-checked at the HTTP boundary); stale conflict handling; strict-ancestor
  undo; unique-child redo; branch-after-undo; machine-source immutability;
  ownership/isolation; reload durability; real-browser behavior; no
  translation-staleness persistence; no P6-004/P6-005/P6-008 scope leakage.
- HPO decision: `DECISION-P6-003-CLOSURE-001` records the
  `VERIFIED → DONE` transition. No code changed for the closure.
- Recorded in `DECISIONS.md`, `DECISION_QUEUE.md`,
  `tasks/P6-003-text-editing-undo-redo.md`, `CURRENT_STATE.md`, `AGENTS.md`,
  `PHASE6-7-ELIGIBILITY-MATRIX.md` §P.

## 2. Exact P6-007 fix

MEDIUM finding: the per-row `data-comparison-revision-edited-note`
("Edited after the translation was produced") rendered for an edited,
machine-aligned revision even when **no persisted translation existed**,
asserting a false chronology.

Fix (rendering rule only):

- `app/Comparison/ComparisonRow.php` — added
  `hasEditedRevisionWithPersistedTranslation()`, true only when
  `revisionEdited()` **and** `hasTranslation()` (i.e. the row is machine-aligned,
  its text differs from the machine source, **and** a persisted translation
  exists for that row). `revisionEdited()` is unchanged as the pure text
  comparison.
- `resources/views/transcriptions/partials/source-translation-comparison.blade.php`
  — the per-row note is now rendered only under
  `$row->hasEditedRevisionWithPersistedTranslation()` instead of
  `$row->revisionEdited()`.

Truthfulness rules preserved:

- **No translation** → only the factual `data-comparison-no-translation` state;
  no translation-produced claim, no fresh/stale implication, no chronology.
- **Translation exists + edited revision** → the explicit provenance/mismatch
  note still states the persisted translation is of the machine source and the
  revision has diverged; it does not imply the translation belongs to the edited
  revision.
- **Structural incompatibility** → still no index-based remapping; unaligned
  revision rows never receive translation content or the note.

No staleness persistence, freshness inference, translation-lifecycle write, or
P6-005 behavior was added.

## 3. Tests added

- `tests/Feature/Comparison/SourceTranslationComparisonTest.php`
  - new: edited revision + **no** translation → no-translation state shown; the
    edited note, mismatch note, and note text are all asserted **absent**;
    builder rows report `hasTranslation() === false` and
    `hasEditedRevisionWithPersistedTranslation() === false`.
  - strengthened: textually identical revision + translation asserts the edited
    note is absent (no false edited-after-translation message).
  - strengthened: edited revision + persisted translation asserts the
    provenance/mismatch note renders (positive case).
  - strengthened: structurally incompatible revision asserts neither the edited
    note nor the mismatch note renders and no translation is mapped onto the
    unaligned rows.
- `tests/Unit/Comparison/ComparisonRowTest.php` (new) — pins the predicate:
  edited + no translation → false; edited + translation → true; unedited →
  false; unaligned → false.

## 4. Browser evidence

- Spec updated:
  `verification/p6-007/source-translation-comparison.spec.js` — the `none`
  fixture (edited revision, no translation) now asserts
  `[data-comparison-revision-edited-note]` count **0**,
  `[data-comparison-mismatch-note]` count **0**, and that the literal note text
  is absent.
- Fixtures unchanged and reproducible via
  `verification/p6-007-seed.php` + `verification/p6-007-fixtures.json`; the
  tracked result JSON is refreshed.
- P6-007 suite: **8 passed / 8** (real Chromium, freshly seeded P6-007 DB).
- P6-003 suite re-run (shared workspace regression): **8 passed / 8**.
- Evidence doc updated:
  `verification/p6-007/P6-007-BROWSER-VERIFICATION-EVIDENCE.md`.

## 5. Full-suite / static results

- Targeted: `tests/Feature/Comparison` + `tests/Unit/Comparison` +
  `tests/Feature/Editing` + `tests/Unit/Editing` → 142 passed / 561 assertions.
- Full PHP suite: **803 tests, 802 passed, 1 skipped, 0 failures** (2
  pre-existing warnings); +6 tests versus the pre-corrective 797.
- Pint (`vendor/bin/pint --dirty --format agent`): passed.
- PHPStan (Larastan, `composer types:check`): **0 errors**.
- Both Playwright suites: 16/16 passed (P6-007 8, P6-003 8).

## 6. Changed files

Application / tests:

- `app/Comparison/ComparisonRow.php`
- `resources/views/transcriptions/partials/source-translation-comparison.blade.php`
- `tests/Feature/Comparison/SourceTranslationComparisonTest.php`
- `tests/Unit/Comparison/ComparisonRowTest.php` (new)

Browser evidence:

- `verification/p6-007/source-translation-comparison.spec.js`
- `verification/p6-007/p6-007-browser-results.json` (refreshed)
- `verification/p6-007/P6-007-BROWSER-VERIFICATION-EVIDENCE.md`
- `verification/p6-003/p6-003-browser-results.json` (refreshed re-run)

Governance:

- `tasks/P6-003-text-editing-undo-redo.md` (DONE)
- `tasks/P6-007-source-translation-comparison.md` (corrective; re-submitted)
- `DECISIONS.md`, `DECISION_QUEUE.md`
  (`DECISION-P6-003-CLOSURE-001`)
- `CURRENT_STATE.md`, `AGENTS.md`,
  `PHASE6-7-ELIGIBILITY-MATRIX.md` §P
- this report

Independent review artifact `reviews/P6-003-P6-007-independent-review.md` is
**unchanged**.

## 7. Handoff for fresh independent P6-007 re-review

Focus for the reviewer:

1. Confirm the per-row note renders **only** under
   `hasEditedRevisionWithPersistedTranslation()` and never in the
   edited-revision-with-no-translation state.
2. Confirm the positive case (translation exists + edited machine-aligned
   revision) still renders the provenance/mismatch note truthfully and does not
   imply the translation belongs to the edited revision.
3. Confirm no staleness is inferred or persisted and the comparison remains
   presentation-only / no-write.
4. Confirm structural incompatibility still never maps translation by index.
5. Reproduce the P6-007 feature/unit tests and Playwright suite from a freshly
   seeded DB.

## 8. P6-002 evidence gap (retained)

`reviews/P6-002-corrective-independent-re-review.md` remains absent. P6-002 is
**not** reopened; no artifact was fabricated or reconstructed from summaries.
The accepted reviewer should add the actual artifact when available.
