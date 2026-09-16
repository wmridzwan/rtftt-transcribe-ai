# P3-003 — Internal Python / FFmpeg / faster-whisper Provider

## Status

BACKLOG

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: UNASSIGNED

## Authorized Phase

Phase 3 — Real Transcription Engine (ADR-017)

## Batch

Batch 1

## Prerequisites

- P3-001 (Transcription Domain Contract) — complete
- P3-002 (Provider + Worker Transport Contract) — complete
- Turbo vs Large-v3 Benchmark Gate — evidence recorded

## Objective

Implement the first real transcription provider: internal Python HTTP service with FFmpeg preparation and self-hosted faster-whisper inference.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `Phase 3 — Real Transcription Engine Technical Specification` (approved)
- P3-001, P3-002 domain and transport contracts
- Benchmark gate evidence (turbo vs large-v3)

## Scope

Implement:

- Python internal HTTP service (FastAPI or equivalent)
- Bearer token authentication
- Shared-filesystem media resolution (reject absolute paths, reject traversal)
- FFmpeg preparation (16 kHz, mono, PCM 16-bit)
- Selected faster-whisper model (per benchmark gate outcome)
- Multilingual inference with code-switching support
- Segment-language normalization (one dominant language per segment)
- No-speech handling (speech_detected=false, segments=[])
- Worker response contract (versioned JSON)
- Laravel HTTP provider adapter (implements TranscriptionProvider)
- Temporary media cleanup (ephemeral default)
- Cross-runtime observability (structured logs)

## Out of Scope

- Queue infrastructure (P3-006)
- Transcript persistence (P3-004)
- Segment persistence (P3-005)
- Failure/retry hardening (P3-007)

## Dependencies

- P3-001, P3-002 — domain and transport contracts
- Benchmark gate — model selection

## Acceptance Criteria

1. Real internal worker callable.
2. Selected model matches benchmark outcome.
3. Worker is authenticated (bearer token).
4. Public access is not the default deployment.
5. Media reference cannot escape configured storage root.
6. Original media remains unchanged.
7. FFmpeg preparation succeeds for representative supported media.
8. Prepared audio is private.
9. Default retention is ephemeral.
10. Retention can be configured.
11. Real faster-whisper inference succeeds.
12. Dominant transcript language returned.
13. Segment languages returned where defensible.
14. `und` returned where language cannot be supported.
15. Segment languages are not fabricated from transcript-level language.
16. No-speech result works.
17. Worker timeout works.
18. Worker errors become provider-neutral Laravel failures.
19. Malformed worker response rejected.
20. Cleanup occurs on success/failure/exception.
21. Observability events emitted safely.
22. All tests pass; Pint clean; PHPStan 0 errors; Python tests pass.

## Review

Review File: None yet.
Review Status: PENDING

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
