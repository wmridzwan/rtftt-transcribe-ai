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

None.

## DECIDED — DECISION-P2-CONCURRENCY-002

### DECISION-P2-CONCURRENCY-002 — Adopt the Option B claim CAS protocol for P2-004A/P2-004A1

Decision ID: DECISION-P2-CONCURRENCY-002

Status: DECIDED

Type: Architecture

Originating Tasks: P2-004A, P2-004A1

Raised By: Claude Code, at the Human Product Owner's request for a concrete
solution to the blocked concurrency contract

Priority: MEDIUM (P2-004A/P2-004A1 remain BLOCKED and Phase 2 is already
ACCEPTED under ADR-014; this does not block other runnable work)

Question:

Should the repository adopt the concrete SQLite-safe compare-and-set claim
protocol in `reviews/P2-004A-P2-004A1-option-b-protocol-proposal.md` as the
selected Option B mechanism, and authorize a new, narrowly-scoped
implementation task for it?

Options:

1. Approve the proposed CAS protocol (add `held_by`/`cleanup_claimed_at` to
   `staging_claims`; replace `lockForUpdate()` in `CleanupStaging` and the
   claim upsert in `MediaIngestionService` with single guarded
   `UPDATE`/`INSERT ... OR IGNORE` statements; move file deletion outside the
   transaction) and authorize a new implementation task scoped to exactly
   that.
2. Request changes to the proposed protocol before approving.
3. Keep Option D (defer) in force indefinitely; do not schedule this work.
4. Select a different resolution class from the original decision package
   (A or C) instead of B.

Recommendation:

Option 1. The proposal keeps the existing engine (SQLite) and schema shape,
requires only an additive migration, and replaces the exact mechanism the
independent review identified as unproven (`lockForUpdate()`) with a
single-statement CAS pattern that is atomic on SQLite without relying on row
locks. It also directly specifies the genuine-independent-process test
approach the verification gate requires, which was the missing piece in the
prior three CHANGES_REQUESTED cycles.

Impact:

Approving this unblocks a path to close P2-004A/P2-004A1, but does not by
itself mark them VERIFIED — the future implementation task must still pass
the verification gate in
`reviews/P2-004A-P2-004A1-sqlite-concurrency-decision-package.md` and
independent review. This does not reopen or change the already-DONE
P2-002B/P2-003 contracts; the proposal preserves their behavior.

Blocks:

- Authorization of a new P2-004A/P2-004A1 implementation task (nothing may
  be implemented against this design until it is approved).

Does Not Block:

- P2-007 or any other Phase 2 runnable work.
- Phase 3 authorization, which is a separate, unrelated gate.

Resolution:

The Human Product Owner approved Option 1 on 2026-09-15. A new implementation
task, P2-004A2, is authorized and READY:
`tasks/P2-004A2-staging-claim-cas-protocol.md`. Durable record: ADR-016 in
`DECISIONS.md`. Option D (ADR-013) remains in force until P2-004A2 is
independently VERIFIED and closed as DONE.

## DECIDED — DECISION-P2-CONCURRENCY-001

Decision ID: DECISION-P2-CONCURRENCY-001

Status: DECIDED
Type: Architecture
Originating Tasks: P2-004A, P2-004A1
Raised By: Codex orchestration after independent remediation-cycle-2 review
Priority: HIGH

Question:

Which approved concurrency contract should govern cleanup versus ingestion for
the repository's actual SQLite deployment: an explicitly SQLite-designed
mechanism, a redesigned SQLite claim protocol, a production engine change, or
continued deferral until cleanup is operationally required?

Options:

1. Retain SQLite and explicitly design around SQLite-supported transaction and
   locking semantics.
2. Retain SQLite but redesign the cleanup/claim protocol so correctness does
   not depend on unsupported row-level `FOR UPDATE` locking.
3. Change the production database/concurrency contract to an engine providing
   the locking semantics assumed by the current design.
4. Defer cleanup/claim execution until an operational need exists.

Recommendation:

Approve deferral for the current gate; if cleanup becomes necessary, prefer a
SQLite-specific claim protocol with genuine independent-connection/process
verification. This is an engineering recommendation only.

Impact:

P2-004A and P2-004A1 remain BLOCKED. No autonomous fourth repair cycle may
start. The exact invariant, contention outcome, transaction semantics, and
verification gate are documented in
`reviews/P2-004A-P2-004A1-sqlite-concurrency-decision-package.md`.

Blocks:

- P2-004A.
- P2-004A1.
- P2-007 and Phase 3 remain separately unauthorized while the Phase 2 gate is
  unresolved.

Does Not Block:

- P2-003 and P2-005 closure as DONE.
- Historical completed tasks and their preserved review evidence.

Resolution:

The Human Product Owner approved Option D on 2026-09-13. Automated
abandoned-staging cleanup is deferred out of the current Phase 2 completion
scope. P2-004A and P2-004A1 remain BLOCKED because the canonical lifecycle has
no DEFERRED state; they are not VERIFIED or DONE. The current SQLite
`lockForUpdate()` implementation is not accepted as a row-lock guarantee.
Existing P2-002B/P2-003 synchronous compensation, retry, ownership, and
same-attempt idempotency contracts remain authoritative. Option B is the
preferred future resolution class, but it is not authorized now. Durable
record: ADR-013 in `DECISIONS.md`.

## DECIDED — DECISION-P2-PHASE2-ACCEPTANCE-001

Decision ID: DECISION-P2-PHASE2-ACCEPTANCE-001
Status: DECIDED
Type: Phase Completion
Originating Scope: Current bounded Phase 2 scope
Raised By: Human Product Owner
Priority: HIGH

Resolution:

The Human Product Owner accepted the current bounded Phase 2 scope on
2026-09-13. Phase 2 is recorded as ACCEPTED under ADR-014. P2-003 and P2-005
remain DONE. P2-004A and P2-004A1 remain BLOCKED and deferred from the current
gate; they are not VERIFIED or DONE. Automated abandoned-staging cleanup
remains unauthorized.

This acceptance does not create or promote P2-007, make it eligible, or
authorize it. Phase 3 remains not eligible and not authorized. Durable record:
ADR-014 in `DECISIONS.md`.

## DECIDED — DECISION-P2-REMEDIATION-001

The Human Product Owner resolved the authorization question on 2026-09-13 by
explicitly authorizing remediation of P2-004A, P2-004A1, P2-005, and the affected
P2-003 service surface. The earlier missing authorization record remains a
governance/audit-trail failure and the independent finding is preserved. The
minimum FFprobe/FFmpeg dependency is approved for P2-005 in Phase 2 only; no
transcription, worker, queue, P2-007, or Phase 3 work is authorized.

Durable resolution: ADR-012 in `DECISIONS.md`. Affected tasks remain REVIEW /
AWAITING INDEPENDENT RE-REVIEW and cannot be marked VERIFIED or DONE by the
implementation owner.

## Decision History

### DECISION-P1-001 — Prototype creation title contract

Decision ID: DECISION-P1-001
Status: DECIDED
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
