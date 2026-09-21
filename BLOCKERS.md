# Blockers

Blocker queue for the Phase 5–7 Controlled Parallel Execution Authorization
(§19). A blocked task does not stop the run; the scheduler switches to another
eligible track. The run stops only when no eligible authorized work remains or a
`GLOBAL_BLOCKER` exists.

Classifications: `LOCAL_BLOCKER`, `TRACK_BLOCKER`, `CONTRACT_BLOCKER`,
`SECURITY_BLOCKER`, `DATA_INTEGRITY_BLOCKER`, `GOVERNANCE_BLOCKER`,
`GLOBAL_BLOCKER`.

## Open Blockers

### B-001 — Phase 3/4 baseline is uncommitted; separate worktree not feasible
- Classification: `GOVERNANCE_BLOCKER` (informational; work-level, non-stopping)
- Task/Source: execution setup (§22)
- Detail: the canonical worktree is dirty; all Phase 3 Batch 2/3 and Phase 4
  implementation files are untracked/modified and absent from HEAD (`55c620a`).
  `git worktree add` cannot carry untracked files, so a parallel worktree would
  lack the required baseline. Execution therefore proceeds on an isolated branch
  (`phase5-7/parallel-2026-09-21`) created in place.
- Impact: the canonical branch is not advanced; pre-existing dirty changes are
  preserved untouched; task-scoped commits stage only files owned by the task.
- Mitigation recorded: never `git add -A`; stage explicit paths per task.
- Owner decision required: none to proceed; HPO may later choose to commit the
  Phase 3/4 baseline on its own authority.
- Blocked work: none.

## Resolved Blockers

| # | Classification | Resolution | Date |
|---|---|---|---|
| GOV-1 (reviewer) | `GOVERNANCE_BLOCKER` | Ratified under ADR-023 / `PHASE5-7-CONTROLLED-PARALLEL-EXECUTION.md` §4: eligible downstream work may start once a committed predecessor reaches `IMPLEMENTED_PENDING_REVIEW`. P5-002-after-P5-001 ordering ratified. | 2026-09-21 |
| GOV-2 (reviewer) | `GOVERNANCE_BLOCKER` | `AGENTS.md` / `CURRENT_STATE.md` updated in the working tree to record Phase 5 = AUTHORIZED FOR IMPLEMENTATION, P5-001/P5-002 = VERIFIED, P6/P7 allowlists only. These two files carry pre-existing Phase 4 governance edits and remain uncommitted (see B-001). | 2026-09-21 |
| GOV-3 (reviewer) | `GOVERNANCE_BLOCKER` | Same root cause as B-001 (uncommitted Phase 3/4 baseline); not a new defect. | 2026-09-21 |

## Rules

1. Record every blocker with classification and affected task.
2. A blocked task never halts unrelated eligible work.
3. Contract/security/data-integrity/governance blockers require an HPO decision;
   do not invent the resolution.
4. Re-check the blocker queue before declaring the run idle.