# P2-004A1 — Upload Attempt Lease / Cleanup Safety Contract

## Status

BLOCKED

P2-004A1 is BLOCKED and deferred out of the current Phase 2 completion scope
under ADR-013. The staging claim
contract is implemented with a `staging_claims` table, `StagingClaim` model,
and integration with `MediaIngestionService` to create and manage claims.

## Authorization State

The earlier denial below is preserved as the historical authorization trail.
The Human Product Owner superseded it for this bounded remediation in ADR-012.
No P2-004A2, P2-004A3, P2-004B+, P2-005+, P2-007, or Phase 3 work is authorized.

## Ownership

Decision owner: Human Product Owner for whether abandoned-staging cleanup is
needed at the current phase gate and whether a new lease/claim architecture is
acceptable.

Implementation owner: Codex, under the Product Owner authorization recorded in
ADR-012.

Independent reviewer: Claude Code after implementation, if authorized.

## Current State Assessment

### Upload-attempt identity

The accepted P2-002B/ADR-009 identity is one opaque UUID
`upload_attempt_id` per intentional upload, scoped to the authenticated media
owner. A completion or response retry reuses that identifier. A separate
intentional duplicate receives a new identifier; the checksum is not an
idempotency key.

P2-003 persists `upload_attempt_id` only on the successful `MediaFile` record,
with a unique `(user_id, upload_attempt_id)` database boundary. It does not
persist an independent attempt record, active lease, expiry, heartbeat, or
terminal attempt state.

### Staging structure and lifecycle

`config/media.php` defines the private media disk, staging root
`media/.staging`, durable root `media`, and a 24-hour temporary-retention
policy. The current P2-003 service stages at:

`media/.staging/{owner_id}/{upload_attempt_id}/{uuid}.{extension}`

The normal flow is staging → validation/metadata derivation → checksum →
opaque durable promotion → `MediaFile` persistence → staging deletion.

### Existing synchronous cleanup and retry behavior

P2-003 deletes staging after successful persistence, after a recovered
duplicate-key race, and best-effort on validation, promotion, or persistence
failure. If a delete cannot be confirmed, the service logs an orphan candidate
for future handling. A committed `MediaFile` is not compensated.

The controller first looks up an existing record by authenticated owner and
attempt identifier. The service also performs the owner/attempt lookup and
recovers from a duplicate-key race. Existing tests independently cover
successful staging cleanup, failure compensation, same-attempt retry, separate
duplicate attempts, ownership isolation, and no Phase 3 side effects.

### What is not persisted

The repository has no `upload_attempts` table or equivalent, no lease status,
lease expiry, heartbeat, claim token, retry-active marker, cleanup claim, or
terminal state for an attempt. There is no command or scheduler for abandoned
staging. `tests/Feature/IngestionCompensationContractTest.php` records staging
cleanup race behavior as future-work TODOs.

### Why mtime-only cleanup is unsafe

A staging file can be older than 24 hours while an upload or retry still owns
the attempt. Possible races include:

- a process pauses after staging and resumes during cleanup;
- a request loses its response and the client retries while the old staging
  artifact remains;
- two same-attempt requests overlap before the successful owner/attempt row is
  committed;
- cleanup observes an old directory while promotion is reading its file; and
- cleanup runs during a storage or database failure whose compensation has not
  completed.

The current path identifies the owner and attempt but does not prove whether
that attempt is active. Deleting solely from filesystem mtime would contradict
ADR-009/P2-002B, which require an atomic claim or equivalent short-lived lease
before deletion and deferral while a retry is active.

## Proposed Objective

Define, only if abandoned-staging cleanup becomes necessary, the minimum
owner-scoped claim/lease contract that lets cleanup distinguish an expired
abandoned staging attempt from an active upload or valid retry without relying
only on filesystem mtime.

The contract must preserve the verified P2-003 upload path and must not itself
select or implement a database schema, filesystem lock protocol, command,
scheduler, or new upload state machine.

## Scope of This Planning Record

- document the current identity, staging, retry, and compensation boundaries;
- compare a database-backed lease, filesystem-backed claim/manifest, and
  deliberate deferral;
- identify the atomicity, expiry, ownership, retry, and cleanup questions that
  must be resolved before implementation;
- define reviewer-verifiable criteria for any future implementation;
- identify required migration/backfill and race tests if a database option is
  later selected; and
- preserve the current P2-003 contract and phase boundary.

## Explicit Non-Scope

This record does not:

- implement a lease, claim, heartbeat, manifest, lock, migration, or model;
- implement or register the staging cleanup command;
- delete staging or durable files;
- implement durable orphan reconciliation;
- change upload transport, controller, service, storage layout, or retry logic;
- add a persisted general upload-attempt state machine;
- add scheduler automation, queues, workers, Redis, or Horizon;
- add UI, media probing, FFprobe, FFmpeg, transcription, translation, or
  processing behavior;
