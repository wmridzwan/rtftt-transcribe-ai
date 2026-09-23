# P6-007 — Pre-Review Handoff (Source / Translation Comparison)

Date: 2026-09-23
Task: `tasks/P6-007-source-translation-comparison.md`
Status: `IMPLEMENTED_PENDING_REVIEW` (not VERIFIED, not DONE)
Authority: `DECISION-P6-007-READY-001` /
`DECISION-P6-003-P6-007-READY-BATCH-001`; `DECISION-P6-007-SCOPE-001`;
`DECISION-PHASE6-AUTHORIZATION-001`; ADR-025; closed Phase 5; verified P6-002
(`DECISION-P6-002-CLOSURE-001`); DC-01
Reviewer: Claude Code (fresh independent review required)

## Scope discipline

Implemented only the canonical presentation-only scope fixed by
`DECISION-P6-007-SCOPE-001`:

- a read-only comparison view inside the existing transcript workspace;
- machine source, active revision, and persisted Phase 5 translation;
- explicit alignment semantics over the machine `segment_index` baseline.

Not done: no machine-source mutation; no revision-history mutation; no
translation-invalidation persistence; no silent translation remap; no translation
lifecycle ownership; no schema change; no P6-003 text editing; no P6-004/P6-005/
P6-008/P6-009; no Phase 7 work.

## 1. What was built

### Read model — `app/Comparison/`

- `ComparisonRow` (new): one read-only row — `machineIndex`, `machineText`,
  `machineLanguage`, `translatedText`, `revisionPosition`, `revisionText`,
  `revisionAligned`. `revisionEdited()` is a factual text comparison, not a
  staleness inference.
- `TranscriptComparison` (new): `rows` + `hasActiveRevision`,
  `activeRevisionVersion`, `hasTranslation`, `translationTargetLanguage`, and
  helpers `hasEditedActiveRevision()` / `hasUnalignedRevisionSegments()`.
- `ComparisonBuilder` (new): reads the machine segments, the latest **completed**
  translation (newest by id), and the active revision. It aligns translation to
  machine `segment_index`; it aligns a revision segment to a machine index only
  when its identity is machine provenance (`machine:<index>`). Segments with any
  other identity (for example a future structural edit) are appended as
  unaligned rows and are **never** mapped by array index.

### Presentation — comparison partial

`resources/views/transcriptions/partials/source-translation-comparison.blade.php`
(new) renders a `Transcript` / `Compare` toggle and a read-only table with
Machine source / Active revision / Translation columns, using dedicated
`data-compare-*` hooks. States rendered:

- no active revision → the machine source is authoritative;
- machine-aligned translation → shown next to the machine source;
- edited active revision → the translation is presented as belonging to the
  machine source, with an explicit mismatch note and a per-row "edited after the
  translation was produced" note;
- no translation → a factual "No translation available" state (machine vs active
  revision comparison still works);
- structurally changed revision segment → revision text shown with alignment
  marked unavailable and the translation cell left as `—` (no remap).

`show.blade.php` adds a workspace-level `transcriptView` toggle and includes the
partial. The partial is **read-only**: it contains no forms and triggers no
request. No freshness/staleness label is ever rendered.

## 2. Preserved semantics (explicit)

- **No writes**: the comparison is a pure read model; feature tests assert
  revision/translation row counts and `updated_at` values are unchanged across
  loads.
- **No machine/revision mutation**: no code path writes machine or revision
  tables.
- **No staleness persistence/inference**: P6-007 writes nothing to
  `translations`; the `stale_at` / `staleness_reason` columns do not exist and
  are not added or read. No "fresh/stale" state is derived.
- **Alignment baseline honoured**: `translation_segments.segment_index` aligns to
  the machine source; an edited revision is never silently shown as the source of
  the translation; structurally incompatible revisions are not index-mapped.
