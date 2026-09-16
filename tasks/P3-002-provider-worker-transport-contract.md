# P3-002 — Provider + Worker Transport Contract

## Status

BACKLOG

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: UNASSIGNED

## Authorized Phase

Phase 3 — Real Transcription Engine (ADR-017)

## Batch

Batch 1

## Objective

Establish the application boundary between Laravel and the transcription execution provider.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `Phase 3 — Real Transcription Engine Technical Specification` (approved)
- P3-001 (Transcription Domain Contract)

## Scope

Define:

- `TranscriptionProvider` interface (provider-neutral)
- Provider-neutral exception taxonomy
- Internal worker request DTO (request_id, transcription_id, attempt_id, media_reference, requested_language, contract_version)
- Worker response DTO (contract_version, text, language, duration_seconds, speech_detected, segments)
- Media-reference semantics (opaque storage-backed, not binary)
- Contract versioning
- HTTP adapter boundary (internal, authenticated)
- Worker authentication configuration (bearer token from env)
- Invalid response rejection

## Out of Scope

- No real worker execution
- No Python implementation
- No Redis execution
- No real HTTP calls

## Dependencies

- P3-001 (Transcription Domain Contract)

## Acceptance Criteria

1. Provider-neutral interface exists.
2. Provider is container-resolvable.
3. Consumers can fake/mock it.
4. Media passed by storage-backed reference, not binary string.
5. HTTP implementation hidden from consumers.
6. Worker contract versioned.
7. Bearer authentication configuration defined.
8. Invalid response is rejected.
9. Provider-neutral error taxonomy foundation exists.
10. Mixed-language normalized result supported.
11. No real worker execution yet.
12. All tests pass; Pint clean; PHPStan 0 errors.

## Review

Review File: None yet.
Review Status: PENDING

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
