# P6-002 — Independent Review

Date: 2026-09-23
Task: `tasks/P6-002-revision-persistence-version-history.md`
Reviewer: Claude Code (fresh independent review; no reliance on the
implementer's batch report or the builder's pre-review handoff)
Authority for review scope: `PHASE6-EDITING-DOMAIN-CONTRACT.md` (frozen,
`DECISION-P6-001-CLOSURE-001`); `tasks/P6-002-revision-persistence-version-history.md`;
`DECISION-P6-002-READY-001`

## Verdict

**CHANGES_REQUESTED**

One MEDIUM finding. No BLOCKER or HIGH findings. Persistence faithfully
implements the frozen P6-001 semantics for version allocation, the
active-pointer CAS, transaction atomicity, ownership isolation, and machine
immutability, and this was independently reproduced (tests, migrations, a
genuine two-process race, Pint, PHPStan). The one finding is a gap between a
documented invariant (in both `RevisionService`'s own docblock and the frozen
P6-001 contract) and what the code actually enforces at the `activate()`
boundary — not a persistence-safety or concurrency defect.

## Findings

### MEDIUM — `activate()`/`undo()` do not verify the target is a strict ancestor; any revision in the transcription can be "undone" to

**File:** [app/Editing/Persistence/EloquentRevisionRepository.php:150-180](app/Editing/Persistence/EloquentRevisionRepository.php:150),
[app/Editing/RevisionService.php:100-113](app/Editing/RevisionService.php:100),
[tests/Support/InMemoryRevisionRepository.php:128-141](tests/Support/InMemoryRevisionRepository.php:128)

`PHASE6-EDITING-DOMAIN-CONTRACT.md` §2 states: "Undo activates a strict
ancestor. Redo activates the deterministic redo target." `RevisionService::undo()`'s
own docblock claims: "Undo: compare-and-set the active pointer onto an
existing ancestor." `redo()` correctly derives its target from
`redoTargetFor()` (the unique-child rule), so redo cannot be misused. Undo has
no equivalent guard.

`EloquentRevisionRepository::activate()` checks only: (1) CAS —
`$expectedActiveRevisionId` matches the current active pointer, and (2) the
target revision exists **and belongs to the same transcription**. It never
checks that `$revisionId` is actually an ancestor (or any relation at all) of
the current/expected active revision. `RevisionService::undo()` passes its
caller-supplied `$targetRevisionId` straight through with no ancestry check
either. The in-memory reference implementation
(`tests/Support/InMemoryRevisionRepository::activate()`) has the identical
gap, so this is a contract-level omission, not an Eloquent-specific slip.

Concretely: given transcription-owned revisions v1 → v2 → v3 (linear) plus a
sibling branch v2b (created after an undo-then-edit from v1), a caller can do
`RevisionService::undo($user, $transcription, 'v2b-id')` while v3 is active.
v2b is not an ancestor of v3 — it is a sibling branch — yet the call succeeds:
CAS passes (expected == actual == v3), existence/ownership passes (v2b
belongs to the transcription), and the pointer moves to v2b. Nothing rejects
this as an invalid "undo." The persisted history stays correct (append-only,
versions still unique) — this is not a data-integrity or concurrency bug —
but the *operation* silently becomes an arbitrary jump/branch-select rather
than the ancestor-only undo the contract and the docblock both promise.

This did not surface in the test suite because every test drives `undo`/`redo`
through legitimate ancestor/child sequences only; no adversarial "undo to a
non-ancestor sibling" case exists in
`tests/Feature/Editing/RevisionRepositoryPersistenceTest.php` or
`RevisionOwnershipTest.php`.

