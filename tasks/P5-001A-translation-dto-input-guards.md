# P5-001A — Translation DTO Input Guards (Follow-Up)

## Status

VERIFIED — independent review `reviews/P5-001A-independent-review.md`
(2026-09-21) returned VERIFIED with no BLOCKER/HIGH/MEDIUM/LOW (one INFO).
Not DONE; HPO closure required.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 5 — Translation (ADR-022)

## Origin

`reviews/P5-001-independent-review.md` §5: LOW-1
(`TranslationSegmentData` accepts `NAN`/`INF` timestamps) and LOW-2
(`TranslationInvocation` accepts duplicate source segment indices). Referenced
follow-up per `.ai/guidelines/orchestration-policy.md`.

## Objective

Tighten the domain-contract input guards without changing any accepted behavior.

## Scope

1. `TranslationSegmentData` rejects non-finite `startSeconds`/`endSeconds`
   (`NAN`, `INF`, `-INF`).
2. `TranslationInvocation` rejects duplicate source `segment_index` values.
   An empty segment list remains valid (a completed no-speech transcript may
   legitimately have no segments).
3. Unit tests for both guards.

## Non-Scope

- enum vocabularies, lifecycle, failure taxonomy (unchanged);
- persistence/writer (P5-002A);
- empty-list policy change.

## Dependencies

- P5-001 VERIFIED.

## Acceptance Criteria

1. Non-finite timestamps throw `InvalidArgumentException`.
2. Duplicate invocation indices throw `InvalidArgumentException`.
3. Empty invocation segment list accepted.
4. Existing P5-001 tests unaffected; Pint clean; PHPStan 0.

## Review

Review File: `reviews/P5-001A-independent-review.md`

Review Status: VERIFIED (2026-09-21). No BLOCKER/HIGH/MEDIUM/LOW. INFO-1:
pre-review evidence figures are combined-state, not task-specific.

## Completion

READY → IMPLEMENTING → IMPLEMENTED_PENDING_REVIEW → REVIEWING → VERIFIED → DONE.