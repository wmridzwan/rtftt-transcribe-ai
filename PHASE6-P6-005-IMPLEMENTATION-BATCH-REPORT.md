# Phase 6 — P6-005 Split / Merge + Translation Invalidation — Implementation Batch Report

Date: 2026-09-24
Task: P6-005 (Split / Merge + Translation Invalidation)
Status: **P6-005 = IMPLEMENTED_PENDING_REVIEW** (not self-verified, not DONE)
Authority: `DECISION-P6-005-READY-001`; the five owner decisions
(`DECISION-P6-005-SPLIT-BOUNDARY-001`, `DECISION-P6-005-MERGE-JOIN-001`,
`DECISION-P6-005-LANGUAGE-PROVENANCE-001`,
`DECISION-P6-005-STALENESS-LIFECYCLE-001`, `DECISION-P6-005-SCHEMA-001`);
`DECISION-PHASE6-AUTHORIZATION-001`; ADR-025; ADR-022; frozen
`PHASE6-EDITING-DOMAIN-CONTRACT.md`; DC-01.

## 0. Explicit confirmations

- The five P6-005 owner decisions are recorded **DECIDED** in `DECISION_QUEUE.md`
  and durably in `DECISIONS.md` (P6-005 Owner Decisions).
- P6-005 was promoted **READY** and authorized for implementation
  (`DECISION-P6-005-READY-001`), recorded in `DECISION_QUEUE.md`, `DECISIONS.md`,
  `tasks/P6-005-split-merge-translation-invalidation.md`, `CURRENT_STATE.md`,
  `AGENTS.md`, `plan.md`, `PHASE6-7-ELIGIBILITY-MATRIX.md` §T, and
  `PHASE5-7-DEPENDENCY-GRAPH.md`.
- **No P6-008 / P6-009 and no new Phase 7 work occurred.**
- **No Phase 5 translation rows or translation segments are rewritten or
  remapped.**
- P6-005 is **not** self-marked VERIFIED or DONE; it is
  `IMPLEMENTED_PENDING_REVIEW` and handed to a fresh independent review.
- P6-002/P6-004/P6-007 record-completeness notes remain intact; no artifact was
  fabricated.

## 1. Owner decisions recorded (governance)

| Decision | Resolution |
|---|---|
| `DECISION-P6-005-SPLIT-BOUNDARY-001` | Strict interior boundary only (`start < t < end`, `0 < k < length(text)`); degenerate/boundary splits rejected; no zero-duration or empty structural children; does not change the general zero-length timing rule. |
| `DECISION-P6-005-MERGE-JOIN-001` | Adjacent-only merge; one canonical plain-space separator; contributor order by position; no trimming / punctuation rewriting / sentence inference; timing = earliest-position start → latest-position end. |
| `DECISION-P6-005-LANGUAGE-PROVENANCE-001` | Split children inherit the source language; same-language merge retains it; mixed-language merge → `und` + explicit ordered provenance; no silent first-language selection; no redetection. |
| `DECISION-P6-005-STALENESS-LIFECYCLE-001` | Per translation row; first invalidation sets `stale_at`/reason/cause; repeated invalidation preserves `stale_at` and upgrades reason by frozen precedence only; no downgrade; never clears on retranslation; failed retranslation never restores currency; stale output remains historical. |
| `DECISION-P6-005-SCHEMA-001` | Additive `translations.stale_at` / `staleness_reason` / `stale_caused_by_revision_id`; additive revision-segment `language_provenance`; preserve Phase 5 rows/segments/identity; no generic metadata field. |

## 2. P6-005 READY promotion and final contract

`DECISION-P6-005-READY-001` promotes P6-005
`CONTRACT_AUTHORED / READY-ELIGIBLE AFTER CONTRACT` → **READY** and authorizes
implementation. The canonical contract
(`tasks/P6-005-split-merge-translation-invalidation.md`) is reconciled to
incorporate all five decisions and now fully specifies split boundaries,
identities/positions/text/timing/language, merge adjacency/text/timing,
mixed-language provenance, the invalidation lifecycle, schema ownership, and
concurrency/atomicity. P6-001..P6-004 semantics are not redefined.

## 3. Final structural semantics

### Split
One revision segment → two ordered children at the original position, as a new
append-only revision derived from the stated base. Two new opaque identities
(`struct:<uuid>`; never `machine:<index>`); children at `p`/`p+1`; subsequent
positions `+1`; contiguous `0..n-1`; `text[0,k)` / `text[k,length)`; `[start,t]` /
`[t,end]`; source language inherited; strict interior boundary enforced; prior
revision and machine source immutable; `EditKind::Structural` →
`SegmentStructureChanged`.

### Merge
Two or more adjacent segments → one at the earliest position. Contributors
ordered by position; one new opaque identity; text joined with one plain space
(no trimming/rewriting); timing earliest-position start → latest-position end;
same-language retains language, mixed → `und` + ordered provenance; the merged
segment must satisfy `start <= end`; prior revisions and machine source
immutable; `EditKind::Structural` → `SegmentStructureChanged`.

## 4. Schema added (additive)

- `translations.stale_at` (timestamp, nullable)
- `translations.staleness_reason` (string(50), nullable, indexed)
- `translations.stale_caused_by_revision_id` (uuid, nullable, indexed)
- `transcript_revision_segments.language_provenance` (text/JSON, nullable)

