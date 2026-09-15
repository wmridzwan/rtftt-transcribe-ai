# P2-002C — Validate Generated Checksum Format

## Status

DONE

## Ownership

Implementation Owner: Codex
Reviewer: Claude Code

## Authorized Phase

Phase 2 checkpoint follow-up; prerequisite for any future upload workflow. This follow-up does not authorize P2-003.

## Objective

Ensure checksum values are generated and validated as server-owned lowercase SHA-256 hex values when the future ingestion workflow is implemented.

## Context

Originating independent review: `reviews/P2-001-P2-002-independent-review.md` (LOW: checksum format validation is intentionally deferred).

## Scope

- Define the server-owned checksum format and validation boundary.
- Preserve nullable, indexed, non-unique semantics and allowed duplicate uploads.

## Out of Scope

- Client-supplied checksum trust.
- Deduplication enforcement.
- P2-003 implementation.

## Dependencies

- Must be completed before accepting checksum values in a real upload workflow.

## Acceptance Criteria

- [x] Server-generated checksum format is explicit.
- [x] Arbitrary client-supplied checksum values are not trusted.
- [x] Duplicate uploads remain allowed.
- [x] Independent review passes.

## Review Reference

`reviews/P2-001-P2-002-independent-review.md`

## Implementation Notes

- Added the centralized checksum contract in `config/media.php`: SHA-256, lowercase hexadecimal encoding, exactly 64 characters.
- Removed `checksum_sha256` from the mass-assignment boundary in `MediaFile`; server code must use `assignGeneratedChecksumSha256()` and the model rejects uppercase, non-hexadecimal, short, and long values.
- Documented server-side generation, client-value rejection, nullable/non-unique storage, and duplicate-upload semantics in ADR-009 and `architecture.md`.
- Updated the media contract tests to cover the format, guarded client assignment, valid server assignment, duplicate checksums, and nullable prototype rows.
- No upload workflow, client checksum acceptance, deduplication, or P2-003 work was started.

## Verification

- Checksum-focused contract tests: 7 passed / 13 assertions.
- Complete `tests/Feature/MediaUploadContractTest.php`: 10 passed / 1 unrelated pre-existing failure. The failure is `media storage boundary rejects a public disk before use`, which resolves `AppServiceProvider` through the container and receives an unresolvable `$app` constructor dependency before reaching the P2-002A assertion.
- Laravel Pint: passed after formatting the dirty PHP files.
- PHPStan: passed with 0 errors.
- Independent review: VERIFIED in `reviews/P2-001A-P2-002A-P2-002B-P2-002C-independent-review.md`.

Work reconciled the stale current-state status references and closed the task as DONE under ADR-010. P2-003 remains closed.
