# P2-004A — Staging Cleanup Command

## Status

BLOCKED

P2-004A is BLOCKED and deferred out of the current Phase 2 completion scope
under ADR-013. The staging cleanup
command `media:cleanup-staging` is implemented with `--dry-run` support, a
transactional row-level deletion lease, and final claim/media rechecks.

## Ownership

Implementation Owner: Codex, under the Product Owner authorization recorded in
ADR-012.

Reviewer: Claude Code after implementation, if authorized.

## Authorized Phase

Phase 2 continuation under ADR-012. P2-003 is DONE after the cycle-2
independent VERIFIED regression re-review. P2-004A
concerns only out-of-band cleanup of abandoned temporary
staging artifacts created by the verified P2-003 upload workflow.

## Current State Assessment

### Current staging path

`config/media.php` defines:

- media disk: `config('media.storage_disk')`, defaulting to `local`;
- staging root: `media/.staging`;
- durable root: `media`; and
- temporary retention: 24 hours.

`MediaIngestionService::stage()` creates one staging file at:

`media/.staging/{owner_id}/{upload_attempt_id}/{uuid}.{extension}`

The owner identifier is the authenticated owner’s database ID, the attempt
identifier is a UUID, and the filename is application-generated. The configured
private disk is the only storage boundary used by `MediaFile::storage()`.

### Synchronous cleanup already covered by P2-003

P2-003 already removes staging synchronously:

- after a committed `MediaFile` is persisted;
- after an ambiguous duplicate-key retry returns the existing record;
- after promotion or persistence failure when the staging delete succeeds; and
- after validation/ingestion failure when the staging delete succeeds.

P2-003 independently verifies successful ingestion and compensation-path
staging cleanup. P2-004A must not duplicate or redesign those paths.

### Abandoned scenarios that can remain

Out-of-band staging can remain after a process crash, request termination,
machine interruption, timeout, storage-adapter failure during cleanup, or any
other boundary where the normal request cannot finish its `deleteStaging()`
step. Such artifacts have no durable `MediaFile` by the normal failed-ingestion
contract, but their absence cannot be assumed solely from the directory name.

### Existing command, scheduler, and tests

The repository has only the default `inspire` command in `routes/console.php`.
No staging-cleanup command, scheduler registration, or cleanup service exists.
`tests/Feature/IngestionCompensationContractTest.php` contains TODO inventory
items for future staging-cleanup race behavior, while the P2-003 feature tests
cover synchronous request-path cleanup rather than out-of-band expiry cleanup.

### Justification

A manual cleanup command is justified as a bounded operational follow-up because
the accepted 24-hour staging-retention contract exists and its implementation
was explicitly deferred. It is not required to reopen or complete P2-003.

## Proposed Objective

Provide a safe, manually invoked Artisan command to detect and remove expired,
abandoned upload-attempt staging directories under the configured private media
disk, while preserving active/fresh staging, all durable media, all database
rows, and all paths outside the configured staging root.

The command must operate only after the cleanup/retry claim-or-lease boundary is
defined and coordinated with the upload path. An age-only delete is not an
acceptable implementation of the existing contract.

## Recommended Authorization Decision

`SPLIT_P2-004A_BEFORE_AUTHORIZATION`

P2-004A must be split into two explicitly bounded concerns before implementation:

1. a claim/lease coordination contract and implementation at the shared staging
   and retry boundary; and
2. the manual command that enumerates and deletes only eligible claimed
   staging-attempt directories.

ADR-009 and P2-002B require cleanup to atomically claim an artifact or acquire
   an equivalent short-lived lease, defer while a retry owns the claim, and
   preserve the same attempt identifier if cleanup wins. The current P2-003
`MediaIngestionService` does not expose or check such a claim/lease. A command
that deletes solely because an mtime is older than 24 hours would therefore be
unsafe and would contradict the accepted contract.

Do not authorize the command portion until the coordination boundary is either
implemented in a separately authorized prerequisite or explicitly approved as
part of a revised bounded task. If the Product Owner decides out-of-band cleanup
is not needed before a later production gate, the alternative is
`CLOSE_P2-004A_AS_UNNEEDED`; that decision is not made here.

## Scope After the Required Split

The command portion may include only the following after its coordination
prerequisite is accepted:

- an Artisan command named `media:cleanup-staging`;
- a `--dry-run` option that performs discovery and reporting without mutation;
- use of `MediaFile::storage()` so the configured private-disk guard is applied;
- traversal limited to the configured `media.staging_directory` root;
- recognition of the existing `{owner_id}/{upload_attempt_id}` attempt layout;
- the configured 24-hour retention threshold, without an ad hoc CLI threshold
  override;
- safety validation, claim/lease acquisition, deletion, and summary reporting;
- warnings for skipped, malformed, unavailable, or failed candidates;
- focused command tests and the existing P2-003 regression suite; and
- the task/review/state records required when the authorized task is completed.

