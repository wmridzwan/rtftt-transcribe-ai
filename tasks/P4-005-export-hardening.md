# P4-005 — Export Hardening

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

Verify and harden the existing transcript export over persisted completed
transcripts for TXT, SRT, VTT, and DOCX, adding regression coverage for
multilingual/Unicode content and millisecond timestamp correctness. This is a
hardening/verification task, not a greenfield exporter.

## Context

- ADR-019; `PHASE4-PLANNING.md` (D4-03 decided)
- P4-001 (export contract and canonical timestamp rules)
- Existing: `app/Http/Controllers/TranscriptionExportController.php`
  (exports TXT/SRT/VTT/DOCX from persisted segments; Completed-gated;
  ownership-authorized); routes `transcriptions.export.txt|srt|vtt|docx`
- Existing coverage: `tests/Feature/TranscriptExportTest.php`
- Persisted segment timestamps: `decimal(12,3)`

## Scope

Implement only:

- hardening and verification of the existing export implementation; do not
  rebuild the controller or remove/rename routes or formats;
- completed-transcription gating retained;
- authorization retained;
- persisted real segments as the source of truth;
- UTF-8 / multilingual correctness for `ms`, `en`, `zh`, `ta`, `und`;
- millisecond timestamp correctness for SRT and VTT, including carry
  normalization (no `,1000` / `.1000`);
- deterministic ordering by `segment_index`;
- no-speech/empty transcript behavior;
- DOCX Unicode correctness and no regression;
- regression tests covering the above.

## Export Source-of-Truth Contract

- TXT: title plus ordered persisted segments; when no segments exist, use
  `full_text`.
- DOCX: title plus ordered persisted segments; when no segments exist, use
  `full_text`.
- SRT: ordered persisted segments only; no segments → empty output.
- VTT: `WEBVTT` header plus ordered persisted segments; no segments →
  header-only output.
- Exports must never rerun transcription or provider inference and must be
  deterministic from persisted Phase 3 state.

## No-Speech / Empty Behavior

For `completed` + `speech_detected=false` + `text=""` + no segments:

- TXT: title plus empty `full_text` (no error).
- SRT: empty output (no error).
- VTT: header-only output (no error).
- DOCX: title only (no error).
- No-speech is a valid success outcome, never treated as a failure.

## Out of Scope

Do not implement:

- new export formats;
- translation export (Phase 5);
- transcript or segment editing (D4-05; Phase 6);
- any schema/migration change;
- a rewrite of the export controller or its routes.

## Dependencies

Requires:

- P4-001 (Transcript Experience Contract) — VERIFIED and closed DONE.

## Acceptance Criteria

1. TXT, SRT, VTT, and DOCX exports remain available at their existing routes.
2. Only completed transcriptions can be exported (non-completed → 403).
3. Authorization/ownership is enforced (cross-user → 403).
4. Exports use persisted ordered segments; ordering is deterministic by
   `segment_index`.
5. UTF-8/multilingual content round-trips for `ms`, `en`, `zh`, `ta`, and `und`
   in TXT, SRT, VTT, and DOCX.
6. SRT timestamps use `HH:MM:SS,mmm` and VTT uses `HH:MM:SS.mmm`, with correct
   millisecond carry; no output contains `,1000` or `.1000`.
7. TXT/DOCX fall back to `full_text` when no segments exist.
8. SRT is empty and VTT is header-only when no segments exist.
9. No-speech completed transcript exports without error per §No-Speech.
10. `Content-Type` and `Content-Disposition` filename remain correct.
11. DOCX export is not regressed (valid document containing persisted text).
12. Large transcripts within current product limits export without error.
13. Exports never invoke transcription/provider inference.
14. Relevant tests pass; Pint clean; PHPStan 0 errors.
15. No unrelated functionality is changed.

## Test Requirements

Minimum tests:

