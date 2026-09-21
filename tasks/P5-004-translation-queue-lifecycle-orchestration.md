# P5-004 — Translation Queue / Lifecycle Orchestration

## Status

IMPLEMENTED_PENDING_REVIEW — queue orchestration, CAS claim, orchestrator,
genuine two-process concurrency evidence, tests, and internal pre-review
complete (2026-09-21). Not VERIFIED; not DONE. Independent review pending.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 5 — Translation (ADR-022)

## Objective

Orchestrate translation asynchronously over the existing Redis queue using
Phase 3 principles: small payload, CAS claim, at-most-once inference,
idempotent result write, terminal protection, and source immutability.

## Scope

1. A queued job carrying only small server-controlled identifiers.
2. CAS/guarded transition `pending/queued → translating`; skip on duplicate
   delivery.
3. Lifecycle transitions enforced via `TranslationLifecycle` (P5-001).
4. Invocation of the provider boundary (P5-003) and atomic persistence via the
   writer (P5-002).
5. Completed translations protected; no mutation of the source transcript.
6. Tests for orchestration, duplicate delivery no-op, terminal skip, and
   source immutability.

## Non-Scope

- retry/recovery hardening (P5-005);
- UI/export (P5-006/007);
- provider runtime internals (P5-003);
- automatic retry scheduling (excluded, mirrors ADR-018).

## Dependencies

- P5-002 DONE; P5-003 DONE.

## Acceptance Criteria

1. Queue payload contains no media path/binary and only small identifiers.
2. Duplicate delivery does not produce duplicate inference or rows.
3. Terminal translations are skipped.
4. Source transcript/segments are never modified.
5. Failures are recorded through the taxonomy (P5-001) and leave the
   translation retryable per lifecycle.
6. Tests, Pint, PHPStan pass.

## Verification Requirements

Feature tests with the existing queue test patterns; a genuine
independent-process concurrency test for the claim where the contract requires
it.

## Expected Reviewer

Claude Code.

## Browser Evidence Required

No.

## Owner Decision Dependencies

D5-01, D5-03, D5-05 (frozen).