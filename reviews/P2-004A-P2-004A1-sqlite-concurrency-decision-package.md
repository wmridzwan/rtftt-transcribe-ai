# P2-004A / P2-004A1 — SQLite Concurrency Escalation and Decision Package

## Purpose and decision boundary

This package records the governance response to the completed independent
remediation-cycle-2 re-review. It is for Human Product Owner architectural
approval. It is not an implementation plan, does not authorize an architecture
change, and does not authorize another autonomous remediation cycle.

## Decision outcome

Approved by the Human Product Owner on 2026-09-13: Option D applies to the
current Phase 2 gate. Automated abandoned-staging cleanup is deferred out of
the current Phase 2 completion scope. Option B is the preferred future starting
resolution class, subject to separate authorization and the verification gate
below. No implementation option is authorized by this outcome.

## Accepted review result

- P2-003 regression re-review: VERIFIED; closed as DONE.
- P2-005: VERIFIED; closed as DONE.
- P2-004A: CHANGES_REQUESTED; escalated to BLOCKED after the third consecutive
  CHANGES_REQUESTED cycle.
- P2-004A1: CHANGES_REQUESTED; escalated to BLOCKED after the third consecutive
  CHANGES_REQUESTED cycle.

## Observed facts

1. The repository's configured/default database is SQLite, including the test
   database configuration.
2. SQLite's Laravel grammar removes the `lockForUpdate()` locking clause, so
   the current call does not establish a row-level `FOR UPDATE` lock.
3. The observed protection is broader SQLite transaction/database locking and
   PDO/runtime behavior. The repository does not currently define or pin that
   behavior as its concurrency contract.
4. The current tests do not use two genuine independent database connections
   or processes to exercise the cleanup-versus-ingestion interleaving.
5. Cleanup file I/O occurs inside the transaction, so SQLite's broad write
   lock can delay unrelated application writes.
6. Empirical absence of silent data loss is not equivalent to a verified,
   repository-controlled concurrency guarantee.

## Repository constraints

- The P2-002B/P2-003 identity and retry contract must remain owner-scoped and
  same-attempt idempotent.
- The invariant is: cleanup must never delete staging for an active upload or
  a valid same-attempt retry; it may remove only an identified abandoned
  attempt, without cross-user claims or durable-media deletion.
- Contention must have an explicit outcome; silent deletion, corruption, or
  creation of a new attempt identity is unacceptable.
- P2-004A and P2-004A1 are BLOCKED under the three-cycle policy.
- P2-007 and Phase 3 remain unauthorized. Closure of P2-003/P2-005 does not
  complete Phase 2.
- No implementation, migration, runtime change, or test change is authorized
  by this package.

## Resolution options

### A — Retain SQLite and explicitly design around SQLite semantics

Invariant: an active claim remains protected for the entire cleanup decision
and no competing ingestion can be silently deleted.

Guarantee: define the SQLite transaction mode, busy-timeout policy, ordering,
and lock acquisition behavior explicitly; keep the critical section free of
file I/O; classify lock contention as wait/retry or a controlled retryable
failure. This must be proven with separate real connections/processes.

SQLite compatibility: native, but dependent on deliberate SQLite mode and
deployment configuration rather than `FOR UPDATE` semantics.

Laravel/transactions: compatible with Laravel transactions and SQLite-specific
connection configuration, provided transaction and retry behavior are explicit.

Failure/retry: a busy/lock result must wait, retry with bounded policy, or
return a documented retryable failure; it must never be treated as success.

P2-002B/P2-003 impact: potentially low if the claim identity and upload path
remain unchanged, but ingestion must translate contention consistently with the
existing retry contract.

Testing: requires genuine independent connections/processes, controlled
interleaving, lock-timeout coverage, and proof that cleanup and ingestion cannot
both commit an unsafe outcome.

Operational complexity: medium; SQLite deployment mode, timeout, filesystem,
and single-writer characteristics must be supported and monitored.

Deployment/migration: no engine migration, but every supported environment
must match the documented SQLite contract. Existing rows need no migration if
the claim schema remains compatible.

Regression risk: medium, especially around lock duration and unrelated writes.

Governance: an ADR amendment or new ADR is required because the actual
concurrency contract differs from the current implied row-lock claim.

### B — Retain SQLite but redesign the cleanup/claim protocol

Invariant: cleanup can delete only an attempt whose claim is atomically proven
 abandoned under a protocol that does not rely on unsupported row-level locks.

Guarantee: use an SQLite-supported compare-and-set/claim protocol with explicit
state transitions and short transactions, or an equivalent protocol whose
atomicity is defined for SQLite. Keep filesystem deletion outside the decision
transaction and make every ambiguous result retryable/reconcilable.

SQLite compatibility: potentially strong, if the selected statements and
transaction behavior are proven against the supported SQLite version/mode.

Laravel/transactions: compatible, but may require query-builder/raw SQL
boundaries that are documented and isolated from generic `lockForUpdate()`.

Failure/retry: failed claim, busy database, crash, and ambiguous commit each
have a bounded retry/reconciliation outcome; deletion is never assumed from an
uncertain claim.

