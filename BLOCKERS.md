# Blockers

Blocker queue for the Phase 5–7 Controlled Parallel Execution Authorization
(§19). A blocked task does not stop the run; the scheduler switches to another
eligible track. The run stops only when no eligible authorized work remains or a
`GLOBAL_BLOCKER` exists.

Classifications: `LOCAL_BLOCKER`, `TRACK_BLOCKER`, `CONTRACT_BLOCKER`,
`SECURITY_BLOCKER`, `DATA_INTEGRITY_BLOCKER`, `GOVERNANCE_BLOCKER`,
`GLOBAL_BLOCKER`.

## Open Blockers

### B-004 — P5-008 real self-hosted translation runtime unavailable
- Classification: `GLOBAL_BLOCKER` (blocks the Phase 5 integration gate; only
  remaining Phase 5 work)
- Task/Source: P5-008 (promoted READY, `DECISION-P5-008-AUTHORIZATION-001`)
- Detail: the committed worker venv lacks `transformers`, `torch`,
  `sentencepiece`; no translation model is cached (only faster-whisper ASR
  models); and no live Redis server is reachable on `127.0.0.1:6379`. The
  required real self-hosted provider/model + live-queue path cannot be
  exercised. Evidence: `PHASE5-P5-008-INTEGRATION-EVIDENCE.md`.
- Impact: P5-008 cannot reach IMPLEMENTED_PENDING_REVIEW with real evidence; it
  is recorded BLOCKED. Mocks are not substituted (ADR-022 D5-09).
- Owner decision required: provision the real runtime/model + Redis, or approve
  a smaller self-hosted model, or explicitly disposition/defer the gate.
- Blocked work: P5-008 only. Phase 6/7 remain unauthorized regardless.

## Resolved Blockers

| # | Classification | Resolution | Date |
|---|---|---|---|
| GOV-1 | `GOVERNANCE_BLOCKER` | Ratified under ADR-023 / `PHASE5-7-CONTROLLED-PARALLEL-EXECUTION.md` §4: eligible downstream work may start once a committed predecessor reaches `IMPLEMENTED_PENDING_REVIEW`. P5-002-after-P5-001 ordering ratified. | 2026-09-21 |
| GOV-2 | `GOVERNANCE_BLOCKER` | `AGENTS.md` / `CURRENT_STATE.md` updated to record Phase 5 = AUTHORIZED FOR IMPLEMENTATION and the P6/P7 allowlists. | 2026-09-21 |
| GOV-3 | `GOVERNANCE_BLOCKER` | Same root cause as B-001 (uncommitted Phase 3/4 baseline); closed with B-001. | 2026-09-22 |
| B-001 | `GOVERNANCE_BLOCKER` | Phase 3/4 baseline committed in the pre-review-pause checkpoint (`2dc4c66`, `76f884d`, `943dba1`) plus the named P5 dependencies (`2ea55d3`, `da482e3`, `6961087`). The working tree is now clean at HEAD. | 2026-09-22 |
| B-002 | `GOVERNANCE_BLOCKER` | Worker `/translate` wiring committed (`63a1a76`); worker model runtime dependencies declared in `worker/requirements.txt` (P5-003 cycle 2). | 2026-09-22 |
| B-003 | `TRACK_BLOCKER` | Claude API recovered on 2026-09-22 (`claude -p` returns normally). Fresh review/re-review is authorized by `DECISION-P5-PENDING-REVIEW-AUTHORIZATION-001`; execution is the next batch. | 2026-09-22 |

## Rules

1. Record every blocker with classification and affected task.
2. A blocked task never halts unrelated eligible work.
3. Contract/security/data-integrity/governance blockers require an HPO decision;
   do not invent the resolution.
4. Re-check the blocker queue before declaring the run idle.