Two readings are possible, and the task's own text creates the ambiguity:
`PHASE6-EDITING-DOMAIN-CONTRACT.md` §2 also says "explicit historical revision
selection may remain possible later (P6-008), but it is not automatic redo" —
which could be read as licensing a general `activate-to-any-revision`
primitive at the repository level, with ancestor-enforcement deferred to a
`undo()` caller in a later phase. But P6-002 already ships a `RevisionService::undo()`
method today, named and documented as ancestor-constrained undo, that has no
such enforcement — so either the docblock overstates what the code does, or
the method is missing a check the frozen contract requires. Recommend one of:
(a) add an ancestor check in `RevisionService::undo()` (e.g. walk
`parentRevisionId` from the expected active revision, or repository-side via
a new `isAncestorOf()`/`ancestorsOf()` primitive) and throw a domain exception
for a non-ancestor target, or (b) rename/re-scope the current method to make
explicit that it is general revision activation (matching P6-008's "explicit
historical revision selection"), and defer a real ancestor-constrained
`undo()` to whichever task actually wires it to a UI. Either is a small,
narrowly-scoped change; this does not require re-opening the frozen P6-001
contract text itself, only reconciling `RevisionService`'s implementation
with what it already claims to do.

## 1. Frozen P6-001 semantics — consumed, not redefined

Confirmed by inspection and by the tests below: revision identity/version
independence from ancestry, `parent_revision_id` ancestry, transcription-
scoped monotonic version, one active-revision pointer with `null` = machine
source, durable branches, active-pointer CAS, stale-base conflict, redo
unique-child rule, segment identity/position, and timing invariants are all
implemented as specified in `PHASE6-EDITING-DOMAIN-CONTRACT.md`. No
persistence shortcut changes domain meaning. The one deviation is the MEDIUM
finding above (undo ancestor enforcement), which is an omission, not a
redefinition.

## 2. Schema and migrations

`transcript_revisions`: uuid PK, `transcription_id` FK with `cascadeOnDelete`,
`unsignedInteger version`, nullable `parent_revision_id`, `created_by` FK to
`users` with `cascadeOnDelete`, timestamps, unique `(transcription_id,
version)`, indexed `parent_revision_id`. Matches the named §10 shape exactly.

`transcript_revision_segments`: bigint PK, `revision_id` FK with
`cascadeOnDelete`, `segment_key`, `unsignedInteger position`,
`decimal(12,3)` timings, `text`, `language string(16)` default `und`, unique
`(revision_id, segment_key)`, unique `(revision_id, position)`. Matches §10.

`transcriptions.active_revision_id`: nullable uuid FK to
`transcript_revisions` with `nullOnDelete` — correct: if a revision were ever
removed (it never is by application code, but the FK action is defensive),
the pointer degrades to "machine source authoritative" rather than an
orphaned reference or a blocked delete.

Independently reproduced: `RevisionSchemaTest` (8 assertions covering table/
column existence, both unique constraints via forced `QueryException`, cross-
transcription version reuse being legal, cascade-delete of revisions and
segments, and that no revision-layer column leaked onto
`transcription_segments`/`transcriptions`) — passed. `RevisionMigrationRollbackTest`
independently reproduced against a dedicated SQLite file: migrate up, assert
all three additive artifacts exist, `migrate:rollback --step 3`, assert all
three are gone — passed, and it does not touch the primary test database.

No existing Phase 3/4/5 column was found dropped or redefined; `full_text`
and machine `transcription_segments` columns are untouched (confirmed by
`RevisionSchemaTest`'s explicit column-absence assertions on the machine
tables and by `MachineSourceImmutabilityTest`, see §8 below).

## 3. Version-allocation concurrency

`nextVersionFor()` is a plain `MAX(version) + 1` query — deliberately a
"read-max-then-insert" primitive, not itself race-safe. The safety comes from
where and how `append()` uses it:

1. `append()` opens `DB::transaction(..., 5)`.
2. It `lockForUpdate()`s the transcription row. On MySQL/Postgres this is a
   real row lock: a second concurrent `append()` for the *same* transcription
   blocks at this `SELECT ... FOR UPDATE` until the first transaction commits
   or rolls back, then observes the first writer's committed state. This
   correctly serializes all writers for one transcription (version allocation
   is transcription-scoped, so cross-transcription concurrency is
   unaffected and correctly unserialized).
3. After acquiring the lock, `append()` re-reads `active_revision_id` (the CAS
   check) and re-computes `nextVersionFor()` (the monotonicity check) from
   inside the now-serialized section — so on a real row-locking engine, the
   second writer's re-check always sees the first writer's fresh state and
   deterministically fails either the CAS (stale base) or the monotonicity
   check (if it somehow retained a stale base match), never a raw unique
   violation.
4. On SQLite, `lockForUpdate()` compiles to a no-op (no row-level locking
   exists), so two connections can both pass steps 1–3 with a stale, pre-lock
   view before either has written anything. The actual serialization point on
   SQLite is the database-level write lock acquired at the first real write
   statement (the `INSERT`/`UPDATE`) inside the transaction: whichever
   connection reaches that write first holds SQLite's file lock; the second
   blocks (up to `busy_timeout`) and then either retries the whole closure
   (if Laravel's deadlock detector classifies the "database is locked"
   condition as retryable — the standard Illuminate `DetectsLostConnections`/
   deadlock-string matching does include SQLite's message) or eventually
   surfaces a `QueryException`/`UniqueConstraintViolationException`. The
   `catch (UniqueConstraintViolationException)` wraps the *entire*
   `DB::transaction()` call, not just the insert, so this holds regardless of
   whether Laravel's internal retry fires first.
5. The `(transcription_id, version)` unique constraint is the actual
   backstop for both engines: whichever writer's version conflicts hits the
   constraint, and `EloquentRevisionRepository::append()`'s catch block
   translates `UniqueConstraintViolationException` into
   `RevisionConflictException::nonMonotonicVersion()` — a raw DB exception is
   never the ordinary outcome of this race.

**Independently reproduced, not just inspected:** `RevisionAppendRaceTest`
spawns two genuine separate OS processes (`Symfony\Component\Process`, real
`php artisan` invocations, each with its own SQLite connection to a shared
file-backed database, `PRAGMA busy_timeout = 15000`), synchronized through a
filesystem rendezvous (ready-files, then a shared "go" file, then a second
barrier immediately before both call `append()`) so the race window is
genuinely contested rather than sequential. I ran it independently three
times (not just once, given timing-based tests can be flaky): all three runs
produced exactly one `success` (version 2) and exactly one `conflict`, with
`(transcription_id, version)` left unique and the active pointer pointing at
the actual winner. `RevisionConcurrencyTest` additionally exercises the
same-process "both allocate version 2, then replay the loser's stale
allocation after an undo" sequence, and a direct unique-constraint-collision
assertion at the DB layer. Both passed on independent re-run.

The implementation does not claim stronger SQLite guarantees than it
demonstrates — the class docblock and the race test's own comment explicitly
say SQLite "serializes writers at the file level and cannot demonstrate
production row-locking," and correctly attribute the production guarantee to
the unique constraint plus row locking on an engine that supports it. This is
an accurate, non-overstated claim.

## 4. Active-pointer CAS

`append()`/`activate()` both lock the transcription row (real lock on
MySQL/Postgres; SQLite as discussed in §3), require
`$current === $expectedActiveRevisionId`, and independently require (for
`append`) `$revision->parentRevisionId === $expectedActiveRevisionId` —
ancestry and CAS are checked as two separate conditions, matching the
contract's explicit statement that they are "separate invariants." A stale
CAS throws before any row is touched (see §5). Probed and independently
reproduced: two concurrent edits from the same base (`RevisionConcurrencyTest`,
`RevisionAppendRaceTest`), a stale writer replaying after another revision
becomes active (`RevisionConcurrencyTest`, `RevisionRepositoryPersistenceTest`),
branching from an older active revision / multiple historical branches
(`RevisionRepositoryPersistenceTest::'preserves multiple historical branches...'`),
and pointer movement across undo/redo/branch (`RevisionRepositoryPersistenceTest::'supports
deterministic redo...'`). "Reset to machine source" is not implemented in
P6-002 (there is no code path that sets `active_revision_id` back to `null`
after a non-null value) — this is consistent with scope (P6-002 does not
introduce a "reset" user action) and is not a defect.

`activate()`'s one gap — it does not check that `$revisionId` bears any
ancestry relation to the CAS-verified expected pointer — is the MEDIUM
finding above; it does not compromise CAS correctness (the pointer still only
moves under a verified compare-and-set) but it does let the pointer move to
an unrelated revision under the `undo()` name.

## 5. Transaction atomicity / rollback

`append()`'s only two writes are `insertRevision()` (revision row, then each
segment row, in that order) followed by `$transcription->active_revision_id
= ...; $transcription->save()`, all inside one `DB::transaction`. Any
exception at any point (CAS failure before any write; a thrown
`InvalidArgumentException` for ancestry/duplicate-id before any write;
non-monotonic version before any write; a mid-insert failure) rolls back the
whole transaction. Independently reproduced: `RevisionRepositoryPersistenceTest::'rolls
back the whole append when segment persistence fails'` forces a `RuntimeException`
on the second segment's `creating` event (after the revision row and first
segment have already been written to the transaction, pre-commit) and asserts
zero orphan revisions, the active pointer unchanged, and exactly the
pre-existing segment count after rollback — passed on independent re-run. I
did not find a code path where the revision row could be committed without
its segments, or the active pointer moved without the revision row existing
(the pointer update is the last statement in the same transaction as the
insert). CAS-failure and uniqueness-failure rollback are exercised by
`RevisionConcurrencyTest`/`RevisionRepositoryPersistenceTest` above, all
inside the same transaction, so no partial state was observed.

