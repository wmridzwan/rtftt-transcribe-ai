# P6-005 Pre-Review — Split / Merge + Translation Invalidation

Task: P6-005
Status: IMPLEMENTED_PENDING_REVIEW (not self-verified, not DONE)
Owner: OpenCode (implementation)
Expected reviewer: Claude Code
Authority: `DECISION-P6-005-READY-001`; the five HPO owner decisions
(`DECISION-P6-005-SPLIT-BOUNDARY-001`, `DECISION-P6-005-MERGE-JOIN-001`,
`DECISION-P6-005-LANGUAGE-PROVENANCE-001`,
`DECISION-P6-005-STALENESS-LIFECYCLE-001`, `DECISION-P6-005-SCHEMA-001`);
frozen `PHASE6-EDITING-DOMAIN-CONTRACT.md`; ADR-025; ADR-022; DC-01.

## 1. Scope delivered

- Structural **split**: one active-revision segment → two ordered children at a
  strict interior boundary, as a new append-only revision.
- Structural **merge**: two or more **adjacent** active-revision segments → one
  segment at the earliest position, as a new append-only revision.
- Explicit nullable **revision-segment language provenance** for mixed-language
  merges.
- Additive **translation staleness schema** and the canonical persisted
  invalidation lifecycle.
- **Atomic** structural revision append + translation invalidation using the
  existing P6-002 CAS/version rules.
- Workspace UI (`data-struct-*`) and translation-invalidation presentation.
- Unit / feature / concurrency tests and real-browser DC-01 evidence.

## 2. Frozen semantics consumed (not redefined)

P6-001 §4/§5/§7/§8/§10 (identity/position, timing invariants, split/merge
identity/position/boundary/language carry, navigation identity, additive schema
shape); P6-002 revision append/CAS/version/undo/redo; P6-003 text editing;
P6-004 timing editing. The `EditKind` / `TranslationInvalidationPolicy` /
`TranslationStalenessReason` taxonomy and precedence are untouched (only an
additive `TranslationStalenessReason::precedence()` mirroring
`EditKind::precedence()` was added).

## 3. Exact structural semantics

### Split

- Two new opaque identities (`struct:<uuid>` via
  `RevisionSegmentIdentity::forStructuralEdit()`), never `machine:<index>`.
- Children at positions `p` and `p+1`; subsequent positions `+1`; contiguous
  `0..n-1`.
- Text `text[0,k)` / `text[k,length)` on Unicode code-point boundaries
  (`mb_substr`).
- Timing `[start, t]` / `[t, end]`.
- Both children inherit the source language; provenance is **not** propagated to
  children (the representation describes a merge composition).
- **Strict interior** boundary (`start < t < end`, `0 < k < length(text)`);
  degenerate splits rejected with a domain validation error and no write.
- `EditKind::Structural` → `SegmentStructureChanged`.

### Merge

- Adjacent-only (contributors must be contiguous in `position`); contributors
  ordered by `position` regardless of input order.
- One new opaque identity at the earliest position; contributing identities
  removed only from the new revision.
- Text joined with exactly one plain space: `implode(' ', texts)`; never trimmed
  or rewritten.
- Timing: earliest-position start → latest-position end; the merged segment must
  itself satisfy the frozen `start <= end` invariant (a merge whose result would
  be invalid is rejected).
- Same-language contributors retain their language; differing contributors →
  `und` + ordered `LanguageProvenance` (`["en","ms"]`).
- `EditKind::Structural` → `SegmentStructureChanged`.

## 4. Schema

- `translations.stale_at` (nullable timestamp), `translations.staleness_reason`
  (nullable string), `translations.stale_caused_by_revision_id` (nullable uuid,
  indexed).
- `transcript_revision_segments.language_provenance` (nullable JSON; cast
  `array`).
- Additive only; no Phase 5 row or segment identity is rewritten.

## 5. Invalidation lifecycle

`EloquentTranslationStalenessWriter` (behind the `TranslationStalenessWriter`
contract):

- first invalidation → `stale_at`, `staleness_reason`, causing revision;
- repeated invalidation → preserve the original `stale_at`; upgrade the reason
  (and causing revision) only when the new reason outranks the stored one by the
  frozen precedence; never downgrade;
- never clears staleness; never rewrites `translation_segments`;
- invalidates **every** translation row for the transcription (per translation
  target identity; no translation → no-op).

## 6. Atomicity

`RevisionService::split()/merge()` wrap the P6-002 `repository->append()` and the
invalidation write in a single `DB::transaction`. A failed invalidation rolls the
structural revision back; a rejected split/merge, a stale base, and an ownership
denial all write nothing. No second concurrency model was introduced.

## 7. Workspace

