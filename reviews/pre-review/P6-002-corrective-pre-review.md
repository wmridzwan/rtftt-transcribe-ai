# P6-002 — Corrective Pre-Review Handoff (strict-ancestor undo)

Date: 2026-09-23
Task: `tasks/P6-002-revision-persistence-version-history.md`
Status: `IMPLEMENTED_PENDING_REVIEW` (not VERIFIED, not DONE)
Trigger: `reviews/P6-002-independent-review.md` — `CHANGES_REQUESTED`, exactly
one MEDIUM finding (undo did not enforce strict ancestry)
Authority: `DECISION-P6-001-CLOSURE-001`; `DECISION-P6-002-READY-001`;
`DECISION-PHASE6-AUTHORIZATION-001`; ADR-025; frozen
`PHASE6-EDITING-DOMAIN-CONTRACT.md`
Reviewer: Claude Code (fresh independent re-review required)

## Scope discipline

Narrowly scoped corrective cycle only:

- no persistence-architecture redesign; no new revision tables, allocator, or
  concurrency mechanism;
- redo left unchanged (only re-asserted by regression test);
- no P6-003+/P6-004/P6-005/P6-007/P6-008/P6-009 implementation;
- no UI, routes/controllers, split/merge, or translation-invalidation
  persistence;
- no later Phase 6/7 work.

## 1. Exact ancestry rule implemented

The frozen P6-001 contract is preserved verbatim:

> Undo activates a strict ancestor of the current active revision.

`RevisionService::undo()` now succeeds only when **all** hold:

1. authorization `update` (`TranscriptionPolicy`) — owner or admin;
2. the target revision exists and belongs to the **same transcription**;
3. the target is a **strict ancestor** of the **current active revision**,
   defined as: repeatedly following `parent_revision_id` from the current
   active revision eventually reaches the target;
4. the expected active revision equals the actual active revision (CAS); a
   stale expected token is rejected as `RevisionConflictException::staleBase()`
   before any ancestry work.

Rejected targets:

| Target | Result |
|---|---|
| current revision itself | `UndoUnavailableException` |
| sibling branch (same parent) | `UndoUnavailableException` |
| cousin branch (sibling of an ancestor) | `UndoUnavailableException` |
| descendant (child/grandchild of active) | `UndoUnavailableException` |
| old descendant from an abandoned branch | `UndoUnavailableException` |
| unrelated revision, same transcription | `UndoUnavailableException` |
| revision from another transcription | `InvalidArgumentException` |
| unknown revision id | `InvalidArgumentException` |
| non-owner/foreign user | `AuthorizationException` |
| stale expected active pointer | `RevisionConflictException` |
| machine source active (`active_revision_id = null`) | `UndoUnavailableException` |

Machine-source semantics: `null` is the ancestry root and is **not** a
reachable revision target. P6-002 has no undo-to-machine-source operation (the
`undo()`/`activate()` signatures require a revision id; the independent review
§4 already recorded that reset-to-machine-source is not implemented and is not
a defect). This is preserved explicitly and tested: undo while the machine
source is active throws `UndoUnavailableException`; the initial revision
(`parent_revision_id = null`) remains a valid undo target because the walk
reaches it.

Rejected undo calls are mutation-free: the active pointer is unchanged, the
revision/segment rows are neither mutated nor deleted, and no raw
infrastructure exception is surfaced.

## 2. Where enforcement lives and why

**Enforcement lives in `RevisionService::undo()`** (application boundary),
using existing `RevisionRepository::find()` to walk `parent_revision_id`. A
private `isStrictAncestor()` helper keeps the graph logic single-sourced.

Rationale (task §5):

- **Cannot be bypassed through normal application service usage.** `undo()` is
  the only undo entry point; it is authorization-fenced and now
  ancestry-fenced. The repository is not an application service.
- **`RevisionRepository` stays persistence-neutral.** `activate()` is the
  active-pointer CAS primitive. It deliberately does not encode graph
  semantics because it is also the mechanism `redo()` uses to move forward onto
  the deterministic unique child — an ancestor-only `activate()` would break
  redo. `activate()` still enforces its persistence-layer invariants: CAS +
  target belongs to the transcription.
- **No generic history-navigation API** was added; no `ancestorsOf()`/
  `isAncestorOf()` surface. The walk reuses `find()`.
- **No duplicated graph logic**: one private walk, exercised identically by
  both repository backends.

`redo()` was not touched: it still derives its target from
`redoTargetFor()` (unique-child rule; `null` at a branch point) and activates
it via the same CAS primitive.

## 3. Files changed (corrective)

- `app/Editing/RevisionService.php` — `undo()` enforces same-transcription +
  strict-ancestor + CAS ordering; private `isStrictAncestor()`; accurate
  docblock.
- `app/Editing/UndoUnavailableException.php` — **new** domain navigation
  exception (`machineSourceActive`, `targetNotAnAncestor`), parallel to
  `RedoUnavailableException`.
- `app/Editing/RevisionRepository.php` — `activate()` docblock states it is the
  persistence-neutral CAS primitive and points to the service for undo
  ancestry; throws documented.
- `tests/Support/InMemoryRevisionRepository.php` — `activate()` now rejects a
  revision from another transcription (parity with Eloquent; the double is not
  more permissive than production persistence).