## 6. Conflict/error translation

- Stale base (append or activate) → `RevisionConflictException::staleBase()`.
- Non-monotonic version (pre-insert check or unique-constraint collision) →
  `RevisionConflictException::nonMonotonicVersion()`.
- Unknown/foreign revision (activate) → `InvalidArgumentException` (not a
  `RevisionConflictException` — this is a programmer/caller error, not a
  concurrency conflict, and `RevisionService` only ever passes revision ids
  it already validated belong to the transcription, so this path is a
  defensive check, not a normal user-facing conflict; the distinction is
  reasonable).
- Redo unavailable → `RedoUnavailableException` (service layer), distinct
  from `RevisionConflictException`, which is correct since "no unique child"
  is not a CAS conflict.
- I did not find a path where an ordinary domain conflict leaks a raw
  `QueryException`: the only two DB-exception surfaces are the
  `UniqueConstraintViolationException` catch in `append()` (translated) and
  whatever `DB::transaction` itself might rethrow after exhausting retries,
  which is caught by the same surrounding `try`/`catch` since it wraps the
  entire `DB::transaction()` call.
- Programming/corruption errors are not mislabeled as domain conflicts: an
  unknown transcription id throws `InvalidArgumentException` (not silently
  swallowed or reclassified as `RevisionConflictException`); a genuinely
  unexpected exception during segment persistence (§5's forced
  `RuntimeException`) propagates as-is rather than being caught and
  reinterpreted as a domain conflict.

