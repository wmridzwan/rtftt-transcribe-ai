# P3-005 — Segment Persistence + Atomic Completion

## Status

BACKLOG

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: UNASSIGNED

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

## Review

Review File: None yet.
Review Status: PENDING

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
