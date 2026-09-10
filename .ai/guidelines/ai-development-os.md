\# AI Development Operating System



\## Purpose



This repository may be worked on by multiple AI coding agents including

Codex, Claude Code, and OpenCode.



The repository is the shared source of truth between agents.



Do not rely on another agent's chat history or summary when repository

state provides the answer.



\## Source of Truth



Interpret project information in this order:



1\. User's current explicit instruction

2\. plan.md

3\. CURRENT\_STATE.md

4\. Relevant task specification under tasks/

5\. architecture.md

6\. DECISIONS.md

7\. PROJECT\_CONTEXT.md

8\. Framework and package guidelines



Future ideas in PROJECT\_CONTEXT.md are not implementation authorization.



\## Default Agent Roles



\- Codex: primary implementation agent and engineering orchestrator

\- Claude Code: independent reviewer, debugger, and forensic investigator

\- OpenCode: interactive local engineer and experimentation agent

\- Human Product Owner: product decisions, major architecture approval,

&#x20; UX acceptance, destructive actions, and production release approval



A role may be changed explicitly for a specific task.



\## Task Ownership



One implementation task must have only one active implementation owner.



Do not modify another agent's active task unless explicitly reassigned.



Before editing code:



1\. Read CURRENT\_STATE.md.

2\. Read the relevant task specification.

3\. Inspect git status.

4\. Read applicable project rules.

5\. Confirm the work is authorized by plan.md.



\## Task Lifecycle



Allowed states:



\- BACKLOG

\- READY

\- IN\_PROGRESS

\- REVIEW

\- CHANGES\_REQUESTED

\- VERIFIED

\- DONE

\- BLOCKED



Normal flow:



READY -> IN\_PROGRESS -> REVIEW -> VERIFIED -> DONE



If review finds blocking issues:



REVIEW -> CHANGES\_REQUESTED -> IN\_PROGRESS -> REVIEW



\## Implementation Agent



The implementation owner must:



1\. Work only within the assigned task scope.

2\. Follow architecture.md and DECISIONS.md.

3\. Add or update appropriate tests.

4\. Run relevant verification.

5\. Update the task file with implementation notes.

6\. Move the task to REVIEW when implementation verification passes.

7\. Update CURRENT\_STATE.md when project state changes.



The implementation agent must not mark its own implementation VERIFIED.



\## Independent Reviewer



The reviewer should not modify implementation code unless explicitly

reassigned as the implementation owner.



Review:



\- correctness

\- acceptance criteria

\- architecture compliance

\- regressions

\- authorization and ownership boundaries

\- security

\- edge cases

\- performance where relevant

\- test adequacy



Write durable review results under reviews/.



Finding severity:



\- BLOCKER

\- HIGH

\- MEDIUM

\- LOW



BLOCKER or HIGH findings prevent VERIFIED status.



\## Verification



A task may become VERIFIED only after:



\- relevant tests pass

\- required formatting and static analysis pass

\- acceptance criteria are satisfied

\- no unresolved BLOCKER or HIGH findings remain



VERIFIED does not authorize production deployment.



\## Human Decision Gates



Stop and request human input when work requires:



\- changing product scope

\- changing a major architecture decision

\- choosing between materially different product behaviors

\- destructive database or production actions

\- security-sensitive external access

\- production deployment

\- milestone UX or product acceptance



Routine implementation, review, and fix cycles do not require human

intervention.



\## Durable Handoffs



Agents communicate through repository artifacts:



\- plan.md - authorized roadmap

\- CURRENT\_STATE.md - current operational state

\- tasks/ - implementation contracts

\- reviews/ - independent review results

\- DECISIONS.md - durable product and engineering decisions

\- Git history - implementation history



Important project information must not exist only in chat output.



The user should not need to manually relay routine implementation

summaries or review findings between agents.



\## Git Safety



Before implementation:



\- inspect git status

\- do not overwrite unrelated uncommitted changes

\- do not force-push

\- do not rewrite shared history

\- do not delete branches or worktrees without authorization



Prefer isolated branches or worktrees for parallel implementation.



\## Scope Discipline



Do not automatically start another task, module, or phase merely because

the current task is complete.



Continue automatically only within work explicitly authorized by the

current task or instruction.



Future phases remain unauthorized until plan.md or the user authorizes them.

