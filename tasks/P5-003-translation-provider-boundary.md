# P5-003 — Translation Provider Boundary

## Status

IMPLEMENTED_PENDING_REVIEW — provider boundary, validator, worker module,
tests, and internal pre-review complete (2026-09-21). Worker `/translate` route
wiring is implemented in the working tree but uncommitted under B-001. Not
VERIFIED; not DONE. Independent review pending.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 5 — Translation (ADR-022)

## Objective

Implement the provider-neutral application-side boundary (D5-04) and a
self-hosted default translation provider, returning a `TranslationResult`
(P5-001) for a `TranslationInvocation`. Text-only: no media path or binary
crosses the boundary.

## Scope

1. A provider registration/binding surface (config-driven) resolving a
   `TranslationProvider` implementation; self-hosted default.
2. A `SelfHostedTranslationProvider` that invokes the internal authenticated
   worker translation endpoint with text-only payloads and maps responses to
   `TranslationResult`.
3. A normalized worker response contract (validator) for translation results:
   target, full text, segment-aligned segments, provider/model identity,
   error envelope.
4. Extended worker transport (new endpoint) is specified; the Python
   implementation is scoped here or in the provider task as defined by the
   contract.
5. Tests for request mapping, response mapping, malformed-output rejection, and
   provider-neutral binding.

## Non-Scope

- queue jobs, claims, retry scheduling (P5-004/P5-005);
- persistence writes (P5-002);
- hosted third-party provider adapters (future ADR);
- provider-abstraction redesign beyond the single boundary.

## Dependencies

- P5-001 DONE.

## Acceptance Criteria

1. `TranslationProvider` is resolved without hardcoding a vendor.
2. Self-hosted provider sends text only; no media path/binary in the payload.
3. Valid worker responses map to `TranslationResult` preserving segment
   alignment.
4. Malformed/partial responses are rejected with a taxonomy failure, not
   silently accepted.
5. Bearer auth shared secret is server/config controlled; no secrets committed.
6. Tests, Pint, PHPStan pass.

## Verification Requirements

Feature/contract tests with a fake HTTP transport; response-validator unit
tests; no mock may claim the final real-provider gate.

## Expected Reviewer

Claude Code.

## Browser Evidence Required

No.

## Owner Decision Dependencies

D5-01, D5-04 (frozen).