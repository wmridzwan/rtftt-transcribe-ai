# P6-010 Builder Report — Revision-Aware Export Remediation (F-001)

Owner: OpenCode (Builder). This is a Builder artifact, not an independent
review. P6-010 must not be marked VERIFIED or DONE by the implementer.

## Baseline

- HEAD: `d8a7e01ffe95b8cf5d2c857214ddee65a649c28d`, branch `main`.
- P6-010 READY (`DECISION-P6-010-READY-001`; HPO-P6-010-A APPROVED).
- Pre-existing unrelated residue left untouched (governance docs, P6-005/P6-008
  work, P6-009 gate artifacts, other controllers/views/routes).
- `app/Http/Controllers/TranscriptionExportController.php` confirmed
  unmodified by unrelated work before implementation (no diff, untracked by
  residue).

## Lifecycle

`READY` → `IN_PROGRESS` (2026-09-25, OpenCode) → `IMPLEMENTED_PENDING_REVIEW`
(this report). Not VERIFIED. Not DONE.

## Root cause confirmed

Matches the gate evidence exactly: all four export actions read machine
`transcription_segments` only and never consulted the active revision,
contradicting frozen P6-001 §9. No second revision authority existed;
`RevisionService::active()` + `TranscriptRevision::orderedSegments()` were
the canonical resolution/projection inputs.

## Implementation summary

`app/Http/Controllers/TranscriptionExportController.php` only (plus tests):

- New private `exportRows()` projection: valid non-empty active revision →
  rows from `orderedSegments()` (position order, revision text/timing);
  otherwise the unchanged machine-source path (segment_index order,
  `full_text` no-speech fallback preserved). Machine rows never mutated.
- All four actions take `RevisionService` via method injection and render
  from rows; authorization (`view`), Completed-only gating, filenames,
  content types, DOCX structure, and `SegmentTimestamp` formatting unchanged.
- Ordering semantics mirror the workspace precedent
  (`TranscriptionController::displaySegments()`), which was deliberately left
  untouched to avoid refactoring working code inside a remediation task.

## Files changed (Builder-owned)

- `app/Http/Controllers/TranscriptionExportController.php`
- `tests/Feature/TranscriptRevisionAwareExportTest.php` (new, 8 tests)
- `tasks/P6-010-revision-aware-export-remediation.md` (lifecycle + this report pointer)

Deleted after use: throwaway F-001 probe (4/4 now correct; output below) and
a scratch split/merge debug test. P6-009 artifacts untouched (the committed
F-001 skip stays for the rerun to replace).

## AC mapping

- AC1–AC4: dedicated per-format tests (TXT/SRT/VTT/DOCX from text-edited
  active revision; SRT/VTT timestamp assertions incl. edited 12.5s end).
- AC5: text, timing, split (6 SRT entries, interior boundaries), merge
  (approved plain-space join `Hai  semua ✓` asserted as frozen behaviour),
  branch-A/B.
- AC6: sibling activation flips TXT output without new rows or machine writes.
- AC7: null-active fallback renders machine content; unresolvable-active and
  zero-segment-active paths share the same `exportRows()` null/empty branch
  (defensive; not constructible via approved operations, covered by review).
- AC8: machine snapshots byte-identical around active/fallback exports.
- AC9: intruder 403 ×4 formats; draft-status 403 ×4 formats.
- AC10: `ms/en/zh/ta/und` `✓` strings in all four outputs (DOCX via
  `word/document.xml`).
- AC11: diff audit (this file + tests only), full suite green, translation
  exports untouched.

## Test results (fresh)

- New file: 8 tests, 8 passed, 45 assertions, 1 warning (unidentified empty
  warning detail; same reporter pattern as the 2 pre-existing baseline
  warnings — documented, not a failure).
- Export-related: `TranscriptRevisionAwareExportTest` + `TranscriptExportTest`
  + `TranscriptExportHardeningTest` + `Translation/TranslationExportTest` →
  37 passed.
- F-001 probe rerun (bounded, remediation evidence only): 4/4 formats now
  contain the edited active text (baseline was 4/4 machine text).
- Full suite `php artisan test --compact`: 900 tests, 898 passed,
  3488 assertions, 2 skipped (pre-existing 2FA + P6-009 F-001 skip, retained
  for the rerun), 3 warnings, 0 failures.
- `composer lint:check` (Pint): passed. `composer types:check` (PHPStan L7):
  0 errors (one genuine `list` vs `array<int>` return-type error found and
  fixed via typed iteration, no suppression).

## Deviations

NONE. Judgment calls (documented, contract-consistent): workspace
`displaySegments()` not refactored (contamination control); merge double-space
asserted as approved join semantics; trim-on-edit noted as frozen P6-003
behaviour in test comments.

## Resulting lifecycle

`IMPLEMENTED_PENDING_REVIEW`. Next: commission independent review of P6-010
(Claude Code). Do not VERIFIED/DONE without it. P6-009 remains
FAILED/INCOMPLETE — rerun not yet authorized. Phase 6 remains OPEN.
