# Decision Queue

This queue records unresolved product, UX, architecture, security, destructive-operation, production, and phase decisions for the Human Product Owner or another explicitly authorized decision-maker.

An unresolved decision affects only the tasks listed in **Blocks**. It must never pause unrelated runnable work. READY tasks with satisfied dependencies may proceed while a decision is pending.

## Queue Statuses

- OPEN — awaiting an authorized decision.
- DECIDED — recorded here and, where durable, in DECISIONS.md.
- WITHDRAWN — no longer applicable.

## Decision Entry Template

Decision ID: DECISION-XXX

Status: DECIDED

Type: Product | UX | Architecture | Security | Destructive Operation | Production Deployment | Phase Completion | Phase Authorization

Originating Task: TASK-XXX

Raised By: Agent or Human Product Owner

Priority: LOW | MEDIUM | HIGH | BLOCKER

Question:

State the decision required in one clear question.

Options:

1. Option A
2. Option B

Recommendation:

Provide an engineering recommendation only when the decision-maker has enough context to choose deliberately.

Impact:

Describe the affected behavior, risks, and consequences.

Blocks:

- TASK-XXX

Does Not Block:

- TASK-YYY

Resolution:

To be completed by the authorized decision-maker. Link the durable ADR or task update when resolved.

## Open Decisions

### DECISION-P1-001 — Prototype creation title contract

Decision ID: DECISION-P1-001
Status: OPEN
Type: Product
Originating Task: TASK-P1-CREATE-001
Raised By: Codex orchestration
Priority: MEDIUM

Question:

For Phase 1 demo transcription creation, should Title be required, or should a blank Title create a transcription with an explicitly chosen demo default title?

Options:

1. Require Title. Preserve current server validation; remove the optional label and filename-fallback promise from the form. No actual filename is available in Phase 1.
2. Allow blank Title with a Product Owner-approved demo default. Define that exact default and update server validation, form copy and tests. This is still demo-only and does not authorize file upload.

Recommendation:

Option 1, because it preserves the existing validated contract and avoids inventing a filename fallback when no file is uploaded. This is a recommendation, not a recorded decision.

Impact:

The current UI and server disagree. A user following the optional-title instruction receives validation errors instead of the promised fallback. Choosing between required input and an automatic title changes product behavior, so the agent does not select one silently.

Blocks:

- TASK-P1-CREATE-001.
- Full Phase 1 prototype acceptance readiness until the selected contract is implemented and reviewed.

Does Not Block:

- TASK-004C.
- TASK-P1-STATIC-001, TASK-P1-STATIC-002, TASK-P1-STATIC-003, TASK-P1-STATIC-004.
- Other unrelated authorized runnable Phase 1 work.

Resolution:

Human Product Owner decided on 2026-09-11: Title remains REQUIRED. UI copy was aligned to existing server validation; no filename fallback or upload behavior was added. Durable record: ADR-006 in DECISIONS.md. Phase 2 remains unauthorized.
## Decision History

DECISION-P1-001 — DECIDED; see ADR-006 in DECISIONS.md.