- alter ownership, multi-tenancy, retention policy, or production policy; or
- authorize P2-004A2, P2-004B+, P2-005+, or Phase 3.

## Architecture Options

### Option A — Database-backed upload-attempt lease

Possible shape: an `upload_attempts` table or equivalent durable record keyed by
owner and attempt UUID, with a claim/lease status, expiry, staging identity,
and terminal outcome.

Pros:

- provides a durable cross-process coordination point;
- can enforce owner/attempt uniqueness and inspect active claims centrally;
- is testable with database transactions, unique constraints, and time control;
- can support cleanup and retry processes that do not share one local
  filesystem; and
- can make expiry and terminal outcomes explicit.

Cons and risks:

- requires a new migration, model/contract, and coordinated changes to the
  verified P2-003 upload path;
- risks becoming the general upload-attempt state machine explicitly excluded
  by ADR-009;
- requires clear transaction/lock semantics, renewal, clock, and cleanup rules;
- creates migration/backfill questions for existing successful `MediaFile` rows
  and abandoned staging artifacts; and
- expands Phase 2 schema and data-integrity surface before a demonstrated need.

P2-003 compatibility is possible only if the record remains a narrow claim/lease
boundary and does not replace the existing successful `MediaFile` owner/attempt
idempotency key. It is not currently approved.

### Option B — Filesystem lock or manifest under staging

Possible shape: an attempt manifest or lock created atomically under the staging
directory, containing the owner/attempt identity, claim/expiry data, and enough
information for cleanup to defer an active retry.

Pros:

- avoids a database migration;
- keeps temporary-attempt coordination near the staged bytes; and
- could remain invisible to the durable `MediaFile` schema.

Cons and risks:

- atomic create, rename, lock, timestamp, and delete semantics vary across the
  configured local and object-storage adapters;
- stale locks, process crashes, clock skew, and partial manifest writes require
  another recovery contract;
- the current P2-003 service does not read or refresh such a manifest;
- a local filesystem technique would leak machine-specific assumptions into a
  portable storage boundary; and
- malformed or attacker-controlled staging paths would increase deletion risk.

P2-003 compatibility is not established. A filesystem-only protocol cannot be
accepted without proving the required atomicity on every supported storage
boundary or explicitly narrowing the supported environment.

### Option C — No lease; defer cleanup until needed

Pros:

- makes no speculative architecture or schema change;
- preserves the independently verified P2-003 path exactly;
- avoids a dangerous mtime-only command;
- avoids migration/backfill, distributed-lock, scheduler, and operational
  policy decisions; and
- matches the current repository state, where cleanup is a future gate and no
  production-use requirement has authorized it.

Cons and risks:

- abandoned staging can accumulate while the cleanup feature is deferred;
- operators have no repository-provided cleanup command today; and
- a future production gate will still require a deliberate claim/lease decision
  and its race tests.

Compatibility with P2-003 is highest because no existing behavior changes. This
is the recommended current approach.

## Recommended Contract

### Current decision

Select Option C for the current repository state: do not adopt a lease/claim
mechanism or cleanup implementation yet. Keep the existing 24-hour retention
value as a documented eligibility policy only. Do not treat it as authorization
to delete files.

The repository’s current narrow identity remains `(user_id,
upload_attempt_id)` on successful `MediaFile` rows. No new lifecycle states,
lease records, heartbeat, or cleanup claims are introduced.

### Future decision gate

Before any cleanup command is authorized, the Product Owner must decide whether
the operational need justifies expanding the Phase 2 contract. The selected
future option must define, at minimum:

- owner and attempt identity;
- the authoritative claim/lease storage boundary;
- atomic acquisition and release;
- lease renewal/heartbeat, if required;
- clock and expiry rules;
- active upload and retry behavior;
- cleanup behavior when the lease is held, expired, missing, or ambiguous;
- failure/compensation interaction;
- logging and privacy expectations; and
- migration/backfill safety if durable state is introduced.

No option is silently selected by this artifact. If the future decision is
database-backed, it must remain a narrow lease/claim boundary and must not
become an unapproved general upload-attempt state machine.

## Future Implementation Acceptance Criteria

If a lease/claim implementation is later authorized, its task must prove:

1. An active upload cannot be cleaned while its claim/lease is valid.
2. A valid same-attempt retry cannot be broken or converted into a new attempt.
3. An expired abandoned attempt can be identified without filesystem mtime as
   the sole signal.
4. Owner and attempt identity are enforced without permitting cross-user claims.
5. Claim acquisition, refresh, expiry, release, and ambiguous outcomes are
   atomic and explicitly classified.
6. Cleanup never touches the durable `media/{uuid}/...` area or a committed
   `MediaFile`.
7. The configured private disk remains authoritative; the public disk is never
   used.
