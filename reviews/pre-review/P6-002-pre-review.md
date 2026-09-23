# P6-002 — Builder Pre-Review Handoff

Date: 2026-09-23
Task: `tasks/P6-002-revision-persistence-version-history.md`
Status: `IMPLEMENTED_PENDING_REVIEW` (not VERIFIED, not DONE)
Authority: `DECISION-P6-001-CLOSURE-001`; `DECISION-P6-002-READY-001`;
`DECISION-PHASE6-AUTHORIZATION-001`; `DECISION-PHASE6-OWNER-DECISIONS-001`;
ADR-025; frozen `PHASE6-EDITING-DOMAIN-CONTRACT.md`
Reviewer: Claude Code (fresh independent review required)

## Why this task exists

P6-001 established the frozen Phase 6 editing domain contract (immutable machine
source + editable revision layer; transcription-scoped monotonic versions;
durable branch history; deterministic redo). P6-002 makes that contract durable:
it implements the persistence layer, the `RevisionRepository` contract, the
active pointer, machine-source materialization, and concurrency enforcement.

P6-001 is DONE (`DECISION-P6-001-CLOSURE-001`, independently VERIFIED).
P6-002 was promoted READY and authorized by `DECISION-P6-002-READY-001`.
This implementation consumes the frozen P6-001 semantics and redefines none.

## Deliverables

Schema (additive migrations only; no existing column dropped or semantically
changed):

- `database/migrations/2026_09_23_000001_create_transcript_revisions_table.php`
- `database/migrations/2026_09_23_000002_create_transcript_revision_segments_table.php`
- `database/migrations/2026_09_23_000003_add_active_revision_id_to_transcriptions_table.php`

Models / factories:

- `app/Models/TranscriptRevisionModel.php` (+ `TranscriptRevisionFactory`)
- `app/Models/TranscriptRevisionSegment.php` (+ `TranscriptRevisionSegmentFactory`)
- `app/Models/Transcription.php` — `active_revision_id`, `revisions()`,
  `activeRevision()`

Persistence / application:

- `app/Editing/Persistence/EloquentRevisionRepository.php` — the P6-001
  `RevisionRepository` implementation.
- `app/Editing/Persistence/MachineSourceMaterializer.php` — machine → initial
  revision.
- `app/Editing/RevisionService.php` — authorization-fenced application service.
- `app/Editing/RedoUnavailableException.php`.
- `app/Providers/AppServiceProvider.php` — binds `RevisionRepository` →
  `EloquentRevisionRepository`.

Tests (31 feature tests, 135 assertions):

- `tests/Feature/Editing/RevisionSchemaTest.php`
- `tests/Feature/Editing/RevisionRepositoryPersistenceTest.php`
- `tests/Feature/Editing/RevisionOwnershipTest.php`
- `tests/Feature/Editing/MachineSourceImmutabilityTest.php`
- `tests/Feature/Editing/RevisionConcurrencyTest.php`
- `tests/Feature/Editing/RevisionAppendRaceTest.php`
- `tests/Feature/Editing/RevisionMigrationRollbackTest.php`
- `tests/Support/EditingPersistenceFixtures.php`
- `app/Console/Commands/RevisionAppendRaceWorker.php` (hidden, testing-only)

## Requested fresh-review focus

1. **Schema correctness and scope.** Exactly the P6-001 §10 additive shape;
   `(transcription_id, version)` unique; `(revision_id, segment_key)` and
   `(revision_id, position)` unique; nullable active pointer; no machine table
   mutation; no translation-staleness/ split-merge persistence.
2. **Persistence round-trip.** Text/timing/language/identity/position/version/
   parent/revision id survive persist → reload losslessly.
3. **Version allocation.** Transcription-scoped monotonic, independent of
   ancestry; undo-then-branch never reuses a version; non-monotonic append is a
   domain conflict.
4. **Concurrency.** Active-pointer CAS; stale base rejected with no partial
   write; read-max-then-insert race handled; DB unique constraint is the durable
   backstop; DB uniqueness/locking conflicts translated to
   `RevisionConflictException` (never a raw `QueryException` as the ordinary
   outcome); genuine two-process evidence.
5. **Undo/redo/branch.** Durable history; old descendants preserved; redo target
   unique-child only; redo unavailable at a branch point; active pointer follows
   the new branch.
6. **Ownership.** Owner/admin can mutate; non-owner cannot mutate or read;
   cross-transcription isolation.
7. **Machine-source immutability.** No machine segment write; revision timing
   never mutates machine timing; no translation data moved into revision tables.
8. **Transaction failure.** A failure during segment persistence rolls back the
   whole append (no orphan revision, active pointer unchanged).
9. **Frozen-semantics discipline.** No P6-001 semantic is redefined; no frozen
   Phase 3/4/5 contract changed; no P6-003/P6-004/P6-005/P6-007/P6-008/P6-009 or
   Phase 7 work started.

## Concurrency strategy (what was implemented)

- `append`/`activate` run inside `DB::transaction(..., 5)` with
  `lockForUpdate()` on the transcription row (row locking where the datastore
  supports it) plus an active-pointer CAS.
- `append` re-reads the transcription `MAX(version)` inside the transaction and
  rejects a non-monotonic version with
  `RevisionConflictException::nonMonotonicVersion()`.
- The `(transcription_id, version)` unique constraint is the durable backstop;
  `Illuminate\Database\UniqueConstraintViolationException` is translated into
  `RevisionConflictException`.
- `append` enforces branch ancestry (`parent_revision_id` equals the expected
  active revision).

## Concurrency evidence and honest limitations

- **Contract-level / application proof.** `RevisionConcurrencyTest` deterministically
  simulates a read-max-then-insert race (two writers allocate the same next
  version, one wins, the loser is a domain conflict) and a stale-writer race;
  `RevisionRepositoryPersistenceTest` covers the stale-base and rollback paths.
- **SQLite behavior.** `RevisionAppendRaceTest` spawns two genuine OS processes
  with independent SQLite connections to one database file and a filesystem
  rendezvous; exactly one persists version 2, the loser surfaces
  `RevisionConflictException`, and the final `(transcription_id, version)` set is
  unique. SQLite serializes writers at the file level; it cannot demonstrate
  production row-locking.
- **Production datastore requirement (not claimed as locally proven).** A
  production RDBMS (PostgreSQL/MySQL) must additionally provide row-level locking
  (`lockForUpdate`) and transactional uniqueness so concurrent writers are
  serialized; the unique constraint remains the portable backstop. This is a
  P7 productionization concern and is not claimed as gated by SQLite evidence.

## Quality / evidence

- `tests/Feature/Editing` → 31 passed, 135 assertions.
- `tests/Unit/Editing` → 48 passed, 186 assertions.
- Full PHP suite → 740 tests, 739 passed, 1 skipped, 0 failures.
- Pint clean; PHPStan 0 (project scope).
- Full-suite two-process race re-run 4× independently, all passing.
- Browser evidence: not required (no browser behavior changed).

## Not done (correctly)

- Not VERIFIED; not DONE. No self-verification.
- No P6-003/P6-004/P6-005/P6-007/P6-008/P6-009 implementation started.
- No split/merge UI or translation-staleness persistence.
- No Phase 7 work; no frozen Phase 3/4/5 contract changed.
