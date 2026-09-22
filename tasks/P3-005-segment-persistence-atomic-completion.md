# P3-005 — Segment Persistence + Atomic Completion

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

Persist normalized segment data and establish the canonical atomic completion boundary.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (canonical specification)
- Existing `TranscriptionSegment` model (transcription_id, segment_index, start_seconds, end_seconds, text)
- P3-004 (Transcript Persistence)

## Scope

Persist:

- segment_index
- start_seconds
- end_seconds
- text
- language (BCP 47-compatible or `und`)

Reconcile schema: add `language` column if not present. Establish unique constraint on (transcription_id, segment_index).

Atomic completion boundary: transcript + all segments persist under a safe boundary. Never mark completed before all segments succeed.

## Out of Scope

- Queue infrastructure (P3-006)
- Full schema redesign (minimum additive changes only)

## Dependencies

- P3-004 (Transcript Persistence)

## Acceptance Criteria

1. Segments persist in deterministic order.
2. One language stored per segment.
3. BCP 47-compatible values supported.
4. `und` supported.
5. BM/English/Chinese/Tamil mixed segments persist.
6. Timestamp precision preserved.
7. `(transcription_id, segment_index)` duplication prevented.
8. Retry cannot duplicate segments.
9. Partial segment persistence cannot produce completed state.
10. Transcript + required segments commit under atomic completion strategy.
11. Unicode preserved.
12. All tests pass; Pint clean; PHPStan 0 errors.

## Implementation Notes

### Files Changed

- `app/Actions/TranscriptionResultWriter.php` (new) — segment persistence and
  the atomic completion boundary (shared with P3-004).
- `app/Models/TranscriptionSegment.php` — `language` fillable + BCP 47 enum
  cast; `start_seconds`/`end_seconds` now cast to float.
- `database/factories/TranscriptionSegmentFactory.php` — `language` default.
- `database/migrations/2026_09_18_000001_add_language_and_widen_timestamps_to_transcription_segments_table.php` (new).
- `database/migrations/2026_09_18_000002_add_unique_segment_index_to_transcription_segments_table.php` (new).
- `app/Http/Controllers/TranscriptionExportController.php` — SRT/VTT
  formatters accept fractional seconds (millisecond precision) as a direct
  consequence of the timestamp widening. Whole-second output is unchanged.
- `tests/Feature/Transcription/SegmentPersistenceTest.php` (new).
- `tests/Feature/Transcription/AtomicCompletionTest.php` (new).

### Design

- Each segment persists `segment_index`, `start_seconds`, `end_seconds`,
  `text`, and one `language` value. Segment language is the Batch 1
  per-segment value; `und` remains valid. Transcript-level language is never
  copied into segments.
- Segments are sorted by `segment_index` before insertion, so persistence is
  deterministic.
- Uniqueness: `(transcription_id, segment_index)` is enforced by a database
  unique index. A replay/replace deletes the existing set for the
  transcription and re-inserts inside the same transaction, so a retry cannot
  duplicate logical segments.
- Timestamp precision: the existing integer-second columns could not hold the
  worker's fractional segment timestamps, so the columns were widened to
  `decimal(12,3)` in a new additive/compatible migration (existing integer
  values convert losslessly). Sub-second precision is preserved.

### Atomic completion

`TranscriptionResultWriter::persist()` runs one short transaction:

```
re-read + guard terminal transcription
persist transcript semantic fields
replace the complete segment set
mark the processing attempt Completed
mark the transcription Completed   ← final consequence
commit
```

If any step throws, the whole transaction rolls back: `Completed` is never
visible with incomplete semantic data, and the failure/retry path remains
possible. Worker inference happens outside this transaction (P3-006).

### Verification

- `php artisan test --compact tests/Feature/Transcription/SegmentPersistenceTest.php`
  → 7 passed, 20 assertions.
- `php artisan test --compact tests/Feature/Transcription/AtomicCompletionTest.php`
  → 2 passed, 12 assertions.
- Failure-injection evidence: a `TranscriptionSegment::creating` listener
  throws on the second segment; the test asserts the transcription remains
  `transcribing`, `full_text`/`detected_language` remain null, zero segments
  exist, and the attempt is not completed.
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
