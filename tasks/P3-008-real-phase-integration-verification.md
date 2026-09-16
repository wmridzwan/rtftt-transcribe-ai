# P3-008 — Real Phase Integration Verification

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

Verify the real end-to-end Phase 3 pipeline with actual media, real faster-whisper, and real Redis queue execution. Mock-only verification is insufficient.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (canonical specification)
- P3-001 through P3-007 (all prior batches)

## Scope

Verify the real path:

```
real supported media
 ↓
Phase 2 private MediaFile
 ↓
transcription request
 ↓
Redis
 ↓
Laravel queue worker
 ↓
authenticated Python worker
 ↓
shared private filesystem
 ↓
FFmpeg
 ↓
real faster-whisper model
 ↓
normalized multilingual result
 ↓
transcript persistence
 ↓
segment persistence
 ↓
completed
 ↓
retrievable through existing authorized application surface
```

Mixed-language verification:

- Transcript dominant language exists
- Different segment languages can coexist
- BM, English, Chinese, Tamil supported
- `und` accepted when appropriate
- Whole transcript not forced to one segment language

Queue verification:

- Request returns before inference completes
- Redis job executes
- Duplicate delivery safe
- Transient failure retries
- Retry success works
- Retry exhaustion fails deterministically
- Completed job not reprocessed

Persistence verification:

- No duplicate logical transcript
- No duplicate segments
- No partial completed state
- Correct MediaFile relationship
- Correct detected transcript language
- Correct segment language values
- Valid timestamps
- Unicode preserved

Security verification:

- User A cannot transcribe User B media
- User A cannot read User B transcript/segments
- Worker authentication required
- Worker not exposed publicly by default
- Arbitrary shared-filesystem path rejected
- Original media unchanged
- Temporary prepared audio private
- Cleanup obeys configured retention
- Secrets absent from logs

Regression verification:

- Phase 1 authentication, dashboard, navigation, ownership
- Phase 2 upload, 500 MiB contract, private storage, upload retry/idempotency
- P2-004A2 CAS behavior
- No new unexplained skips

## Out of Scope

- Translation
- Full transcript UI redesign
- Minimum WER threshold
- Hosted ASR provider

## Dependencies

- P3-007 (Failure/Retry/Recovery Hardening)

## Acceptance Criteria

1. Real supported media processed end-to-end.
2. Real Redis queue execution verified.
3. Real authenticated Python worker invoked.
4. Real FFmpeg preparation verified.
5. Real faster-whisper inference verified.
6. Normalized multilingual result produced.
7. Transcript persistence verified.
8. Segment persistence verified.
9. Atomic completion verified.
10. Duplicate delivery safe.
11. Retry bounded and idempotent.
12. Terminal failure deterministic.
13. Cross-user isolation intact.
14. Phase 1/2 regression verification passes.
15. No translation introduced.
16. Mock-only tests insufficient; real path evidence required.
17. All tests pass; Pint clean; PHPStan 0 errors.

## Review

Review File: None yet.
Review Status: PENDING

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
