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

## Migration from Previous Model

This policy supersedes the previous multi-agent model (Work, OpenCode, Claude Code, Codex). Historical task records and review artifacts from the previous model are preserved as historical truth. The agent-role definitions and orchestration portions of prior ADRs (including ADR-004 and ADR-010) are superseded by this policy; their repository-handoff principles remain authoritative.
