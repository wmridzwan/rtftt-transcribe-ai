# P3-004 — Transcript Persistence

## Status

DONE

## Ownership

Implementation Owner: OpenCode (per AGENTS.md agent model)
Reviewer: Claude Code

## Authorization

Promoted to READY by the Human Product Owner as part of Phase 3 Batch 2
authorization (2026-09-18). Batch 2 authorized tasks: P3-004, P3-005, P3-006.
Batch 3 remains unauthorized.

## Authorized Phase

Phase 3 — Real Transcription Engine (ADR-017)

## Batch

Batch 2

## Objective

Persist canonical transcript-level output safely from the normalized worker result.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (canonical specification)
- Existing `Transcription` model (status, full_text, detected_language, model, timestamps)

## Scope

Reuse/reconcile current Transcription schema. Persist:

- full_text (normalized transcript text)
- detected_language (dominant/primary language)
- model (which whisper model produced this)
- status timestamps (started_at, completed_at)
- relationship to MediaFile
- requested language vs detected language as distinct concepts

## Out of Scope

- Segment persistence (P3-005)
- Queue infrastructure (P3-006)
- Schema redesign (minimum additive migration only if strictly required)

## Dependencies

- P3-003 (real provider producing normalized results)

## Acceptance Criteria

1. Full transcript text persisted.
2. Dominant detected language persisted.
3. Requested-language hint not overwritten incorrectly.
4. Correct MediaFile relation preserved.
5. Cross-user relationship impossible.
6. Unicode/mixed scripts preserved (BM, English, Chinese, Tamil).
7. Long transcript supported within repository constraints.
8. DB failure does not produce false completion.
9. Repeated persistence is idempotent.
10. Existing schema reused where possible.
11. Only minimal additive migration allowed if strictly required.
12. All tests pass; Pint clean; PHPStan 0 errors.

## Implementation Notes

### Files Changed

- `app/Actions/TranscriptionResultWriter.php` (new) — atomic transcript +
  segment persistence and completion boundary (shared with P3-005).
- `app/Models/Transcription.php` — `speech_detected` fillable + boolean cast.
- `database/factories/TranscriptionFactory.php` — `speech_detected` default.
- `database/migrations/2026_09_18_000003_add_speech_detected_to_transcriptions_table.php` (new).
- `config/transcription.php` — canonical model config (`large-v3`).
- `tests/Feature/Transcription/TranscriptPersistenceTest.php` (new).

### Design

- Persists `full_text`, `detected_language` (dominant), `speech_detected`,
  `model`, `started_at`, `completed_at`, `processing_seconds` on the existing
  `transcriptions` table.
- Requested `language` (hint) is never overwritten; `detected_language` is a
  distinct column.
- Ownership integrity is asserted server-side: `transcription.user_id` must
  equal `mediaFile.user_id`. The worker supplies no ownership identity, so it
  cannot override server authority. Cross-user writes are impossible.
- No-speech is persisted as a successful outcome (`text = ""`,
  `detected_language = und`, `speech_detected = false`, no segments); it is
  never converted into a worker/empty-result failure or retry loop.
- Repeated persistence for an already-completed transcription is idempotent
  (no-op), and a write for a terminal transcription never resurrects it.
- Unicode/mixed scripts are stored verbatim; long transcripts are supported
  within the SQLite `TEXT` column.

### Schema

Reused the existing `transcriptions` schema. One additive column
(`speech_detected`, nullable boolean) was required because no existing column
represented the normalized speech-detected semantic result. No Phase 1/2
migration was modified. `duration` reuses the existing `MediaFile` duration
relationship rather than introducing a duplicate transcript concept.

### Verification

- `php artisan test --compact tests/Feature/Transcription/TranscriptPersistenceTest.php`
  → 9 passed, 31 assertions.
- Pint clean; PHPStan 0 errors.

## Review

Review File: reviews/PHASE3-BATCH2-independent-review.md
Review Status: VERIFIED (independent Batch 2 review, 2026-09-18).

## Closure

Closed as DONE by the Human Product Owner on 2026-09-19, based on the
completed independent Batch 2 verification. Canonical transition:
VERIFIED → (HPO closure decision) → DONE. Closure is governance/state
reconciliation only; no implementation change is authorized by this closure.

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