- each format exports a completed transcription;
- non-completed export denied (all four formats);
- cross-user export denied (all four formats);
- multilingual/Unicode round-trip for `ms`/`en`/`zh`/`ta`/`und`;
- SRT/VTT millisecond formatting including carry-boundary values;
- deterministic ordering by `segment_index` when rows are created out of order;
- no-speech/empty behavior for each format;
- DOCX regression (valid archive, persisted text present);
- filename and `Content-Type` assertions.

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Unicode corruption | UTF-8 round-trip tests for all five languages |
| Timestamp rounding (`1000`) | Carry-normalization + boundary tests |
| DOCX regression | Archive/document.xml assertion test |
| Format removal/route change | Explicitly forbidden; existing route tests retained |
| Provider rerun on export | Assert exports read persisted state only |

## Implementation Notes

### Files Changed

- `app/Http/Controllers/TranscriptionExportController.php` — SRT/VTT timestamps
  now use the P4-001 `SegmentTimestamp` primitive; the local `formatSrtTime()` /
  `formatVttTime()` methods were removed. TXT/DOCX source-of-truth behavior is
  unchanged.
- `tests/Feature/TranscriptExportHardeningTest.php` (new).

### Important Decisions

- Reused the P4-001 canonical formatter for SRT (`HH:MM:SS,mmm`) and VTT
  (`HH:MM:SS.mmm`); no second timestamp implementation. Millisecond carry cannot
  emit `,1000`/`.1000`.
- Source of truth preserved: TXT/DOCX = title + ordered persisted segments
  (fallback to `full_text` when empty); SRT = ordered segments only (empty when
  none); VTT = `WEBVTT` header + ordered segments (header-only when none).
- Completed-only gating and ownership authorization unchanged. Exports read
  persisted state only and never invoke the transcription provider (asserted).

### Known Limitations

- None new. The display accessor
  `TranscriptionSegment::getFormattedStartAttribute` is intentionally unchanged
  (display is out of P4-005 scope; P4-001 documented the deferral).

## Verification

Commands executed on 2026-09-19 (PHP 8.4, Pest 5.1):

- `vendor/bin/pest tests/Feature/TranscriptExportHardeningTest.php tests/Feature/TranscriptExportTest.php`
  → 21 passed, 116 assertions.
- Full wave suite: 422 tests, 421 passed, 1 skipped, 1400 assertions, 2 warnings.
- PHPStan 0 errors; Pint passed.

Result:

PASSED

## Review

Review File: `reviews/P4-005-independent-review.md`

Review Status: VERIFIED (independent review, 2026-09-20). No BLOCKER/HIGH/
MEDIUM findings. Non-blocking: LOW-1 (no-speech DOCX regression test only
asserts non-empty byte length, which any valid archive would satisfy
regardless of content; source inspection confirms the actual title-only
behavior is correct). Confirmed a single canonical `SegmentTimestamp`
primitive is now used for SRT/VTT with the old `formatSrtTime`/
`formatVttTime` methods fully removed (zero remaining occurrences), explicit
`orderBy('segment_index')` at every export call site, no provider
re-invocation (mocked and asserted), and meaningful DOCX Unicode round-trip
verification via real `ZipArchive`/`document.xml` inspection. All reported
quality gates (focused suite 21/116, full suite 422/421/1/~1400/2, PHPStan 0
errors, Pint clean) were independently reproduced with an exact or
equivalent match. Eligible for Human Product Owner closure to DONE.

## Closure

Closed as DONE by the Human Product Owner on 2026-09-20
(DECISION-P4-005-CLOSURE-001), based on `reviews/P4-005-independent-review.md`
(P4-005 = VERIFIED; no BLOCKER/HIGH/MEDIUM; LOW-1 non-blocking, retained).

Canonical transition: VERIFIED → (HPO closure decision) → DONE. No
implementation or test change is authorized.

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED. P4-006 depends on
this task being VERIFIED and closed DONE.
