# Orchestration Policy

This policy governs the two-agent operating model in this repository: **OpenCode** (Builder) and **Claude Code** (Reviewer), with the **Human Product Owner** as the decision-maker and task closer. Repository artifacts are the shared source of truth.

This file is the one canonical State-to-Action Contract. Other governance
artifacts may summarize its implications, but must cross-reference this file
instead of duplicating or redefining its state/action table.

## Agent Responsibilities

- **OpenCode** — Builder. Implements the assigned task, creates/updates tests, runs verification (tests, lint, typecheck), and moves to REVIEW. Must not mark its own work VERIFIED, close its own tasks as DONE, expand product scope, make Human Product Owner decisions, or begin unrelated tasks automatically.

- **Claude Code** — Independent Reviewer. Reviews implementation correctness, verifies acceptance criteria, inspects architecture and security boundaries, assesses regressions and edge cases, and produces durable review artifacts. Returns VERIFIED or CHANGES_REQUESTED. Must not modify implementation code during review, become the implementation owner, review its own implementation, or make product decisions.

- **Human Product Owner** — Decider. Owns product scope, architecture, security, destructive operations, production deployment, phase completion, and phase authorization. Closes VERIFIED tasks as DONE. Decides BLOCKED tasks. Agents may provide evidence and recommendations; agents must not silently make these decisions.

- **Repo** — Remember. Shared durable memory and source of truth via repository artifacts. Routine agent-to-agent communication must happen through repository artifacts rather than requiring manual relay.

## Work Continuity

- A blocked task must not block unrelated runnable tasks.
- READY tasks whose dependencies are satisfied may continue while another task is blocked or awaiting a decision.
- Only tasks directly affected by an unresolved decision become BLOCKED.
- Unresolved Human Product Owner decisions are recorded in [DECISION_QUEUE.md](../../DECISION_QUEUE.md), including affected tasks and tasks that remain unblocked.

## Decision Boundaries

Agents must not invent product, UX, architecture, security, destructive-operation, production-deployment, phase-completion, or phase-authorization decisions. Agents may make local implementation decisions when they remain within an already approved task and do not change its product contract. Decisions requiring the Human Product Owner remain queued until resolved.

## Task Lifecycle and Review

The normal flow is:

```
READY → IN_PROGRESS → REVIEW → VERIFIED → DONE
```

`CHANGES_REQUESTED` returns the task to implementation (`IN_PROGRESS`). A task may undergo at most three autonomous repair and re-review cycles. If it still cannot pass review after the third cycle, escalate it as BLOCKED and record the evidence in the task and review artifacts.

Actionable non-blocking LOW or MEDIUM review findings become separate READY follow-up tasks that reference the originating review artifact. Follow-up tasks do not start automatically.

## State-to-Action Contract

The following mapping is authoritative. Every non-terminal, non-blocked state represents runnable work.

| State | Who Acts | Required Action |
|-------|----------|-----------------|
| `READY` | Human Product Owner | Assign to OpenCode, or OpenCode self-assigns if authorized. |
| `IN_PROGRESS` | OpenCode | Continue implementation. |
| `REVIEW` | Claude Code | Independent review, produce durable artifact in `reviews/`. |
| `CHANGES_REQUESTED` | OpenCode | Fix issues, return to REVIEW. |
| `VERIFIED` | Human Product Owner | Close task as DONE. |
| `DONE` | — | No further action. |
| `BLOCKED` | Human Product Owner | Decide: unblock, reassign, defer, or close. |
| `BACKLOG` | Human Product Owner | Promote to READY when authorized. |

### Critical Rules

1. **REVIEW is Claude Code's responsibility.** When OpenCode moves a task to REVIEW, Claude Code must review it unless blocked by an unresolved dependency or Human Product Owner decision.

2. **VERIFIED does not mean DONE.** After Claude Code returns VERIFIED, the Human Product Owner must close the task as DONE. VERIFIED is not the same as DONE until closure is completed.

3. **CHANGES_REQUESTED preserves ownership.** OpenCode fixes its own CHANGES_REQUESTED items. There is only one implementation agent, so no reassignment is needed.

4. **BLOCKED is scoped.** Unrelated authorized runnable work continues. Only tasks directly affected by the blocker become BLOCKED.

## Three-Cycle Escalation