`transcriptions/partials/structural-toolbar.blade.php` + `transcriptStructural`
Alpine component, using dedicated `data-struct-*` hooks. Row controls
(checkbox / split button) are `x-show="structMode"`. Mutually exclusive with
P6-003/P6-004 modes via `p6-struct-enter` / `p6-text-enter` / `p6-timing-enter`.
Persisted staleness is surfaced in a `data-translation-staleness` block. The
P6-007 comparison partial was **not** modified.

## 8. Tests

- `tests/Unit/Editing/SplitComposerTest.php` — interior split, identity/position,
  multi-byte text, boundary/degenerate rejection, precision, provenance cleared.
- `tests/Unit/Editing/MergeComposerTest.php` — adjacency, join, timing,
  same/mixed language, invalid-timing rejection, ordering.
- `tests/Unit/Editing/LanguageProvenanceTest.php`,
  `tests/Unit/Editing/TranslationStalenessReasonTest.php`.
- `tests/Feature/Editing/SplitMergeWorkspaceTest.php` — HTTP split/merge,
  materialize-then-edit, atomicity-relevant persistence, stale base, ownership,
  reload durability, P6-006 nav, P6-007 non-alignment, machine immutability,
  translation invalidation, Phase 5 segment non-rewriting.
- `tests/Feature/Editing/TranslationStalenessLifecycleTest.php` — first/repeated
  weaker/stronger invalidation, precedence, causing revision, multiple targets,
  no-translation, failed structural no-invalidation, failed invalidation
  rollback, historical content unchanged.
- Updated the now-obsolete schema-absence assertions in
  `TranscriptTimingWorkspaceTest`, `TranscriptEditingWorkspaceTest`,
  `SourceTranslationComparisonTest`, and the P6-002 migration-rollback test
  (now 5 steps including the P6-005 migrations).

## 9. Verification results

| Gate | Result |
|---|---|
| `pest tests/Unit/Editing tests/Feature/Editing` | 195 passed / 801 assertions |
| `php artisan test --compact` (full) | 870 tests, 869 passed, 1 skipped, 2 warnings, 0 failures |
| `pint --dirty` | passed |
| `phpstan analyse --memory-limit=1G` (level 7) | 0 errors |
| P6-005 Playwright (real Chromium, dedicated DB) | 8 passed / 8 |
| P6-003 / P6-004 / P6-006 / P6-007 browser regression | 8 / 10 / 7 / 8 passed |

## 10. Residual findings / honest limitations

- Text/timing edits still do **not** persist staleness (P6-003/P6-004 behaviour
  frozen; the schema-absence assertions were updated to assert no staleness is
  written). P6-005 wires persisted invalidation only into its structural
  operations; the lifecycle service fully implements all reasons and the frozen
  precedence and is proven at the domain level. Retro-wiring text/timing is a
  separate, explicitly scoped change.
- The machine-source first structural edit materializes the initial revision
  then appends the structural revision (mirrors P6-003/P6-004): each step is
  independently CAS-fenced, and validation happens before any write, but a
  concurrent writer between the two steps surfaces as a conflict with a durable,
  recoverable materialized revision.
- A merge whose earliest-position start would exceed its latest-position end is
  rejected (the merged segment must satisfy `start <= end`); this is a
  per-segment validity rule, not a cross-segment monotonicity constraint.
- `RevisionAppendRaceTest` is a process-spawn concurrency test that can flake
  under full-suite resource contention; it passes in isolation (2.9 s). This is
  environmental and independent of P6-005.
- The pre-existing `showRenameModal` console error (Phase 4 debt) is unrelated.

## 11. Recommended independent-review focus

1. Exact adherence to the five owner decisions (strict interior split;
   plain-space adjacent merge; explicit mixed-language provenance; invalidation
   lifecycle preserving `stale_at` and never downgrading; exact schema).
2. Identity rotation: structural children/merges never reuse `machine:<index>`
   or prior identities; old revisions byte-for-byte durable.
3. Atomicity: structural append + invalidation commit or roll back together;
   stale base / ownership / invalid edit write nothing.
4. Phase 5 boundary: `translation_segments` never rewritten or remapped;
   P6-007 alignment stays unavailable for structural segments.
5. P6-006 navigation stays position-based after structural edits.
6. Machine source immutability across split/merge/undo.
7. Reproduce the committed browser harness and regression suites; verify the
   residual findings are environmental / deliberate scope boundaries.

## 12. Explicit non-actions

- P6-005 is **not** self-marked VERIFIED or DONE.
- No P6-008 / P6-009 or new Phase 7 work.
- Phase 5 translations / translation segments were not rewritten or remapped.
- No frozen P6-001..P6-004 semantics were redefined.