P2-002B/P2-003 impact: medium; the existing upload-attempt identity remains
authoritative, but claim lifecycle and contention handling touch ingestion.

Testing: requires independent connections/processes and crash/interleaving
tests for claim acquisition, refresh, expiry, cleanup, and retry.

Operational complexity: medium-to-high; protocol observability and orphan
reconciliation are required.

Deployment/migration: likely schema/protocol migration work for existing
`staging_claims` rows and any abandoned artifacts; rollout ordering matters.

Regression risk: medium-to-high because it changes the claim/cleanup boundary.

Governance: a new or amended ADR and an explicitly authorized implementation
task are required.

### C — Change production to an engine with the assumed row-lock semantics

Invariant: cleanup and ingestion serialize on the owner/attempt claim so an
active or valid retry cannot be deleted.

Guarantee: use a production-supported engine such as PostgreSQL or MySQL with
documented row-lock and transaction semantics, and align the implementation,
timeouts, and isolation level with that engine.

SQLite compatibility: development/test SQLite would not prove production
behavior; a supported test matrix or production-like engine is required.

Laravel/transactions: strong framework compatibility, but engine-specific
behavior, isolation, deadlock, and retry rules must be explicit.

Failure/retry: bounded deadlock/lock-timeout retry or controlled retryable
failure; ambiguous commits require attempt-identity recovery.

P2-002B/P2-003 impact: high at the system boundary; upload logic can preserve
its contracts, but connection configuration, test infrastructure, deployment,
and operational assumptions change.

Testing: use independent connections/processes against the actual production
engine and retain SQLite tests only for SQLite-specific behavior that remains
supported.

Operational complexity: high; managed database operations, backups, monitoring,
credentials, capacity, and failure recovery are added.

Deployment/migration: high; production provisioning, schema migration,
backfill/validation, rollback strategy, and environment configuration are
required.

Regression risk: high due to the infrastructure and rollout surface.

Governance: new/amended ADR, explicit production architecture approval, and
separate deployment/migration authorization are required.

### D — Defer cleanup/claim execution until an operational need exists

Invariant: while cleanup is not authorized, no automated cleanup operation may
delete staging; the existing synchronous P2-003 compensation behavior remains
the only active cleanup path.

Guarantee: remove the unresolved cleanup race from the active execution scope
by keeping P2-004A/P2-004A1 BLOCKED and not running the command or scheduler.

SQLite/Laravel/transactions: no new concurrency contract is introduced.

Failure/retry: existing P2-002B/P2-003 behavior remains in force; abandoned
staging accumulates until a separately approved solution exists.

P2-002B/P2-003 impact: lowest; no upload-path change.

Testing: no cleanup verification gate is claimed; any future implementation
must meet the independent-concurrency gate below.

Operational complexity: low now, with storage accumulation as the accepted
interim cost.

Deployment/migration: none now; future work remains possible.

Regression risk: lowest, but it does not solve future cleanup requirements.

Governance: no ADR change is required to defer; an ADR is required before a
future cleanup mechanism is selected.

## Recommendation

Observed facts do not establish that SQLite's incidental broad locking is a
portable or repository-controlled implementation of the required invariant.
The current architecture does not otherwise dictate a production database
engine, and no production cleanup requirement is recorded in the accepted
Phase 2 contracts.

Recommendation for human approval: select D for the current gate, preserving
P2-004A/P2-004A1 as BLOCKED, while treating B as the preferred resolution class
if cleanup becomes operationally necessary. B keeps the current engine but
requires a genuinely SQLite-defined protocol and independent concurrency proof.
Select C only if product/operations require production concurrency guarantees
that justify the cost of changing the production database contract. A is viable
only if its SQLite-specific locking and availability trade-offs are explicitly
accepted and proven; the current `lockForUpdate()` claim is not sufficient.

This recommendation is not an architectural decision. Human architectural
approval is required before selecting A, B, C, or any other implementation
resolution, and an ADR update/new ADR is required for the selected contract.

## Verification gate before VERIFIED

Future remediation must prove, on the selected supported database engine:

1. Two real independent database connections or processes coordinate a
   cleanup-versus-ingestion interleaving while the relevant transaction is
   genuinely open.
2. The test demonstrates the race window, including the cleanup observation,
   competing claim refresh/upsert, and commit/rollback ordering.
3. The selected mechanism prevents deletion of active staging and valid
   same-attempt retry state, with no silent corruption, duplicate durable
   object, duplicate committed record, or cross-user claim.
4. Contention behavior is asserted as a contract: the loser waits, retries, or
   receives a controlled retryable failure, exactly as the approved design
   specifies.
5. Busy timeout, deadlock/lock failure, crash/interruption, ambiguous commit,
   and cleanup-file-I/O failure are classified and recoverable.
6. The focused tests, full regression suite, static analysis, and applicable
   deployment/configuration checks pass, followed by independent review.

A sequential simulation alone is insufficient. P2-004A and P2-004A1 cannot
return to VERIFIED until this gate is satisfied and the selected architecture
has been approved.