```
Cycle 1: CHANGES_REQUESTED → OpenCode fixes → REVIEW
Cycle 2: CHANGES_REQUESTED → OpenCode fixes → REVIEW
Cycle 3: CHANGES_REQUESTED → ESCALATE to Human Product Owner
```

After the third consecutive CHANGES_REQUESTED, the task becomes **BLOCKED**. The Human Product Owner decides:
- Reassign to a different approach
- Defer the task
- Accept with known limitations
- Close as unneeded

## Phase Gates

Phase completion stops at `AWAITING HUMAN ACCEPTANCE`. Completing one phase never automatically authorizes the next phase. The next phase requires explicit Human Product Owner authorization recorded in the roadmap or an explicit user instruction.

## Durable Records

Task status belongs in `tasks/`, independent review results belong in `reviews/`, current operational state belongs in [CURRENT_STATE.md](../../CURRENT_STATE.md), durable decisions belong in [DECISIONS.md](../../DECISIONS.md), and unresolved decisions belong in [DECISION_QUEUE.md](../../DECISION_QUEUE.md).

## Phase 3 Batch-Execution Exception (ADR-017)

Phase 3 uses a batch-level review model that is a Phase-specific exception
to the normal per-task review cadence. This exception does not apply to
any other phase unless separately authorized.

### Batch authorization

One explicit HPO Batch authorization may authorize the set of tasks
belonging to that batch. Phase 3 planning acceptance does not itself
authorize any batch.

For Batch 1:

```
P3-001
P3-002
Turbo vs Large-v3 Benchmark Gate
P3-003
```

HPO must explicitly authorize Batch 1 before any Batch 1 task is promoted
to READY.

### READY transition

When HPO explicitly authorizes a batch, all implementation tasks in that
batch may be promoted to READY at authorization time. Dependency ordering
still controls when implementation may begin.

```
HPO authorizes Batch 1
  ↓
P3-001 / P3-002 / P3-003 become READY
  ↓
P3-001 may begin (no predecessor dependency)
P3-002 begins after P3-001 is implementation-complete
Benchmark gate runs after P3-002 is implementation-complete
P3-003 begins after benchmark gate is recorded
```

### Sequential implementation inside batch

Within an authorized batch, OpenCode may proceed sequentially without
Claude reviewing every task individually, provided:

- the previous task is internally implementation-complete;
- relevant tests pass;
- no known blocking dependency defect exists;
- the next task is inside the already HPO-authorized batch;
- the next task's dependency contract permits an
  implementation-complete predecessor rather than an independently
  VERIFIED predecessor.

A task may remain officially `IN_PROGRESS` while the next task in the
batch begins implementation. This is a Phase 3 exception to normal
per-task review cadence, not a new global workflow.

```
P3-001 implementation-complete (may remain IN_PROGRESS)
  ↓
P3-002 begins implementation
  ↓
P3-002 implementation-complete (may remain IN_PROGRESS)
  ↓
Benchmark gate evidence recorded
  ↓
P3-003 begins implementation
```

### Review handoff

At the end of the batch, all implementation tasks in the batch move
to `REVIEW`:

```
P3-001 → REVIEW
P3-002 → REVIEW
P3-003 → REVIEW
  ↓
Claude performs one independent batch review
```

Claude records task-specific findings and one overall batch verdict.

### VERIFIED

The independent reviewer determines whether each task satisfies its
contract while also returning one overall batch verdict.

If the batch verdict is `CHANGES_REQUESTED`, the batch does not progress
even if some individual tasks have no findings.

If the batch verdict is `VERIFIED`, the reviewed tasks may enter the
repository-supported `VERIFIED` state.

OpenCode must never self-assign `VERIFIED`.

### DONE

`VERIFIED` → `DONE` remains subject to the existing HPO/governance
closure rule. Claude's `VERIFIED` does not automatically equal `DONE`.

### Blocking defect inside a batch

If an earlier task develops a known defect that invalidates the next
task's dependency contract:

```
STOP progression
```

even though no independent review is scheduled yet. Surface the
blocker to HPO. Do not continue to the next task in the batch.

## Migration from Previous Model

This policy supersedes the previous multi-agent model (Work, OpenCode, Claude Code, Codex). Historical task records and review artifacts from the previous model are preserved as historical truth. The agent-role definitions and orchestration portions of prior ADRs (including ADR-004 and ADR-010) are superseded by this policy; their repository-handoff principles remain authoritative.
