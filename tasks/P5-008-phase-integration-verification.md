# P5-008 — Phase 5 Integration Verification

## Status

BLOCKED — promoted `BACKLOG → READY` and execution authorized by the Human
Product Owner on 2026-09-22 (`DECISION-P5-008-AUTHORIZATION-001`). Blocked at the
real-model prerequisite: the committed worker venv does not contain the pinned
self-hosted translation runtime (`transformers`, `torch`, `sentencepiece`), and no
translation model is cached locally, so the required real self-hosted
provider/model path (ADR-022 D5-09) cannot be exercised. The gate was not
over-claimed with mocks. See `PHASE5-P5-008-INTEGRATION-EVIDENCE.md` and
`BLOCKERS.md` B-004. Not VERIFIED; not DONE. Phase 5 is not closed.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 5 — Translation (ADR-022)

## Objective

Execute the final Phase 5 integration gate over the frozen P5 implementation:
real self-hosted translation provider/model path (D5-09), segment alignment,
persistence, failure/retry, real browser UX (ADR-021), translated exports,
ownership isolation, and Phase 3/4 regression.

## Scope

1. Real end-to-end gate: completed transcript → translation queued via Redis →
   real self-hosted provider/model → persisted segment-aligned translation.
2. Multilingual/code-switch fixture coverage (`ms`, `en`, `zh`, `ta`, `und`,
   mixed).
3. Failure + manual retry gate.
4. Source transcript immutability verification.
5. Ownership isolation verification.
6. Real browser verification of the translation workspace.
7. Translated TXT/SRT/VTT/DOCX export verification.
8. Full regression of Phase 3/4 contracts.
9. Retained evidence artifact.

## Non-Scope

- Phase 6 editing/comparison;
- production hardening/monitoring/scale (Phase 7);
- provider abstraction redesign.

## Dependencies

- P5-003 DONE; P5-004 DONE; P5-005 DONE; P5-006 DONE; P5-007 DONE.

## Acceptance Criteria

1. Real provider/model path exercised; mocks do not substitute.
2. Segment alignment (index, ms timestamps, source-language marker) preserved.
3. Failure + manual retry verified.
4. Source transcript unchanged.
5. Ownership denied cross-user.
6. Browser evidence retained.
7. All four translated exports verified.
8. No Phase 3/4 regression; no unresolved BLOCKER/HIGH/MEDIUM.
9. Pint, PHPStan pass.

## Verification Requirements

Real worker/model + live queue + real browser; retained evidence artifact under
the Phase 3 P3-008 / Phase 4 P4-006 pattern. Independent review of the gate.

## Expected Reviewer

Claude Code (final integration review).

## Browser Evidence Required

Yes (ADR-021).

## Owner Decision Dependencies

D5-04, D5-09 (frozen); ADR-021.