The exact command class/registration surface must follow the repository’s
existing Laravel console loading convention. The current repository has no
custom command class to extend and no scheduler registration to reuse.

## Candidate Eligibility Rules

The implementation-ready successor must use these repository-aligned rules:

1. Resolve the disk through `MediaFile::storage()` and never directly use the
   public disk or a machine-specific filesystem path.
2. Normalize and validate all relative paths before acting. Reject absolute
   paths, traversal segments, paths outside the configured staging root, and
   paths that could resolve into the durable `media/` root.
3. Consider only attempt directories immediately below the staging root with
   the expected `{owner_id}/{UUID upload_attempt_id}` structure.
4. Treat unexpected children, symlinks, unreadable metadata, or malformed
   attempt paths as skipped candidates. Do not recursively delete a candidate
   containing an unexpected structure.
5. Determine age from the most recent reliably available modification time of
   the candidate’s files/directories. A candidate is expired only when its last
   observed activity is at or before `now - temporary_retention_hours`.
6. If required age or claim information cannot be verified, preserve the
   candidate and report why it was skipped.
7. Acquire the approved claim/lease before deletion. The command must defer
   when an active retry owns the claim and must release the claim safely on
   success or failure.
8. Delete only the claimed attempt directory and its staging contents. Never
   delete the staging root, durable media, a `MediaFile` row, or an owner
   directory merely because it is empty.
9. A committed owner/attempt record and its durable path must never be deleted
   by this command. Any read-only database check required by the final contract
   must be explicit; the command must not perform row deletion or orphan
   adoption.

## Dry-Run, Output, and Error Contract

`--dry-run` is required for the command portion. It must apply the same path,
age, and claim-eligibility checks as deletion but must not delete, rename,
claim permanently, update the database, or alter durable media.

Normal output must report counts for discovered, eligible, deleted, skipped,
deferred, and failed candidates. Per-candidate output should identify the
relative staging path and a concise reason. It must not print media contents,
credentials, or machine-specific absolute paths.

The command should continue evaluating unrelated candidates after an individual
delete failure, report the failure, and return a non-success exit status when
any candidate could not be safely processed or the configured disk could not be
validated. A missing staging root is an empty/no-op result when the configured
private disk is valid; an inaccessible or invalid disk configuration is a
reported safety failure, not permission to fall back to another disk.

The command is manually invoked only in this task. Scheduler automation,
deployment hooks, queue dispatch, and recurring production execution are out
of scope and require separate authorization.

## Architecture and Contract

### Entry point

The planned entry point is the manual Artisan command:

`php artisan media:cleanup-staging [--dry-run]`

No HTTP route, Livewire action, upload-controller change, or UI is introduced.

### Storage boundary

The command uses the configured media disk through the existing private-storage
boundary. It does not use `Storage::disk('public')`, the default disk by
assumption, Herd paths, or direct local filesystem APIs that would bypass the
configured adapter.

### Race behavior

The command must not infer abandonment from age alone. It must use the accepted
claim/lease mechanism. Cleanup defers while a retry is active. If cleanup wins,
the retry remains tied to the same `upload_attempt_id` and must re-stage rather
than silently mint a new attempt or create a second logical upload.

The claim/lease mechanism is deliberately not invented in this task artifact;
its absence is the reason for the required split before authorization.

### Idempotency

Running the command repeatedly is safe. A successfully deleted candidate is
absent on the next run. A fresh, malformed, deferred, or failed candidate is
preserved or reported for later handling. The command never creates a
`MediaFile`, changes a `MediaStatus`, changes a checksum, or creates a new
attempt identifier.

## Explicit Non-Scope

P2-004A must not include:

- durable private-media orphan reconciliation, which belongs to P2-004B;
- deletion of any path outside `config('media.staging_directory')`;
- deletion or mutation of durable media, `MediaFile` rows, or database records;
- changes to upload transport, staging format, promotion, checksum, or retry
  behavior unless required by the separately authorized claim/lease prerequisite;
- scheduler automation, queue dispatch, worker processes, Redis, Horizon, or
  recurring production execution;
- FFprobe, FFmpeg, media probing, conversion, transcription, faster-whisper,
  translation, or processing behavior;
- UI, Livewire, controller, Media Detail, folder, rename, or delete behavior;
- a general persisted upload-attempt state machine;
- retention-policy redesign, privacy/legal policy, or production deployment;
- P2-004B, P2-004C, P2-004D, P2-005, or Phase 3; and
- machine-specific PHP, Nginx, Herd, or storage configuration changes.

## Acceptance Criteria for the Authorized Command Portion

The successor task must satisfy all applicable criteria below:

1. The command is manually invokable as `media:cleanup-staging` and supports
   the required `--dry-run` behavior.
2. Only paths strictly under the configured staging root are eligible.
3. The command uses the configured private media disk and rejects a public or
   invalid disk configuration before deletion.
