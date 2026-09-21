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
| B-001 (named items) | `GOVERNANCE_BLOCKER` | Named P5 dependencies tracked with provenance: `SegmentTimestamp` (`2ea55d3`), segment-language schema/cast (`da482e3`), speech-detected schema (`6961087`). The wider Phase 3/4 baseline (e.g. unique-segment-index/failure-code migrations, transcription/processing-job controllers) remains uncommitted and is still an HPO action; a catch-all baseline commit was expressly not created. | 2026-09-21 |
| B-002 (named items) | `GOVERNANCE_BLOCKER` | Worker `/translate` wiring tracked (`63a1a76`); worker model runtime dependencies declared in `worker/requirements.txt` (P5-003 cycle 2). | 2026-09-21 |

### B-002 — P5-003 worker `/translate` route wiring cannot be committed
- Classification: `GOVERNANCE_BLOCKER` (informational; work-level, non-stopping)
- Task/Source: P5-003
- Detail: the `/translate` FastAPI route lives in `worker/main.py`, which also
  carries uncommitted Phase 3/4 worker changes in the dirty baseline. Committing
  the file wholesale would mix the Phase 3 baseline into a P5-003 commit.
- Impact: the route is implemented and worker-tested in the working tree
  (`worker/tests/test_translation.py`, 4 passed) but remains uncommitted.
  `worker/translation.py` is committed. PHP-side P5-003 is fully committed and
  self-sufficient.
- Mitigation: same as B-001 — do not stage baseline files wholesale.
- Owner decision required: none to proceed; the HPO should commit the Phase 3/4
  baseline on its own authority so these wiring files can be committed.
- Blocked work: none (worker endpoint is not required by PHP tests; P5-008 will
  require it).

## Rules

1. Record every blocker with classification and affected task.
2. A blocked task never halts unrelated eligible work.
3. Contract/security/data-integrity/governance blockers require an HPO decision;
   do not invent the resolution.
4. Re-check the blocker queue before declaring the run idle.