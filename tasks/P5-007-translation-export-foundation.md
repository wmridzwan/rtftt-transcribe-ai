# P5-007 — Translation Export Foundation

## Status

IMPLEMENTED_PENDING_REVIEW — controller, routes, tests, and internal
pre-review complete (2026-09-21). Not VERIFIED; not DONE. Independent review
pending.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 5 — Translation (ADR-022)

## Objective

Export persisted translated transcripts as TXT, SRT, VTT, and DOCX (D5-08),
inheriting timestamps from source segments, never recomputing them, never
invoking the provider, and never overwriting source exports.

## Scope

1. Controllers/routes for translated export in the four formats.
2. Ownership authorization derived from `TranscriptionPolicy`.
3. Completed-translation gating.
4. Distinct filename convention (source title + target-language suffix).
5. Millisecond-correct SRT/VTT formatting reused from `SegmentTimestamp`.
6. Tests for each format, naming, gating, ownership, and no-provider-invocation.

## Non-Scope

- source export changes (frozen P4-005);
- provider invocation during export;
- export of in-progress/failed translations.

## Dependencies

- P5-002 DONE.

## Acceptance Criteria

1. All four formats render translated segments with inherited timestamps.
2. Filenames do not collide with source exports.
3. Cross-user access denied; non-completed translation export blocked.
4. No provider call occurs during export.
5. UTF-8/multilingual round-trip verified.
6. Tests, Pint, PHPStan pass.

## Verification Requirements

Feature tests per format; perspective/ownership tests.

## Expected Reviewer

Claude Code.

## Browser Evidence Required

Optional.

## Owner Decision Dependencies

D5-08 (frozen); D4-03 (source format set).