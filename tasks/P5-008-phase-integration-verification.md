# P5-008 — Phase 5 Integration Verification

## Status

DONE — closed by the Human Product Owner on 2026-09-23
(`DECISION-P5-008-CLOSURE-001`) on the basis of the accepted fresh independent
VERIFIED verdict (`reviews/P5-008-independent-review.md`). The corrective
(`DECISION-P5-008-CORRECTIVE-001`; ADR-024) adopted the proven runtime
(`transformers==5.17.0`, `torch==2.14.0`, `sentencepiece==0.2.2`), reconciled
all declarations and guard tests to one canonical runtime, re-provisioned a
clean canonical `facebook/nllb-200-distilled-600M` cache (pinned revision;
weights not committed), committed a reproducible real-gate harness
(`verification/p5-008-real-gate.mjs`,
`app/Console/Commands/Phase5IntegrationVerification.php`), executed a true
browser-to-real-model end-to-end flow (no worker double), and re-ran the
canonical gate for `ms`/`en`/`zh`/`ta` with a code-switched source, alignment,
source immutability, ownership isolation, and TXT/SRT/VTT/DOCX exports. Quality:
worker 46, translation 193, full suite 626/625, Pint clean, PHPStan 0. Evidence:
`PHASE5-P5-008-INTEGRATION-EVIDENCE.md`; runbook:
`verification/p5-008/README.md`. Non-blocking LOW/INFO debt retained;
historical review/corrective provenance preserved unchanged.
`DECISION-PHASE5-CLOSURE-001` closes Phase 5.

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