4. Only expected owner/attempt staging directories with verifiable metadata are
   considered for cleanup.
5. Fresh staging directories are preserved.
6. Expired abandoned staging directories are deleted only after successful
   claim/lease acquisition.
7. Active retry claims defer cleanup and do not lose or duplicate an attempt.
8. Malformed, unexpected, unreadable, or traversal-like paths are skipped and
   reported without recursive deletion.
9. Durable `media/{uuid}/...` objects are never enumerated as deletion targets.
10. No `MediaFile` row is deleted or synthesized, and valid database-backed
    media remains unchanged.
11. Empty or missing staging roots are handled safely and repeatedly.
12. A partial delete failure is reported without deleting unrelated candidates;
    the command remains safe to rerun.
13. Dry-run produces no storage, database, claim, or durable-media mutation.
14. Existing P2-003 upload, retry, compensation, ownership, private-storage,
    checksum, and no-side-effect tests remain passing.
15. No Phase 3 or later behavior is introduced.

## Testing Requirements

The authorized command portion requires focused tests for:

- dry-run discovery with no mutation;
- an expired expected staging attempt being deleted;
- a fresh staging attempt being preserved;
- exact retention-boundary behavior;
- a durable `media/{uuid}/...` object being preserved;
- malformed owner/attempt paths and unexpected children being skipped safely;
- path traversal and absolute-path attempts being rejected;
- empty and missing staging roots;
- repeated command execution being idempotent;
- active claim/retry deferral;
- successful claim release after deletion; and
- partial delete failure reporting and preservation of unrelated candidates,
  where the storage adapter can reproduce it reliably.

The claim/lease coordination prerequisite must carry the race test that proves
an active P2-003 retry cannot be deleted while it is staging or promoting. A
command-only test that merely sets an old mtime is insufficient.

The implementation owner must also run the existing focused P2-003 tests and
the full repository checks appropriate to changed surfaces. Independent review
must reproduce the relevant commands and inspect the diff for storage-boundary,
path-safety, and phase-boundary compliance.

## Risks and Regression Concerns

- A path-prefix or normalization error could delete durable media or data
  outside the staging root.
- An mtime-only implementation could delete an active upload because the
  current P2-003 path has no shared claim/lease primitive.
- A malformed or symlink-like tree could turn recursive deletion into a broad
  delete operation.
- A disk misconfiguration could cause the command to operate on the wrong
  storage boundary unless `MediaFile::storage()` remains authoritative.
- Storage adapters may expose different metadata/error behavior; unverifiable
  candidates must be preserved rather than guessed eligible.
- Adding a scheduler or machine-specific configuration would expand the task
  into deployment/operations work.
- The dirty worktree contains unrelated governance, Phase 1, P2-003, test,
  migration, and configuration changes; broad staging would corrupt isolation.
- Cleanup logic must not leak into durable orphan reconciliation or Phase 3.

## Implementation-Isolation Plan

No implementation is authorized by this artifact. If the prerequisite and the
command are later authorized:

1. Establish an agreed baseline containing the accepted P2-003 artifacts in a
   separate branch or worktree where possible.
2. Leave the current dirty worktree untouched; do not reset, stash, move,
   overwrite, clean, stage, or commit its unrelated changes.
3. Implement only the approved claim/lease prerequisite and/or command surface,
   its focused tests, and the task/state records required for closure.
4. Do not modify migrations, runtime configuration, upload UI, Phase 3 files,
   or durable orphan-reconciliation surfaces.
5. Before staging, inspect status and classify every changed path.
6. Run `git diff --check`, inspect the staged diff, and verify that no durable
   media, database rows, public storage, or unrelated dirty-worktree files are
   included.
7. Move to REVIEW only after self-verification. Claude Code must independently
   review the result before Work can close it as DONE.

## Documentation and State Handling

This planning artifact does not update `plan.md`, `CURRENT_STATE.md`,
`architecture.md`, `DECISIONS.md`, or `DECISION_QUEUE.md`. Existing P2-003
records and historical review passages remain unchanged.

If the claim/lease prerequisite changes an accepted architecture boundary, that
decision must be recorded before implementation. If it remains within ADR-009
and P2-002B, the implementation task must cross-reference those records rather
than restating or replacing them.

## Review and Closure

If authorized, the task follows the repository State-to-Action Contract:

`BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE`

The implementation owner may move it to REVIEW after verification. Claude Code
must independently verify acceptance criteria, path safety, storage isolation,
race behavior, regression results, and phase boundaries. Work closes it only
after VERIFIED.

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

Historical pre-authorization recommendations above are superseded by ADR-012.
The current task is BLOCKED after the third consecutive CHANGES_REQUESTED
cycle; another autonomous repair cycle is not authorized. Under ADR-013, its
automated cleanup work is deferred out of the current Phase 2 completion scope.
The canonical lifecycle has no DEFERRED state, so BLOCKED is retained; this
task is not VERIFIED or DONE.
