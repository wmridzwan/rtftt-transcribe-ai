# P3-007 — Failure / Retry / Recovery Hardening

## Status

BACKLOG

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: UNASSIGNED

## Authorized Phase

Phase 3 — Real Transcription Engine (ADR-017)

## Batch

Batch 3

## Objective

Finalize failure classification and deterministic recovery behavior across queue, Laravel, HTTP worker, FFmpeg, faster-whisper, and persistence.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (canonical specification)
- P3-001 through P3-006 (all prior batches)

## Scope

Failure taxonomy covering at minimum:

- MEDIA_MISSING
- MEDIA_REJECTED
- WORKER_UNAVAILABLE
- WORKER_AUTH_FAILED
- WORKER_TIMEOUT
- WORKER_SATURATED
- FFMPEG_FAILED
- RESOURCE_EXHAUSTED
- INVALID_WORKER_RESPONSE
- PROCESSING_FAILED
- PERSISTENCE_FAILED
- CONFIGURATION_ERROR

Each category: retryable?, user-safe message class, internal diagnostic handling, terminal/recoverable semantics.

Retry requirements:

- Bounded attempts
- Explicit backoff
- Same logical transcription
- New attempt identity where appropriate
- No duplicate logical transcript
- No duplicate segments
- Safe exhaustion state

Recovery scenarios:

- Redis duplicate delivery
- Laravel worker crash before provider
- Laravel worker crash after provider result
- Worker HTTP timeout
- Python worker crash
- FFmpeg failure
- Resource exhaustion
- Malformed worker result
- Persistence transaction failure
- Persistence succeeds but queue acknowledgement fails
- Prepared audio left behind
- Retry exhaustion

## Out of Scope

- Translation
- Hosted ASR fallback
- Multi-provider routing

## Dependencies

- P3-001 through P3-006 (all prior batches)

## Acceptance Criteria

1. Retryable vs terminal deterministic.
2. Retry counts bounded.
3. Backoff explicit.
4. No infinite/resource busy-loop.
5. Worker outage retries safely.
6. Configuration/security failures do not loop.
7. Persistence recovery safe.
8. Logical identity preserved.
9. Duplicate outputs prevented.
10. Exhaustion produces deterministic failed state.
11. Safe user-facing errors.
12. Detailed internal diagnostics preserved.
13. Temporary artifact recovery/cleanup verified.
14. All tests pass; Pint clean; PHPStan 0 errors.

## Review

Review File: None yet.
Review Status: PENDING

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