8. Failure and compensation do not create duplicate records or silently mint a
   new attempt identifier.
9. Existing P2-003 upload, retry, compensation, ownership, storage, checksum,
   and no-side-effect behavior remains unchanged.
10. No cleanup command, scheduler, queue, worker, processing, or Phase 3 side
    effect is included unless separately authorized.

## Testing Requirements for a Future Implementation

The future implementation task must include focused tests for:

- claim acquisition and release;
- duplicate same-attempt claim behavior;
- lease expiration and renewal, if renewal is selected;
- preservation of an active lease;
- identification of an expired abandoned staging attempt;
- retry after an interrupted or failed upload;
- cleanup/active-upload race safety;
- cross-user isolation;
- promotion/persistence compensation interaction; and
- repeated cleanup eligibility evaluation without duplicate claims.

If a database-backed option is selected, tests must also cover migration
creation, rollback, unique-key behavior, existing-row/backfill safety, and
transaction/lock failure. No migration or implementation test is added by this
planning record.

The existing P2-003 focused and full regression suites remain required after any
future change. Tests must not rely only on old mtimes or a single-process happy
path to claim race safety.

## Risks and Regression Concerns

- A lease introduced before it is needed could over-engineer Phase 2 and alter
  the verified upload path.
- An incorrect expiry or clock rule could delete an active upload or valid
  retry.
- A database option could silently become a general attempt state machine,
  contradicting ADR-009.
- A filesystem option could work on local Herd storage but fail on the
  configured S3-compatible boundary.
- Changing the P2-003 service to add claims could reopen independently verified
  behavior and require a new review cycle.
- The current dirty worktree contains unrelated governance, Phase 1, P2-003,
  migration, test, and configuration changes; no broad staging or cleanup is
  permitted.
- No machine-specific Herd/PHP/Nginx behavior may become part of the contract.

## Implementation Isolation Plan

No implementation is authorized. If a future decision selects a lease option:

1. Publish a separate authorized task naming the selected option and affected
   boundaries.
2. Use an agreed baseline containing the accepted P2-003 artifacts in a
   separate branch or worktree where possible.
3. Leave the current dirty worktree untouched and classify every changed file
   before staging.
4. Isolate any migration, model/service coordination, and tests to that task.
5. Do not create the cleanup command in the lease-contract task.
6. Run focused and full regression checks, `git diff --check`, and independent
   review before closure.

## Review Requirements

This planning artifact requires no implementation review. Any future lease
implementation must be independently reviewed for:

- conformance to the selected contract and ADR-009;
- race, expiry, retry, ownership, and compensation behavior;
- migration/backfill safety if applicable;
- private-storage and durable-media isolation;
- regression against P2-003; and
- absence of cleanup-command, P2-004B+, P2-005+, and Phase 3 scope.

## Phase Boundary

P2-004A1 is limited to a future Phase 2 staging-cleanup safety decision. It
introduces no FFprobe, FFmpeg, media processing, transcription,
faster-whisper, queues, Redis, Horizon, workers, or transcript behavior.

## Recommended Authorization Decision

`DEFER_STAGING_CLEANUP_UNTIL_NEEDED`

The current repository has no production-use requirement or authorized
operational gate requiring abandoned-staging cleanup now. P2-003 already
handles normal success and compensation cleanup, while the remaining cleanup
need is explicitly future work. Deferring avoids both an unsafe mtime-only
command and a speculative database/filesystem lease architecture.

If a future production or operational gate makes cleanup necessary, reopen the
decision with a bounded claim/lease task, select the authoritative mechanism,
and require the race and migration tests listed above before authorizing the
cleanup command.

## Escalation Record

The independent remediation-cycle-2 re-review returned CHANGES_REQUESTED.
This is the third consecutive CHANGES_REQUESTED cycle. Under
`.ai/guidelines/orchestration-policy.md`, this task is escalated to BLOCKED;
another autonomous repair cycle is not authorized.

Blocking issue: The cleanup/ingestion concurrency safety contract is not yet
backed by an explicit and verified concurrency mechanism for the repository's
actual SQLite database engine. `lockForUpdate()` does not provide row-level
locking on SQLite; the observed protection depends on broader SQLite/PDO
locking behavior that is not explicitly defined, pinned, or tested with
genuine independent connections/processes.

Independent review evidence and previous remediation history remain preserved
in `reviews/P2-003-P2-004A-P2-004A1-P2-005-remediation-cycle-2-re-review.md`.

## Next Action

Historical pre-authorization recommendations are superseded by ADR-012. The
current task is BLOCKED after the third consecutive CHANGES_REQUESTED cycle;
another autonomous repair cycle is not authorized. Under ADR-013, its
automated cleanup/claim work is deferred out of the current Phase 2 completion
scope. The canonical lifecycle has no DEFERRED state, so BLOCKED is retained;
this task is not VERIFIED or DONE.
