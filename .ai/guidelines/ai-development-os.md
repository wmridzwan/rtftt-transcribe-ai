# AI Development Operating System

## Purpose

This repository may be worked on by multiple AI coding agents including

Claude Code and OpenCode.
The repository is the shared source of truth between agents.
Do not rely on another agent's chat history or summary when repository
state provides the answer.

## Source of Truth

Interpret project information in this order:

1. The current explicit user instruction or Human Product Owner decision.
2. `AGENTS.md` and the canonical State-to-Action Contract in
   `.ai/guidelines/orchestration-policy.md` for agent authority and lifecycle
   behavior.
3. Accepted ADRs in `DECISIONS.md` for durable product, architecture,
   security, and workflow decisions. An ADR does not authorize execution by
   itself.
4. `plan.md` for authorized roadmap and phase scope.
5. `CURRENT_STATE.md` for current operational state, gates, and status.
6. The relevant task file for task-level scope and acceptance criteria.
7. `architecture.md` for repository architecture within accepted decisions and
   the authorized plan.
8. Source, schema, tests, configuration, CI, and Git history as evidence of
   what exists. Evidence does not create authorization by itself.
9. `PROJECT_CONTEXT.md` and other future-looking material for context only.
10. Framework and package guidelines.

Future ideas and proposed external governance documents are not implementation
authorization until they are reconciled into repository-native artifacts and
explicitly approved. The State-to-Action mapping is defined once in the
orchestration policy; other files cross-reference it rather than duplicating
the state/action table.

## Canonical Agent Architecture

The repository follows a two-agent operating model — **OpenCode** (Builder)
and **Claude Code** (Reviewer) — with the **Human Product Owner** as the
decision-maker and task closer. One implementation task must have only one
active implementation owner.

### OpenCode — Builder

OpenCode is the implementation agent. OpenCode implements the assigned task,
creates/updates tests, runs verification (tests, lint, typecheck), and moves
the task to REVIEW. OpenCode must not mark its own work VERIFIED, close its
own tasks as DONE, expand product scope, make Human Product Owner decisions,
or begin unrelated tasks automatically.

### Claude Code — Independent Reviewer

Claude Code is the independent reviewer. Claude reviews implementation
correctness, verifies acceptance criteria, inspects architecture and security
boundaries, assesses regressions and edge cases, and produces durable review
artifacts. Claude returns VERIFIED or CHANGES_REQUESTED. Claude must not
modify implementation code during review, become the implementation owner,
review its own implementation, or make product decisions.

### Ridzwan / Human Product Owner — Decider

The Human Product Owner owns decisions involving product scope, materially
different UX behavior, major architecture, security-sensitive decisions,
destructive operations, production deployment, milestone acceptance, phase
completion, and phase authorization. The Human Product Owner closes VERIFIED
tasks as DONE and decides BLOCKED tasks. Agents may provide evidence and
recommendations. Agents must not silently make these decisions. Unresolved
decisions belong in DECISION_QUEUE.md. Resolved durable decisions belong in
DECISIONS.md.

### Repo — Remember

The repository is the shared durable memory and source of truth. Routine
agent-to-agent communication must happen through repository artifacts
(plan.md, CURRENT_STATE.md, tasks/, reviews/, DECISIONS.md,
DECISION_QUEUE.md, architecture.md, Git history, and governance/rule files)
rather than requiring manual relay between agents. Chat history is not the
canonical project state when repository state exists.

A role may be changed explicitly for a specific task.

The canonical State-to-Action Contract (who acts on each task lifecycle
state) is defined once in `.ai/guidelines/orchestration-policy.md`; this file
cross-references it rather than duplicating the table.

## Task Ownership

One implementation task must have only one active implementation owner.
Do not modify another agent's active task unless explicitly reassigned.
Before editing code:

1. Read CURRENT_STATE.md.
2. Read the relevant task specification.
3. Inspect git status.
4. Read applicable project rules.
5. Confirm the work is authorized by plan.md.

## Task Lifecycle

Allowed states:
- BACKLOG
- READY
- IN_PROGRESS
- REVIEW
- CHANGES_REQUESTED
- VERIFIED
- DONE
- BLOCKED


Normal flow:

READY -> IN_PROGRESS -> REVIEW -> VERIFIED -> DONE


If review finds blocking issues:

REVIEW -> CHANGES_REQUESTED -> IN_PROGRESS -> REVIEW

## Implementation Agent

The implementation owner must:

1. Work only within the assigned task scope.
2. Follow architecture.md and DECISIONS.md.
3. Add or update appropriate tests.
4. Run relevant verification.
5. Update the task file with implementation notes.
6. Move the task to REVIEW when implementation verification passes.
7. Update CURRENT_STATE.md when project state changes.

The implementation agent must not mark its own implementation VERIFIED.

## Independent Reviewer

The reviewer should not modify implementation code unless explicitly
reassigned as the implementation owner.

Review:
- correctness
- acceptance criteria
- architecture compliance
- regressions
- authorization and ownership boundaries
- security
- edge cases
- performance where relevant
- test adequacy

Write durable review results under reviews/.

Finding severity:
- BLOCKER
- HIGH
- MEDIUM
- LOW

BLOCKER or HIGH findings prevent VERIFIED status.

## Verification

A task may become VERIFIED only after:
- relevant tests pass
- required formatting and static analysis pass
- acceptance criteria are satisfied
- no unresolved BLOCKER or HIGH findings remain

VERIFIED does not authorize production deployment.


## Human Decision Gates

Stop and request human input when work requires:
- changing product scope
- changing a major architecture decision
- choosing between materially different product behaviors
- destructive database or production actions
- security-sensitive external access
- production deployment
- milestone UX or product acceptance

Routine implementation, review, and fix cycles do not require human
intervention.

## Durable Handoffs

Agents communicate through repository artifacts:
- plan.md - authorized roadmap
- CURRENT_STATE.md - current operational state
- tasks/ - implementation contracts
- reviews/ - independent review results
- DECISIONS.md - durable product and engineering decisions
- Git history - implementation history


Important project information must not exist only in chat output.


The user should not need to manually relay routine implementation

summaries or review findings between agents.


## Git Safety



Before implementation:
- inspect git status
- do not overwrite unrelated uncommitted changes
- do not force-push
- do not rewrite shared history
- do not delete branches or worktrees without authorization

Prefer isolated branches or worktrees for parallel implementation.


## Scope Discipline



Do not automatically start another task, module, or phase merely because

the current task is complete.



Continue automatically only within work explicitly authorized by the

current task or instruction.



Future phases remain unauthorized until plan.md or the user authorizes them.

## Governance References

The shared orchestration rules are defined in [.ai/guidelines/orchestration-policy.md](orchestration-policy.md). Unresolved Human Product Owner decisions are recorded in [DECISION_QUEUE.md](../../DECISION_QUEUE.md); only tasks affected by a queued decision are blocked.

## Orchestration Governance

The full State-to-Action Orchestration Contract is defined in `.ai/guidelines/orchestration-policy.md`. The critical rules:

- **REVIEW is Claude Code's responsibility.** A task in REVIEW must be routed to Claude Code for independent review unless blocked by an unresolved dependency or Human Product Owner decision. "Awaiting independent review" MUST NOT be treated as "no runnable authorized work."
- **CHANGES_REQUESTED preserves ownership.** OpenCode fixes its own CHANGES_REQUESTED items; there is only one implementation agent, so no reassignment is needed.
- **VERIFIED does not mean DONE.** After Claude Code returns VERIFIED, the Human Product Owner must close the task as DONE.
- **BLOCKED is scoped.** A blocked task must not block unrelated runnable tasks. READY tasks with satisfied dependencies may continue.

Human decisions are accumulated in DECISION_QUEUE.md. Agents must record the decision ID, status, type, originating task, question, options, recommendation, impact, tasks blocked, and tasks explicitly not blocked. Only affected tasks become BLOCKED.

Agents must not invent product, UX, architecture, security, destructive-operation, production-deployment, phase-completion, or phase-authorization decisions. Agents may make local implementation decisions only when those decisions remain within an already approved task and do not change an approved product contract.

The task lifecycle remains READY → IN_PROGRESS → REVIEW → VERIFIED → DONE. CHANGES_REQUESTED returns the task to implementation. A maximum of three autonomous repair/re-review cycles is allowed; after the third unsuccessful cycle, escalate the task as BLOCKED with the evidence recorded in the task and review artifacts.

Actionable non-blocking LOW or MEDIUM review findings become separate READY follow-up tasks that reference their originating review artifact. Follow-up tasks are not started automatically.

Phase completion stops at AWAITING HUMAN ACCEPTANCE. Completion must never automatically authorize the next phase; the next phase requires explicit authorization in plan.md or from the user.