## 7. Ownership and isolation

`RevisionService` gates every mutation behind `TranscriptionPolicy::update`
and every read behind `view` via `Gate::forUser($user)->authorize(...)`
(throws `AuthorizationException`, not swallowed). Independently reproduced:
`RevisionOwnershipTest` — owner can materialize/edit/undo/redo; admin can
mutate a transcription they don't own; a non-owner is rejected on
`materializeInitial`/`edit`/`undo`/`redo` *and* on the read paths
(`active`/`history`/`redoTarget`); history/active state is isolated per
transcription across two independent transcriptions — all passed on
independent re-run.

Repository-level constraints complement rather than duplicate the
service-level authorization: the repository never checks `User`/ownership at
all (correctly — `EloquentRevisionRepository` has no authorization
awareness), but it does independently enforce that `activate()`'s
`$revisionId` belongs to the given `$transcriptionId`
(`RevisionRepositoryPersistenceTest::'rejects activation of an unknown
revision or one from another transcription'`, independently reproduced,
passed). This is the correct division: authorization is a service-layer
concern; cross-transcription data isolation is a persistence-layer
invariant. A user cannot reference another user's revision as parent/base/
active target — this is enforced structurally (CAS ties `parentRevisionId`
to the caller's own transcription's active pointer; `edit()` in
`RevisionService` independently checks `$base->transcriptionId === $transcription->getKey()`
before deriving) rather than by an explicit ownership check on the base
revision itself, which is sufficient since the base id space and the CAS
token are both transcription-scoped.

## 8. Machine-source immutability