- `tests/Unit/Editing/RevisionRepositoryContractTest.php` — one added
  cross-transcription activation parity test.
- `tests/Feature/Editing/RevisionUndoAncestryTest.php` — **new** shared
  contract test.

No migrations, models, factories, or `EloquentRevisionRepository` logic
changed.

## 4. Tests added

`tests/Feature/Editing/RevisionUndoAncestryTest.php` — a Pest dataset runs the
same 15 scenarios against **both** `EloquentRevisionRepository` and the
in-memory reference implementation, through the same `RevisionService`:

Valid (pointer moves as expected):

- direct parent; grandparent; deep ancestor (via the same branched graph);
- undo to an ancestor on the active branch after a sibling branch exists;
- explicit expected pointer equal to the active revision.

Invalid (rejected, active pointer and history unchanged):

- current revision itself;
- sibling revision;
- cousin revision;
- descendant revision;
- old descendant from an abandoned branch;
- unrelated revision from the same transcription;
- revision from another transcription;
- unknown revision id;
- non-owner user;
- machine source active;
- stale expected active pointer (`RevisionConflictException`).

Also verified: rejected undo leaves the active pointer unchanged and
`historyFor()` byte-equivalent (no rows mutated/deleted); redo behavior is
unchanged around a valid undo (undo → unique-child redo → redo unavailable at
the tip → undo → unique-child redo again).

Backend matrix: 15 scenarios × 2 backends = **30 tests / 78 assertions**.

Repository-contract parity: `tests/Unit/Editing/RevisionRepositoryContractTest.php`
gains one test asserting the in-memory reference `activate()` rejects a
revision from another transcription, matching `EloquentRevisionRepository`
(Eloquent's mirrored behavior remains covered by
`RevisionRepositoryPersistenceTest`).

## 5. Verification results (corrective cycle)

Commands run by the implementation owner:

| Gate | Result |
|---|---|
| `php artisan test --compact tests/Feature/Editing/RevisionUndoAncestryTest.php` | 30 passed / 78 assertions |
| `php artisan test --compact --filter=Editing` | 110 passed / 402 assertions |
| `php artisan test --compact` (full suite) | 771 tests, 770 passed, 1 skipped, 0 failures |
| `vendor/bin/pint --dirty` then `composer lint:check` | fixed 1 docblock; check passed |
| `composer types:check` (PHPStan) | 0 errors |
| `RevisionAppendRaceTest` (genuine 2-OS-process race) re-run ×2 | both passed (1 test / 10 assertions each) |
| `RevisionConcurrencyTest` + `RevisionMigrationRollbackTest` + `RevisionSchemaTest` | 11 passed / 49 assertions |
| `tests/Unit/Editing` + persistence/ownership/machine-immutability | 67 passed / 262 assertions |

## 6. Version allocation / CAS / rollback intact

No change was made to version allocation, the append path, the active-pointer
CAS, the transaction boundary, or the unique-constraint backstop. The appended
`undo()` enforcement is an **additional precondition checked before** the
existing `activate()` CAS, which remains the authoritative write; the CAS is
re-checked inside the repository transaction under `lockForUpdate`.

Re-run evidence (full suite + targeted gates above) confirms:

- transcription-scoped monotonic versions and `(transcription_id, version)`
  uniqueness across undo-then-branch remain intact;
- sort/append race still yields exactly one persisted version and one domain
  conflict (two-process test re-run twice);
- stale base / stale activation still throws `RevisionConflictException` with
  no partial write;
- segment-persistence failure still rolls back the whole append (no orphan
  rows, pointer unchanged);
- migrations up/down still clean;
- redo unique-child semantics unchanged.

## 7. Residual findings / honest limitations

- The repository-level `activate()` remains callable with any revision in the
  same transcription (it is the shared primitive used by redo and reserved for
  future explicit historical selection, P6-008). Strict-ancestor enforcement is
  an **application-boundary** guarantee: normal service usage cannot bypass it,
  but direct repository use is intentionally unconstrained. This is documented
  on the interface. If the fresh reviewer requires the primitive itself to be
  ancestor-constrained, that would force a redo-specific forward method and is a
  larger design change than this corrective cycle's scope — flagged here rather
  than silently assumed.
- Undo-to-machine-source is not part of the P6-002 contract (consistent with
  the prior review's §4); no new history semantic was invented.
- Nothing new observed. No BLOCKER/HIGH/LOW/INFO findings introduced.

## 8. Fresh independent re-review handoff

Requested:

1. Re-verify the MEDIUM finding is resolved: `RevisionService::undo()`
   rejects self/sibling/cousin/descendant/unrelated/cross-transcription/
   machine-source targets and leaves state intact.
2. Confirm `redo()` is unchanged and still unique-child only.
3. Confirm version allocation, CAS, transaction rollback, migration, race, and
   ownership evidence still holds (full suite green as above).
4. Confirm the in-memory reference implementation is not more permissive than
   Eloquent (cross-transcription `activate()` parity).
5. Confirm scope discipline (no later Phase 6/7, UI, split/merge, or
   translation-invalidation persistence).

P6-002 remains `IMPLEMENTED_PENDING_REVIEW`. The implementation owner has not
self-verified and has not promoted or started any later task.
