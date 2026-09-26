# TD-008 — Full-Suite Order-Dependent Flakiness Remediation

## Status

BACKLOG (contract authored in the pre-Linux remediation batch; NOT READY, NOT AUTHORIZED FOR IMPLEMENTATION — requires explicit HPO READY promotion and execution authorization)

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: UNASSIGNED (independent review required per the State-to-Action Contract)

## Authorized Phase

Phase 7 (pre-P7-012 prerequisite; `DECISION-TD-008-REPRIORITIZATION-001`)

## Objective

Eliminate order-dependent full-suite flakiness so the P7-012 terminal gate can rely on a deterministic full-suite signal. Close TD-008 (OPEN, MEDIUM) with evidence — do not silently close it and do not reclassify P7-012 confidence without the pass criteria below.

## Context

- `docs/TECHNICAL_DEBT_REGISTER.md` TD-008 entry (problem statement,
  evidence, CONDITIONAL pre-P7-012 blocker, MEDIUM severity)
- `DECISION-TD-008-REPRIORITIZATION-001` (reprioritization; pre-P7-012 prerequisite)
- `reviews/PHASE7-WAVE2-INDEPENDENT-REVIEW.md` §4 (7-of-8 full runs with 1–3 failures/errors in varying pre-existing files, never Wave 2 diffs)
- `reviews/PHASE7-WAVE3A-INDEPENDENT-REVIEW.md` §H/§L (`LogContextTest` attempt-ordinal unique-collision, full-suite-only, 6/6 green in isolation)
- Pre-Linux remediation batch, fresh occurrence 2026-09-27: full suite
  1098 total / 1096 passed / 1 error / 1 skipped — the same
  `LogContextTest` ordinal test failed with `UNIQUE constraint
  failed: processing_jobs.transcription_id` (sqlite `:memory:`),
  green in isolation and on the adjacent full run (1097 passed).
  Order-dependent, pre-existing, untouched by the remediation diff.
- `reviews/PHASE4-final-closure.md:91-92` (assertion-count variance 1400/1402 across legitimate runs)
- `DECISION-PHASE6-7-DEBT-CARRYFORWARD-001` item 1 (origin: order-dependent `MediaManagementTest` file-size/Flysystem-on-Windows behavior; cross-test state leakage)

## Scope

Implement only:

- deterministic reproduction of each known flake family (file-size/Flysystem ordering, `LogContextTest` ordinal collision, assertion-count variance) with seed/order logging
- attribution per failure: product defect vs test-isolation defect vs environment (Windows/SQLite) artifact — recorded per case, never blanket-classified
- isolation fixes (test hygiene only: unique fixtures, sequence-safe factories, order-independent cleanup, no shared mutable state)
- a documented full-suite invocation (order, seed handling, repeat count) that P7-012 will execute

## Out of Scope

Do not implement:

- product-code behavior changes (a genuine product defect found here becomes a separate HPO-scoped finding, not silent scope expansion)
- P7-009 Phase B, P7-007 drill, P7-012 itself
- TD-014 or any other debt item
- CI/provider migration or new test infrastructure beyond what reproduction requires

Future ideas are not authorization.

## Dependencies

Requires:

- None to start (test-only scope; may run in parallel with Linux provisioning)
- Blocks: P7-012 (hard prerequisite — P7-012 must not run until this task is VERIFIED and closed DONE)

## Acceptance Criteria

The task is complete when:

- [ ] each known flake family reproduces deterministically (seed/order recorded) or is proven fixed with before/after evidence
- [ ] every fixed case carries attribution (test-isolation vs environment vs product) with file/line evidence
- [ ] full suite passes N consecutive runs (N decided by HPO at READY promotion; minimum 5) with zero failures and stable assertion counts
- [ ] no product-code change outside test/support files (or HPO-authorized findings split out separately)
- [ ] Pint clean; PHPStan at the repository-required level
- [ ] independent review returns VERIFIED with no BLOCKER/HIGH/MEDIUM
- [ ] TD-008 register entry updated to CLOSED with evidence links (register edit owned by this task, not a separate act)

## Implementation Notes

To be completed by the implementation owner.

### Files Changed

- None yet.

### Important Decisions

- None yet.

### Known Limitations

- Windows/SQLite-dev evidence only proves determinism here; the Linux-target full-suite run (post-provisioning) re-confirms under P7-012 — this task owns dev-side determinism, not the target run.

## Verification

Implementation owner must record the commands executed and results.

Example commands:

php artisan test --compact
vendor/bin/pint --dirty --format agent
composer types:check

Result:

PENDING

## Review

Review File:

None yet.

Review Status:

PENDING

## Completion

A task cannot move directly from IN_PROGRESS to DONE.

Required flow:

BACKLOG -> READY -> IN_PROGRESS -> REVIEW -> VERIFIED -> DONE

The implementation owner must not mark their own work VERIFIED. READY promotion and DONE closure are HPO acts.
