# Phase 5–7 — Controlled Parallel Execution Authorization (Durable Record)

Date: 2026-09-21
Status: ADOPTED — Human Product Owner
Authority: Human Product Owner (Controlled Parallel Execution Authorization)
Related: ADR-021, ADR-022, ADR-023; `PHASE5-7-EXECUTION-CLASSIFICATION.md`;
`PHASE5-7-DEPENDENCY-GRAPH.md`; `.ai/guidelines/orchestration-policy.md`

This file is the durable repository record of the Human Product Owner's
authorization for a controlled parallel execution model across Phases 5–7. The
independent reviewer (GOV-1 in `reviews/P5-002-independent-review.md`) could not
locate the authorization in the repository; this record supplies it.

## 1. Authorized model

Phase-level serialization (`Phase 5 CLOSED → Phase 6 → Phase 7`) is replaced by
dependency-driven parallelism with strict contract and closure boundaries:

```text
Phase 5 critical path
      │
      ├──────────────┐
P6 early-start    P7 early-hardening
independent work  independent work
      │              │
      └──────┬───────┘
             │
      Phase 5 CLOSED
             ↓
translation-aware Phase 6 work → Phase 6 CLOSED → final Phase 7 → P7-012
```

Phase numbering alone is never treated as proof of dependency.

## 2. Phase status at authorization

```text
Phase 5 = AUTHORIZED FOR IMPLEMENTATION
Phase 6 = NOT GENERALLY AUTHORIZED — EARLY-START ALLOWLIST ONLY
Phase 7 = NOT GENERALLY AUTHORIZED — EARLY-HARDENING ALLOWLIST ONLY
```

## 3. Standing unattended execution authority

Within the authorized scopes, the implementation agent may progress from one
**eligible** task to another once the current task reaches
`IMPLEMENTED_PENDING_REVIEW`, provided tests, evidence, internal pre-review, and
task-scoped commits are complete and no unresolved blocker prevents downstream
work. Independent verification does not have to occur immediately before
starting another eligible task.

The agent may never: mark its own work VERIFIED; close a phase; waive
BLOCKER/HIGH/MEDIUM findings; make HPO product decisions; modify frozen
contracts without authorization; redesign tenancy; expand scope; or cross from
allowlisted early work into general Phase 6/7 implementation.

## 4. Dependency gate interpretation (resolves reviewer GOV-1)

For an authorized parallel track, a task whose contract lists a predecessor as a
prerequisite may begin once that predecessor reaches
`IMPLEMENTED_PENDING_REVIEW` **and** its contract artifact is committed and
stable, provided the predecessor is not later invalidated by review. If a review
of the predecessor requests changes that alter a consumed contract, downstream
work built on that contract must be reconciled.

This is the rule that permitted P5-002 to be implemented after P5-001 reached
`IMPLEMENTED_PENDING_REVIEW`. Both were subsequently returned VERIFIED. The
deviation is hereby **ratified** under this authorization. Phase 3's batch
exception (ADR-017) is unaffected and remains Phase-3-specific.

## 5. Execution state machine

```text
BACKLOG → READY → IMPLEMENTING → IMPLEMENTED_PENDING_REVIEW → REVIEWING
        → VERIFIED → DONE   |   REVIEWING → CHANGES_REQUESTED → IMPLEMENTING
```

`IMPLEMENTED_PENDING_REVIEW` means implementation, tests, evidence, internal
pre-review, and task-scoped commits are complete. It is not VERIFIED, DONE, HPO
acceptance, independent review, or phase closure.

## 6. Track model

```text
TRACK A — Phase 5 critical path
TRACK B — Phase 5 independent/leaf work
TRACK C — Phase 6 early-start allowlist
TRACK D — Phase 7 early-hardening allowlist
TRACK E — Explicitly allowlisted legacy debt
```

Scheduling preference: eligible P5 critical path → eligible P5 leaf → eligible
P6 early-start → eligible P7 early-hardening → allowlisted debt. This is a
preference, not a dependency.

## 7. Contract freeze rules

An early-start task may rely only on completed P1–P4 contracts, frozen Phase 5
contracts (ADR-022), independently adopted cross-phase decisions, and its own
task contract. It may not rely on unfinished Phase 5 implementation, speculative
Phase 6 translation semantics, speculative production architecture, or
undocumented behavior.

## 8. Provisional decisions and blockers

Provisional implementation decisions are recorded in `DECISIONS-PROVISIONAL.md`
and may not determine translation ownership/invalidation, revision/translation
relationships, datastore/storage architecture, tenancy, authorization
semantics, retention semantics, external API contract, or irreversible schema
assumptions. Blockers are recorded in `BLOCKERS.md`.

## 9. Browser governance

ADR-021 (DC-01) is the cross-phase browser verification ADR; ADR-020 is not
silently extended.

## 10. Closure boundaries

Phase 5/6/7 each close only with: all required tasks VERIFIED; the phase
integration gate VERIFIED; no unresolved BLOCKER/HIGH/MEDIUM; required real
provider/model and browser evidence; and an explicit HPO closure decision.
Early Phase 6/7 work never authorizes a phase closure.

## 11. Explicit non-actions

This record does not promote any task to READY, does not authorize Phase 6 or
Phase 7 generally, and does not change any frozen Phase 3/4 contract or Phase 5
decision.