`MachineSourceMaterializer` only reads `TranscriptionSegment` rows into
`MachineSegmentSnapshot` value objects; I found no write to
`transaction_segments`/`transcriptions`' machine columns anywhere in
`app/Editing/Persistence/`. Independently reproduced:
`MachineSourceImmutabilityTest` — machine segment text/timing/language
unchanged after materialize+edit+undo (before/after snapshot comparison),
machine `start_seconds` unchanged after a revision timing edit, no
`Translation` row created or moved into revision tables, and machine segment
row count and `updated_at` timestamps byte-for-byte unchanged after
materialize+edit — all passed on independent re-run. `RevisionSchemaTest`
additionally confirms no revision-layer column (`revision_id`) exists on
`transcription_segments` and no `version` column exists on `transcriptions`
itself (only `active_revision_id`).

## 9. Branch and redo persistence

Independently reproduced via `RevisionRepositoryPersistenceTest`: linear
revisions (materialize → edit → edit, versions 1/2/3); undo (CAS to v2);
redo before branching (`redoTargetFor()` returns v3, `redo()` reactivates it,
matches `redoTargetFor`'s doc — "unique child"); undo → new branch (edit
again from v2 produces v4 with `parentRevisionId = v2`, version 4, not
colliding with the abandoned v3); old descendant (v3) preserved and
independently `find()`-able after the branch; branch-point redo unavailable
— after branching from v2 a second time (v2 now has two children, v3 and
v4) and undoing back to v2, `redoTargetFor()` returns `null` (deterministic,
not a guess among siblings) and `childrenOf(v2)` returns both children;
multiple historical branches — three separate branches from the same v1
(`branchA`/`branchB`/`branchC`, versions 2/3/4) all preserved, all
`childrenOf(v1)`, full history count 4. All match the graph-vs-user-path
distinction in `PHASE6-EDITING-DOMAIN-CONTRACT.md` §2. The one caveat is the
MEDIUM finding: `redoTargetFor()`-driven redo is correctly constrained, but
`undo()`/`activate()` accept any revision id in the transcription, not just
strict ancestors, so a caller could technically use `undo()` to reach a
sibling branch rather than a true ancestor.

## 10. Segment persistence invariants

Enforced in the domain constructors — `RevisionSegmentData::__construct()`
(position non-negative, `TimingInvariants::assertValidTiming`) and
`TranscriptRevision::__construct()` → `TimingInvariants::assertValidSequence()`
(duplicate identity, duplicate/non-contiguous position) — which run
unconditionally on every construction, including
`EloquentRevisionRepository::toDomain()` when reloading rows from the
database. This means invariants cannot be bypassed through the repository's
public API in either direction (write or read-back), since both paths
construct the same guarded value objects. I did not find a code path
(factory, repository, or service) that constructs `RevisionSegmentData`/
`TranscriptRevision` outside these guarded constructors. Overlaps and
zero-length segments are explicitly legal per §5 and independently
reproduced round-tripping through the real database
(`RevisionRepositoryPersistenceTest::'round-trips multilingual Unicode text,
overlap, and zero-length timings'`) — passed. Out-of-time-order but
position-valid segments are implicitly covered by the same test (segment
timings `0.0–2.0`, `1.0–1.0`, `5.0–9.5` are non-monotonic and overlapping,
and persist correctly ordered by `position`).

## 11. SQLite vs. production datastore

**Proven locally:** unique-constraint enforcement (`(transcription_id,
version)`, `(revision_id, segment_key)`, `(revision_id, position)`);
transaction rollback/atomicity; CAS logic under both same-process and
genuine two-OS-process contention; conflict translation under a real
(file-level) SQLite race; migration up/down; cascade deletes; domain-object
invariant enforcement on write and reload.

**Contractually required of production persistence:** row-level locking on
the transcription row during `append()`/`activate()` (the `lockForUpdate()`
call is already present and will take effect automatically on
MySQL/Postgres — no code change needed, only a different datastore); the
unique constraints (portable, already schema-enforced); the same
CAS-then-insert transaction shape.

**Deferred to Phase 7 / production verification:** actual row-lock
contention behavior under MySQL/Postgres (this review and the SQLite race
test cannot observe true row-level blocking, only file-level serialization);
deadlock-retry behavior under `DB::transaction(..., 5)` against a real
RDBMS's deadlock detector; behavior under sustained/high-concurrency load
beyond a two-writer race.

