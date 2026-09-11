# AI Development Operating System

## Purpose

This repository may be worked on by multiple AI coding agents including

Codex, Claude Code, and OpenCode.
The repository is the shared source of truth between agents.
Do not rely on another agent's chat history or summary when repository
state provides the answer.

## Source of Truth

Interpret project information in this order:

1. User's current explicit instruction
2. plan.md
3. CURRENT_STATE.md
4. Relevant task specification under tasks/
5. architecture.md
6. DECISIONS.md
7. PROJECT_CONTEXT.md
8. Framework and package guidelines

Future ideas in PROJECT_CONTEXT.md are not implementation authorization.

## Default Agent Roles

- Codex: primary implementation agent and engineering orchestrator
- Claude Code: independent reviewer, debugger, and forensic investigator
- OpenCode: interactive local engineer and experimentation agent
- Human Product Owner: product decisions, major architecture approval,

&#x20; UX acceptance, destructive actions, and production release approval

A role may be changed explicitly for a specific task.

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

Blocked work is scoped to the blocked task. A blocked task must not block unrelated runnable tasks, and READY tasks with satisfied dependencies may continue while another task awaits a Human Product Owner decision.

Human decisions are accumulated in DECISION_QUEUE.md. Agents must record the decision ID, status, type, originating task, question, options, recommendation, impact, tasks blocked, and tasks explicitly not blocked. Only affected tasks become BLOCKED.

Agents must not invent product, UX, architecture, security, destructive-operation, production-deployment, phase-completion, or phase-authorization decisions. Agents may make local implementation decisions only when those decisions remain within an already approved task and do not change an approved product contract.

The task lifecycle remains READY → IN_PROGRESS → REVIEW → VERIFIED → DONE. CHANGES_REQUESTED returns the task to implementation. A maximum of three autonomous repair/re-review cycles is allowed; after the third unsuccessful cycle, escalate the task as BLOCKED with the evidence recorded in the task and review artifacts.

Actionable non-blocking LOW or MEDIUM review findings become separate READY follow-up tasks that reference their originating review artifact. Follow-up tasks are not started automatically.

Phase completion stops at AWAITING HUMAN ACCEPTANCE. Completion must never automatically authorize the next phase; the next phase requires explicit authorization in plan.md or from the user.

