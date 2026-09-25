# Blockers

Blocker queue for the Phase 5–7 Controlled Parallel Execution Authorization
(§19). A blocked task does not stop the run; the scheduler switches to another
eligible track. The run stops only when no eligible authorized work remains or a
`GLOBAL_BLOCKER` exists.

Classifications: `LOCAL_BLOCKER`, `TRACK_BLOCKER`, `CONTRACT_BLOCKER`,
`SECURITY_BLOCKER`, `DATA_INTEGRITY_BLOCKER`, `GOVERNANCE_BLOCKER`,
`GLOBAL_BLOCKER`.

## Open Blockers

None. B-005 is resolved; the fresh independent P5-008 review completed and
returned VERIFIED (`reviews/P5-008-independent-review.md`), and Phase 5 is
CLOSED (`DECISION-PHASE5-CLOSURE-001`).

Governance note: P6-005 was `IMPLEMENTED_PENDING_REVIEW` awaiting fresh
independent review; that review is complete (`reviews/P6-005-INDEPENDENT-REVIEW.md`,
VERIFIED, no BLOCKER/MAJOR) and the HPO has closed P6-005 DONE
(`DECISION-P6-005-CLOSURE-001`, 2026-09-25). P6-008 was independently reviewed
VERIFIED (`reviews/P6-008-INDEPENDENT-REVIEW.md`; all AC1–AC10 PASS; no
BLOCKER/MAJOR/MINOR) and the HPO has closed P6-008 DONE
(`DECISION-P6-008-CLOSURE-001`, 2026-09-25). P6-009 executed 2026-09-25 with
verdict FAIL (F-001 MAJOR: AC6 — exports render the machine source, not the
active revision; AC1–AC5 and AC7–AC11 PASS; evidence
`verification/p6-009/P6-009-FINAL-GATE-EVIDENCE.md`). F-001 blocks the Phase 6
closure recommendation until resolved through a separately authorized path
with a gate re-run; general Phase 7 work is not started/authorized. P6-010 was
implemented, independently reviewed VERIFIED (all AC1–AC11 PASS, no findings),
and closed DONE (`DECISION-P6-010-CLOSURE-001`, 2026-09-25); the bounded F-001
remediation is RESOLVED while the historical P6-009 FAIL is preserved, and a
full P6-009 rerun was executed 2026-09-25 as `P6-009-RERUN-01` under direct
HPO rerun authorization (late-persisted
`DECISION-P6-009-RERUN-01-AUTHORIZATION-001`): verdict PASS (AC1–AC11 fresh;
`verification/p6-009-rerun-01/P6-009-FINAL-GATE-RERUN-EVIDENCE.md`),
independently reviewed VERIFIED
(`reviews/P6-009-RERUN-01-INDEPENDENT-REVIEW.md`) with one MAJOR procedural
finding (authorization-record gap) reconciled in
`reviews/P6-009-RERUN-01-GOVERNANCE-RECONCILIATION.md` and HPO-accepted for
closure (`DECISION-P6-009-CLOSURE-001`, 2026-09-25). P6-009 is DONE; Phase 6
terminal gate completed; Phase 6 is CLOSED (`DECISION-PHASE6-CLOSURE-001`,
2026-09-25; P6-001..P6-010 DONE; closure review
`reviews/PHASE6-CLOSURE-REVIEW.md`). D6-08/D6-09 remain DEFERRED. These remain authorization boundaries
rather than blockers. There is no P3-010 task file, so no P3-010 blocker exists.

## Resolved Blockers

| # | Classification | Resolution | Date |
|---|---|---|---|
| GOV-1 | `GOVERNANCE_BLOCKER` | Ratified under ADR-023 / `PHASE5-7-CONTROLLED-PARALLEL-EXECUTION.md` §4: eligible downstream work may start once a committed predecessor reaches `IMPLEMENTED_PENDING_REVIEW`. P5-002-after-P5-001 ordering ratified. | 2026-09-21 |
| GOV-2 | `GOVERNANCE_BLOCKER` | `AGENTS.md` / `CURRENT_STATE.md` updated to record Phase 5 = AUTHORIZED FOR IMPLEMENTATION and the P6/P7 allowlists. | 2026-09-21 |
| GOV-3 | `GOVERNANCE_BLOCKER` | Same root cause as B-001 (uncommitted Phase 3/4 baseline); closed with B-001. | 2026-09-22 |
| B-001 | `GOVERNANCE_BLOCKER` | Phase 3/4 baseline committed in the pre-review-pause checkpoint (`2dc4c66`, `76f884d`, `943dba1`) plus the named P5 dependencies (`2ea55d3`, `da482e3`, `6961087`). The working tree is now clean at HEAD. | 2026-09-22 |
| B-002 | `GOVERNANCE_BLOCKER` | Worker `/translate` wiring committed (`63a1a76`); worker model runtime dependencies declared in `worker/requirements.txt` (P5-003 cycle 2). | 2026-09-22 |
| B-003 | `TRACK_BLOCKER` | Claude API recovered on 2026-09-22 (`claude -p` returns normally). Fresh review/re-review is authorized by `DECISION-P5-PENDING-REVIEW-AUTHORIZATION-001`; execution is the next batch. | 2026-09-22 |
| B-004 | `GLOBAL_BLOCKER` | Resolved by Option A provisioning (2026-09-22): installed torch 2.14.0 / transformers 5.17.0 / sentencepiece 0.2.2 and started local Redis 5.0.14.1. Independent review then returned CHANGES_REQUESTED (undeclared runtime; corrupt cache); resolved by the P5-008 corrective (`DECISION-P5-008-CORRECTIVE-001`, ADR-024): canonical runtime reconciled, clean canonical NLLB cache re-provisioned, committed harness + real browser-to-real-model proof added, gate re-run. P5-008 = IMPLEMENTED_PENDING_REVIEW. | 2026-09-22 |
| B-006 | `DATA_INTEGRITY_BLOCKER` | Resolved by relocation (2026-09-22): the `D:` volume was found to silently corrupt large files (same length, different bytes; NLLB cache `config.json`/`tokenizer.json` invalid) and the `D:`-hosted Redis had disabled writes after RDB save failures. The canonical model cache was re-provisioned on `C:` (`C:\rtftt-hf-cache`) and Redis re-pointed to `C:\rtftt-redis` with persistence disabled for the gate. Reproducible provisioning documented in `verification/p5-008/README.md`. Retained as documented operational evidence (see `DECISION-PHASE5-DEBT-CARRYFORWARD-001`). | 2026-09-22 |
| B-005 | `TRACK_BLOCKER` | Resolved 2026-09-23: the reviewer session quota recovered; the fresh independent P5-008 review completed and returned VERIFIED (`reviews/P5-008-independent-review.md`). P5-008 closed DONE (`DECISION-P5-008-CLOSURE-001`); Phase 5 CLOSED (`DECISION-PHASE5-CLOSURE-001`). | 2026-09-23 |

## Rules

1. Record every blocker with classification and affected task.
2. A blocked task never halts unrelated eligible work.
3. Contract/security/data-integrity/governance blockers require an HPO decision;
   do not invent the resolution.
4. Re-check the blocker queue before declaring the run idle.