No overclaim was found: the code, its docblocks, and the task's own
implementation notes are consistent about what SQLite proves versus what is
contractually required versus what remains to be verified against a
production RDBMS. No design was found that *cannot* be made safe on a
production datastore without redefining P6-001/P6-002 — `lockForUpdate()`
degrading gracefully to a no-op on SQLite while the unique constraint still
backstops correctness is exactly the documented, intended behavior.

## 12. Regression / scope discipline

No editing UI, no split/merge implementation, no translation-staleness
persistence (`Translation` model/table untouched — confirmed in §8), no
other Phase 6 task's scope, no Phase 7 implementation, and no machine
transcript redesign were found in this diff. `git status` at review time
shows only P6-002-scoped migrations/models/persistence/service files, tests,
and the expected governance-document updates; no unrelated files were
touched.

## Verification summary

**Independently reproduced (commands run by this reviewer, not taken from
the batch report):**
- `php artisan test --compact --filter=Editing` → 79/79 passed, 321
  assertions.
- `php artisan test --compact` (full suite) → 740 tests, 739 passed, 1
  skipped, 0 failures — matches the implementer's claimed figure exactly.
- `vendor/bin/pint --test` → clean.
- `vendor/bin/phpstan analyse` → 0 errors.
- `RevisionAppendRaceTest` (genuine two-OS-process race) run 3 additional
  times independently → passed all 3 times, no flakiness observed.
- Migration rollback (`RevisionMigrationRollbackTest`) — passed.

**Inspected only (not independently re-executed beyond the suite run above):**
- Exact MySQL/Postgres row-locking behavior (no such datastore available in
  this environment; reasoned from Laravel/`lockForUpdate` semantics and the
  code's own, non-overstated documentation of this limitation).

**Not reproduced:**
- Production-RDBMS concurrency (explicitly out of scope for this phase per
  §11 above).

## Answers to the review brief's closing questions

1. **P6-002 verdict:** CHANGES_REQUESTED (one MEDIUM; no BLOCKER/HIGH).
2. **Severity-labelled findings:** one MEDIUM (§ above); no BLOCKER, HIGH,
   LOW, or INFO findings.
3. **Does persistence faithfully preserve frozen P6-001 semantics?** Yes, with
   the one MEDIUM exception (undo ancestor enforcement is documented but not
   implemented).
4. **Is version allocation safe enough for the current phase?** Yes — CAS +
   transcription-row locking + unique-constraint backstop + conflict
   translation, independently verified including under a genuine two-process
   race, run three times with no flakiness.
5. **Is active-pointer CAS correct?** Yes for the CAS mechanism itself
   (compare-and-set against the true current pointer, verified under
   contention). The one gap is that `activate()` does not additionally verify
   an ancestor relationship, which is a scope/enforcement gap in `undo()`,
   not a CAS correctness defect.
6. **Is transaction rollback behavior correct?** Yes, independently
   reproduced including a forced mid-transaction failure with no orphan rows.
7. **Is ownership/isolation sound?** Yes, independently reproduced for
   owner/admin/non-owner on both mutation and read paths, and for
   cross-transcription isolation.
8. **SQLite vs. production-RDBMS limitations:** Accurately documented and not
   overclaimed; production row-locking is deferred to Phase 7 verification as
   it should be.
9. **Exact evidence independently reproduced:** see "Verification summary"
   above.
10. **May P6-002 proceed to HPO closure?** Recommend addressing the MEDIUM
    finding first (either add ancestor enforcement to `undo()`, or
    re-scope/rename the method to make clear it is a general activation
    primitive) and returning for a short follow-up verification pass, rather
    than closing as-is — the fix is small and localized, but it is a real gap
    between documented and implemented behavior in code that downstream UI
    tasks (P6-003/P6-006) will call directly.
11. **Can the next Phase 6 task be considered after that closure?** Not yet —
    this task is not being marked VERIFIED by this review. Once the MEDIUM
    finding is resolved and re-verified, there is no other finding in this
    review that would block promoting a subsequent Phase 6 task.

P6-002 is not marked DONE by this review. No later task is promoted or
implemented.