Migrations: `2026_09_24_000001_add_staleness_to_translations_table.php`,
`2026_09_24_000002_add_language_provenance_to_transcript_revision_segments_table.php`.
Rollback verified by the updated migration-rollback test (5 steps).

## 5. Invalidation lifecycle implementation

`EloquentTranslationStalenessWriter` (behind `App\Editing\TranslationStalenessWriter`)
invalidates every translation row for the transcription:

- first invalidation: set `stale_at`, `staleness_reason`, causing revision;
- repeated invalidation: preserve the original `stale_at`; upgrade the reason
  and causing revision only when the new reason outranks the stored reason by the
  frozen precedence (`SegmentStructureChanged > TimingChanged > SourceTextChanged`);
  never downgrade;
- no lifecycle transition, never clears staleness, never rewrites
  `translation_segments`;
- zero translations → no-op.

## 6. Atomicity strategy

`RevisionService::split()` / `merge()` compose against the base, derive a new
revision, then `DB::transaction(append + invalidate)` using the existing P6-002
`EloquentRevisionRepository::append` CAS/version rules (no second concurrency
model). Failure of the invalidation rolls back the structural append; stale
base, ownership denial, and invalid edit write nothing.

## 7. Split/merge implementation

- `App\Editing\SplitComposer`, `App\Editing\MergeComposer`,
  `App\Editing\LanguageProvenance`,
  `App\Editing\RevisionSegmentIdentity::forStructuralEdit()`,
  `App\Editing\RevisionSegmentData::$languageProvenance`.
- `App\Editing\RevisionService::split()` / `merge()`;
  `App\Editing\Persistence\EloquentTranslationStalenessWriter`.
- `App\Http\Controllers\TranscriptRevisionController::split()` / `merge()` +
  `POST /transcriptions/{transcription}/revisions/split|merge`.
- Workspace: `transcriptions/partials/structural-toolbar.blade.php`,
  `transcriptStructural` Alpine component, row `data-struct-*` hooks, and a
  `data-translation-staleness` presentation block.
- `TranscriptionController` projects `segment_key` / `text_length` and the stale
  translations; `displaySegments` unchanged for existing consumers.

## 8. Browser evidence (DC-01)

`verification/playwright.p6-005.config.js` +
`verification/p6-005/split-merge.spec.js` →
`verification/p6-005/p6-005-browser-results.json`: **8 passed / 8, 0
unexpected**. Coverage: valid split + reload + invalidation surfaced + P6-006
navigation; boundary split rejected; valid adjacent merge (mixed-language `und`);
non-adjacent merge rejected; stale structural conflict; cancel/no write;
ownership denial; machine unchanged + P6-007 alignment unavailable + undo to the
machine copy. Evidence doc:
`verification/p6-005/P6-005-BROWSER-VERIFICATION-EVIDENCE.md`.

## 9. Concurrency / atomicity evidence

- `tests/Feature/Editing/TranslationStalenessLifecycleTest.php`:
  failed invalidation rolls back the structural revision; failed structural edit
  does not invalidate.
- `tests/Feature/Editing/SplitMergeWorkspaceTest.php`: stale base conflict;
  ownership denial; rejected edits write nothing; machine immutability;
  Phase 5 segment non-rewriting.
- `tests/Feature/Editing/RevisionConcurrencyTest.php` and the process-spawn
  `RevisionAppendRaceTest` (P6-002 CAS/version rules) remain green; the latter
  passes in isolation and is an environmental full-suite flake (carried note).

## 10. Full-suite / static results

| Gate | Result |
|---|---|
| `pest tests/Unit/Editing tests/Feature/Editing` | 195 passed / 801 assertions |
| `php artisan test --compact` (full PHP) | 870 tests, 869 passed, 1 skipped, 2 warnings, 0 failures |
| `pint --dirty` | passed |
| `phpstan analyse --memory-limit=1G` (level 7) | 0 errors |
| P6-003 / P6-004 / P6-006 / P6-007 browser regression | 8 / 10 / 7 / 8 passed |

## 11. Residual findings / honest limitations

- Text/timing edits do not persist staleness (P6-003/P6-004 behaviour frozen);
  P6-005 owns and wires the persisted marker only for structural operations, while
  the lifecycle service implements every reason and the frozen precedence (proven
  at the domain level). Retro-wiring text/timing is a separate, explicitly scoped
  change.
- Machine-source first structural edit materializes the initial revision then
  appends the structural revision (mirrors P6-003/P6-004); validation precedes
  any write, but a concurrent writer between the two steps surfaces as a conflict
  with a durable recoverable materialized revision.
- A merge whose earliest-position start would exceed its latest-position end is
  rejected (per-segment `start <= end` validity; not a cross-segment monotonicity
  constraint).
- `RevisionAppendRaceTest` is a process-spawn test that can flake under
  full-suite resource contention; it passes in isolation. Environmental, not a
  P6-005 regression.
- The pre-existing `showRenameModal` console error (Phase 4 debt) is unrelated.

## 12. Task state and fresh-review handoff

- `P6-005 = IMPLEMENTED_PENDING_REVIEW`; **not** VERIFIED, **not** DONE.
- Pre-review artifact: `reviews/pre-review/P6-005-pre-review.md`.
- Expected reviewer: Claude Code (independent). The reviewer should reproduce the
  committed browser harness and regression suites and verify exact adherence to
  the five owner decisions, atomicity, the Phase 5 boundary, P6-006 navigation,
  P6-007 non-alignment, and machine immutability.
- No P6-008 / P6-009 and no new Phase 7 work occurred.