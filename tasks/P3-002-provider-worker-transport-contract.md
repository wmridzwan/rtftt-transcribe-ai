# P3-002 — Provider + Worker Transport Contract

## Status

REVIEW

## Ownership

Implementation Owner: OpenCode (per AGENTS.md agent model)
Reviewer: UNASSIGNED (pending batch review)

## Authorized Phase

Phase 3 — Real Transcription Engine (ADR-017)

## Batch

Batch 1

## Objective

Establish the application boundary between Laravel and the transcription execution provider.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (canonical specification)
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

## Implementation Notes

### Files Created

- `app/Transcription/WorkerRequest.php` — request DTO with server-generated UUID
- `app/Transcription/WorkerResponse.php` — response DTO
- `app/Transcription/WorkerSegmentData.php` — segment DTO
- `app/Transcription/WorkerErrorResponse.php` — error envelope
- `app/Transcription/WorkerContract.php` — version constants
- `app/Transcription/WorkerResponseValidator.php` — malformed response rejection
- `app/Transcription/HttpTranscriptionProvider.php` — HTTP provider implementation
- `app/Providers/TranscriptionServiceProvider.php` — container binding
- `config/transcription.php` — worker URL, token, timeout, retention, shared root

### Tests Created

- `tests/Unit/Transcription/WorkerTransportTest.php` — 8 tests

### Quality Results

- 39 unit tests passed, 125 assertions
- Pint clean
- PHPStan 0 errors

### Contracts Implemented

- Provider-neutral interface (TranscriptionProvider)
- Container-resolvable (TranscriptionServiceProvider)
- Consumer-mockable (interface-based)
- Media by reference (TranscriptionMedia)
- HTTP hidden behind provider boundary
- Worker contract versioned (WorkerContract::VERSION)
- Bearer authentication configured (config/transcription.php)
- Invalid response rejected (WorkerResponseValidator)
- Failure taxonomy foundation (TranscriptionFailure + WorkerErrorResponse)

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
