# P3-006 — Redis Queue Orchestration + Idempotent Delivery

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

Move the verified provider/persistence path into asynchronous Redis execution.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (canonical specification)
- P3-004 (Transcript Persistence)
- P3-005 (Segment Persistence + Atomic Completion)

## Scope

Implement:

- Small queue payload (transcription_id only)
- Job state reload from database (not serialized media/provider)
- Lifecycle transitions: queued → preparing → transcribing
- Atomic processing identity (ProcessingJob per attempt)
- Duplicate-delivery protection
- Completed-state protection (no reprocessing)
- Provider invocation via TranscriptionProvider
- Atomic result writer invocation (P3-004 + P3-005)
- Safe job acknowledgement behavior
- Redis outage surfaced safely
- No Horizon dependency
- Ownership intact
- Queue/job observability (structured logs)

## Out of Scope

- Horizon (not required initially)
- Failure/retry hardening (P3-007)
- Worker implementation (P3-003)

## Dependencies

- P3-004, P3-005 (persistence semantics established)
- P3-003 (real provider available)

## Acceptance Criteria

1. HTTP request returns before inference completes.
2. Job payload contains no media binary.
3. Job reloads authoritative database state.
4. Missing media handled.
5. Missing private storage object handled.
6. Cancelled state prevents new processing.
7. Completed transcription not reprocessed.
8. Duplicate delivery does not duplicate inference where preventable.
9. Duplicate delivery cannot duplicate transcript/segments.
10. Queue acknowledgement failure does not corrupt final state.
11. Redis outage is surfaced safely.
12. No Horizon dependency.
13. Ownership remains intact.
14. Queue/job observability present.
15. All tests pass; Pint clean; PHPStan 0 errors.

## Review

Review File: None yet.
Review Status: PENDING

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
