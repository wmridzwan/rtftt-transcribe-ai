# P5-003 — Translation Provider Boundary

## Status

DONE — closed by the Human Product Owner on 2026-09-22
(`DECISION-PHASE5-TASK-CLOSURES-001`) on the basis of the recorded independent
VERIFIED verdict (`reviews/P5-corrective-batch-cycle2-independent-review.md`,
cycle 2). LOW L-1..L-4 and INFO retained. Historical review artifacts preserved
unchanged.

## Cycle 2 Notes

- Strict invocation-aligned response validation (count/index/timestamps/
  source-language echo); authoritative alignment copied from the invocation.
- Strict types; no raw PHP warnings.
- Transport + HTTP-status failure mapping.
- `zsm_Latn` correction; per-segment tokenizer `src_lang`; worker runtime
  dependencies declared in `worker/requirements.txt`.
- Pre-review: `reviews/pre-review/P5-003-cycle2-pre-review.md`.

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

## Review

Review File: `reviews/P5-003-independent-review.md`

Review Status: CHANGES_REQUESTED (2026-09-21, cycle 1 of 3).

Required: HIGH-1 — enforce segment count/index set/timestamps against the
invocation at the provider boundary (taxonomy failures) with tests for partial,
empty, and foreign-index responses. Strongly recommended in the same cycle:
MEDIUM-1 (strict type validation; array `text` leaks a raw `ErrorException`) and
MEDIUM-2 (`ms` maps to invalid NLLB code `msa_Latn`; should be `zsm_Latn`, add a
mapping test). LOW-1..LOW-4 and INFO recorded in the review.

## Expected Reviewer

Claude Code.

## Browser Evidence Required

No.

## Owner Decision Dependencies

D5-01, D5-04 (frozen).