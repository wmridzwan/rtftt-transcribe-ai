# Orchestration Policy

This policy governs Codex, Claude Code, and OpenCode work in this repository. Repository artifacts are the shared source of truth.

## Work Continuity

- A blocked task must not block unrelated runnable tasks.
- READY tasks whose dependencies are satisfied may continue while another task is blocked or awaiting a decision.
- Only tasks directly affected by an unresolved decision become BLOCKED.
- Unresolved Human Product Owner decisions are recorded in [DECISION_QUEUE.md](../../DECISION_QUEUE.md), including affected tasks and tasks that remain unblocked.

## Decision Boundaries

Agents must not invent product, UX, architecture, security, destructive-operation, production-deployment, phase-completion, or phase-authorization decisions. Agents may make local implementation decisions when they remain within an already approved task and do not change its product contract. Decisions requiring the Human Product Owner remain queued until resolved.

## Task Lifecycle and Review

The normal flow is:

`READY → IN_PROGRESS → REVIEW → VERIFIED → DONE`

`CHANGES_REQUESTED` returns the task to implementation (`IN_PROGRESS`). A task may undergo at most three autonomous repair and re-review cycles. If it still cannot pass review after the third cycle, escalate it as BLOCKED and record the evidence in the task and review artifacts.

Actionable non-blocking LOW or MEDIUM review findings become separate READY follow-up tasks that reference the originating review artifact. Follow-up tasks do not start automatically.

## Phase Gates

Phase completion stops at `AWAITING HUMAN ACCEPTANCE`. Completing one phase never automatically authorizes the next phase. The next phase requires explicit Human Product Owner authorization recorded in the roadmap or an explicit user instruction.

## Durable Records

Task status belongs in `tasks/`, independent review results belong in `reviews/`, current operational state belongs in [CURRENT_STATE.md](../../CURRENT_STATE.md), durable decisions belong in [DECISIONS.md](../../DECISIONS.md), and unresolved decisions belong in [DECISION_QUEUE.md](../../DECISION_QUEUE.md).
