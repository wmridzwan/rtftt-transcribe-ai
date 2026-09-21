# P5-001 — Translation Domain Contract

## Status

VERIFIED — independent review `reviews/P5-001-independent-review.md` (2026-09-21)
returned VERIFIED with no BLOCKER/HIGH/MEDIUM. LOW-1/LOW-2 and INFO-1..3
recorded as non-blocking; LOW-1/LOW-2 carried into follow-up P5-001A. Not DONE;
HPO closure required.

## Review

Review File: `reviews/P5-001-independent-review.md`

Review Status: VERIFIED (2026-09-21). No BLOCKER/HIGH/MEDIUM. Non-blocking:
LOW-1 (`TranslationSegmentData` accepts NAN/INF timestamps), LOW-2
(`TranslationInvocation` accepts duplicate source indices), INFO-1..3.

## Follow-Up

- P5-001A — DTO input guards (LOW-1, LOW-2), referencing the review above.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code (independent review)

## Authorization

Authored under `DECISION-PHASE5-AUTHORIZATION-001` and ADR-022. D5-01..D5-09 are
frozen. This task implements only the shared Phase 5 domain contract and
primitives; it does not implement schema, routes, UI, queue orchestration, or
provider runtime.

## Authorized Phase

Phase 5 — Translation (ADR-019, ADR-022)

## Objective

Establish the canonical Phase 5 translation domain contract and implement only
the shared primitives consumed by P5-002..P5-008: status vocabulary, target
language vocabulary, lifecycle transitions, failure taxonomy, segment-aligned
result DTOs, alignment/passthrough policy, and the provider-neutral interface.

## Scope

1. `App\Translation\TranslationStatus` enum (`pending`, `queued`,
   `translating`, `completed`, `failed`) with `isTerminal()`.
2. `App\Translation\TranslationTarget` enum (`ms`, `en`, `zh`, `ta`) with
   `fromBcp47()` returning `null` for unsupported/`und` targets.
3. `App\Translation\TranslationLifecycle` with valid transitions:
   `pending→queued`, `queued→translating|failed`,
   `translating→completed|failed`, `failed→queued` (manual retry only),
   `completed` terminal.
4. `App\Translation\TranslationFailure` string-backed taxonomy with
   `isRetryable()`.
5. `App\Translation\TranslationSegmentData` readonly DTO enforcing alignment
   invariants (index ≥ 0, start ≥ 0, end ≥ start), carrying source language.
6. `App\Translation\TranslationResult` readonly DTO (target, full text,
   segments, provider, model) validating segment alignment invariants.
7. `App\Translation\TranslationAlignment` static policy:
   `isPassthrough(LanguageIdentifier $source, TranslationTarget $target): bool`
   (true only when source equals target; `und` is never passthrough), and
   `passthrough(TranslationSegmentData $source, TranslationTarget $target)`.
8. `App\Translation\TranslationInvocation` readonly DTO with server-generated
   `requestId`, `transcriptionId`, optional `translationId`, `targetLanguage`,
   and source segments.
9. `App\Translation\TranslationProvider` interface:
   `translate(TranslationInvocation $invocation): TranslationResult`.
10. Unit tests for items 1–8.

## Non-Scope

- database schema/migrations/models (P5-002);
- queue jobs, claims, idempotent writer (P5-004);
- concrete provider implementation (P5-003);
- failure/retry/stale recovery wiring (P5-005);
- routes, controllers, views, JavaScript, workspace UI (P5-006);
- export (P5-007);
- integration verification (P5-008);
- mutation of `transcriptions`/`transcription_segments`;
- any new language outside `ms`, `en`, `zh`, `ta`, `und`.

## Dependencies

- D5-01..D5-09 frozen (satisfied, ADR-022).
- No P5 predecessor.

## Acceptance Criteria

1. Status enum has exactly the five values and correct terminal semantics.
2. Target enum has exactly `ms`, `en`, `zh`, `ta`; `fromBcp47('und')`,
   `fromBcp47('fr')`, and `fromBcp47('')` return `null`; `fromBcp47('en-US')`
   returns English.
3. Lifecycle permits only the transitions above; `completed` has no outgoing
   transition; `failed→queued` is the only retry edge.
4. Failure taxonomy classified retryable/non-retryable; `isRetryable()` is
   deterministic.
5. Segment/result DTOs reject invalid alignment values; valid values are
   preserved without re-rounding.
6. Alignment policy treats a target-language source segment as passthrough,
   never treats `und` as passthrough, and preserves index/timestamps/source
   language.
7. Provider interface is provider-neutral (no media/runtime assumptions).
8. Relevant tests pass; Pint clean; PHPStan 0 errors.
9. No schema, route, view, JavaScript, worker, or unrelated change.

## Verification Requirements

- Unit tests for enum vocabularies, lifecycle transitions (including illegal
  transitions throwing), failure retryability, DTO invariants, and alignment
  policy.
- Full PHP suite regression.
- Pint + PHPStan.

## Expected Reviewer

Claude Code (independent).

## Browser Evidence Required

No.

## Owner Decision Dependencies

None (D5-01..D5-09 frozen).

## Completion

Required flow: READY → IMPLEMENTING → IMPLEMENTED_PENDING_REVIEW → REVIEWING →
VERIFIED → DONE. Implementation owner must not self-verify.