- **P6-006 / reserved Phase 4 hooks**: the comparison uses its own `data-compare-*`
  hooks and leaves `data-filter-language`, `data-nav-seconds`, `data-seek-seconds`,
  and `data-segment-language` intact.
- **Authorization**: the workspace (and therefore the comparison) requires
  `TranscriptionPolicy::view`; a non-owner receives 403.

## 3. Files changed

Application:

- `app/Comparison/ComparisonRow.php` (new)
- `app/Comparison/TranscriptComparison.php` (new)
- `app/Comparison/ComparisonBuilder.php` (new)
- `app/Http/Controllers/TranscriptionController.php`
- `resources/views/transcriptions/show.blade.php`
- `resources/views/transcriptions/partials/source-translation-comparison.blade.php` (new)

Tests:

- `tests/Feature/Comparison/SourceTranslationComparisonTest.php` (new) — 8 tests /
  47 assertions: machine-authoritative + no-translation state; `segment_index`
  alignment; identical revision alignment; edited-revision mismatch; structural
  alignment-unavailable + no remap; no-writes/no-staleness; isolation; P6-006 /
  Phase 4 hooks intact.

Verification (DC-01):

- `verification/p6-007-seed.php`, `verification/p6-007-auth.setup.js`,
  `verification/playwright.p6-007.config.js`,
  `verification/p6-007/source-translation-comparison.spec.js`,
  `verification/p6-007/README.md`,
  `verification/p6-007/P6-007-BROWSER-VERIFICATION-EVIDENCE.md`,
  `verification/p6-007/p6-007-browser-results.json`.

Governance: `tasks/P6-007-source-translation-comparison.md`,
`PHASE6-7-ELIGIBILITY-MATRIX.md` §O, `DECISIONS.md`, `DECISION_QUEUE.md`,
`CURRENT_STATE.md`.

## 4. Verification results

| Gate | Result |
|---|---|
| `php artisan test --compact tests/Feature/Comparison/SourceTranslationComparisonTest.php` | 8 passed / 47 assertions |
| `php artisan test --compact` (full suite) | 797 tests, 796 passed, 1 skipped, 0 failures |
| `vendor/bin/pint` (changed/new files) | passed |
| `phpstan analyse` (level 7) | 0 errors |
| P6-007 Playwright (real Chromium, dedicated DB) | 8 passed / 8 |

Browser evidence: `verification/p6-007/P6-007-BROWSER-VERIFICATION-EVIDENCE.md`.
Required coverage all observed passing: comparison toggle/view; source vs active
revision; source/revision vs translation; no-translation state;
authorization/isolation; no mutation from comparison actions.

## 5. Residual findings / honest limitations

- A structurally changed revision currently shows its own revision segments as
  unaligned rows (revision text + "alignment unavailable") and leaves the
  translation cell empty; the machine rows then read "No matching revision
  segment". This is a deliberate "do not guess" presentation, not index mapping.
  The exact visual treatment is an implementation detail per the contract.
- No translation-selection UI (target language chooser) is provided: P6-007 shows
  the newest completed translation. Where a staleness marker is later added by
  P6-005 it may be consumed only through a separately approved contract.
- The comparison is server-rendered and the toggle is client-side; no comparison
  state is persisted (and must not be).
- No BLOCKER/HIGH/MEDIUM finding is claimed absent by the implementer; the
  reviewer decides.

## 6. Independent-review focus

1. Confirm presentation-only: no writes to machine source, revision history, or
   translation state; no staleness column added/read/inferred.
2. Confirm alignment: machine `segment_index` baseline; edited revision shown with
   an explicit mismatch; structural revisions never index-mapped.
3. Confirm no-translation is a factual first-class state and comparison still
   works without it.
4. Confirm P6-006 / reserved Phase 4 hooks are intact and that P6-007 did not
   redefine P6-001/P6-002 semantics.
5. Reproduce the browser evidence from the committed harness.

P6-007 remains `IMPLEMENTED_PENDING_REVIEW`. The implementation owner has not
self-verified and has not promoted or started any later task.
