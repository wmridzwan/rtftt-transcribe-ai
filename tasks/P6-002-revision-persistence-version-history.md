# P6-002 — Revision Persistence / Version History

## Status

DONE — closed 2026-09-23 by the Human Product Owner
(`DECISION-P6-002-CLOSURE-001`) on the fresh independent corrective re-review
verdict **VERIFIED** (no BLOCKER/HIGH/MEDIUM/LOW/INFO remaining). Contract
authored 2026-09-23 under `DECISION-PHASE6-AUTHORIZATION-001` and the adopted D6
decisions (`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025); reconciled against the
corrected P6-001 version-allocation / redo semantics; P6-001 independently
VERIFIED and closed DONE; HPO promoted P6-002 to **READY** and authorized
implementation (`DECISION-P6-002-READY-001`, 2026-09-23); implemented and tested
2026-09-23; corrective cycle resolved the original MEDIUM finding.

- P6-001 semantics are frozen (`PHASE6-EDITING-DOMAIN-CONTRACT.md`); P6-002
  consumes them without redefining them.
- Implementation: migrations + models + `EloquentRevisionRepository` +
  `MachineSourceMaterializer` + authorization-fenced `RevisionService`.
- Tests: `tests/Feature/Editing/` (incl. a genuine two-process append race and the
  shared strict-ancestor undo contract test) and `tests/Unit/Editing/`.
- Quality: full PHP suite 771 tests / 770 passed / 1 skipped / 0 failures; Pint
  clean; PHPStan 0.
- Historical artifacts preserved: `reviews/P6-002-independent-review.md` (original
  CHANGES_REQUESTED), `reviews/pre-review/P6-002-pre-review.md`,
  `reviews/pre-review/P6-002-corrective-pre-review.md`.

### Corrective cycle (2026-09-23) — MEDIUM `CHANGES_REQUESTED` resolved

Independent review `reviews/P6-002-independent-review.md` returned exactly one
MEDIUM finding: `undo()` did not enforce strict ancestry (any same-transcription
revision could be jumped to under the `undo()` name). Corrective (no
architecture redesign, no later Phase 6/7 work):

- `RevisionService::undo()` now enforces the frozen P6-001 strict-ancestor
  contract: the target must belong to the same transcription and be reached by
  repeatedly following `parent_revision_id` from the current active revision;
  self/siblings/cousins/descendants/unrelated/cross-transcription targets are
  rejected; the active pointer is unchanged.
- New `App\Editing\UndoUnavailableException` (navigation outcome, parallel to
  `RedoUnavailableException`); stale expected pointer still surfaces as
  `RevisionConflictException`.
- `RevisionRepository::activate()` documented as the persistence-neutral CAS
  pointer primitive (does not encode graph semantics); `redo()` unchanged.
- `InMemoryRevisionRepository::activate()` aligned with Eloquent
  cross-transcription rejection so the double is not more permissive.
- Added `tests/Feature/Editing/RevisionUndoAncestryTest.php`: 15 shared
  scenarios × 2 backends (Eloquent + in-memory through the same service), 30
  tests / 78 assertions.

Closed `VERIFIED → DONE` by the HPO (`DECISION-P6-002-CLOSURE-001`). The
reviewer-owned artifact `reviews/P6-002-corrective-independent-re-review.md` was
not present in the working tree at reconciliation time; it is the expected
durable evidence for the accepted VERIFIED verdict and must be retained/added.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 6 — Advanced Transcript UX (ADR-019; ADR-025 D6-01/D6-02/D6-03/D6-04)

## Objective

Implement the durable persistence layer for the P6-001 editable revision model:
the revision tables and models, the `RevisionRepository` implementation, the
active-revision pointer, machine-source materialization, and the optimistic
concurrency (stale-write) enforcement that makes version history durable across
reload.

## P6-001 semantics this task consumes (frozen)

P6-002 must not re-decide any of the following; it implements them:

- immutable machine source; editable revision layer (D6-01);
- `RevisionId` opaque identity; **transcription-scoped monotonic `version`
  independent of revision ancestry** (every new revision gets a version strictly
  greater than every version already allocated for the transcription; branching
  from an older active revision never reuses an earlier version);
  `parent_revision_id` records ancestry; durable history (D6-02);
- version allocation contract `RevisionVersionAllocator::nextVersionFor()`, and
  append-time re-validation that rejects a non-monotonic version with
  `RevisionConflictException` so `(transcription_id, version)` cannot collide
  across branches;
- active revision pointer (`transcriptions.active_revision_id`, null = machine
  source authoritative);
- optimistic concurrency: base revision id must equal the active revision id,
  otherwise `RevisionConflictException`; no silent merge / last-writer-wins;
- append enforces branch ancestry: the new revision's `parent_revision_id` must
  equal the base revision id it was composed against;
- deterministic active-pointer movement; branch ancestry queryable via
  `childrenOf()`;
- redo semantics: redo target is the active revision's **unique** child
  (`redoTargetFor()`); creating a new revision from a non-tip active revision
  invalidates the prior redo path (the branch point then has multiple children
  and automatic redo returns `null`); abandoned branches stay durable and are
  never deleted;
- revision segments: stable `RevisionSegmentIdentity`, contiguous `position`
  `0..n-1`;
- timing invariants: finite/non-negative/`start <= end`; overlaps legal with
  lowest-`position` resolution; zero-length legal but never active; no
  cross-segment monotonicity; revision timing never mutates machine timestamps;
- navigation identity over edited transcripts follows the active revision's
  identity/position, not machine `segment_index`;
- translation invalidation policy (all edit kinds invalidate); persisted
  staleness markers are **out of P6-002 scope** and owned by P6-005.

## Scope

1. **Schema (additive migrations only).** Add exactly the P6-001 §10 shape:
   - `transcript_revisions` (`id` uuid PK, `transcription_id` FK,
     `version`, `parent_revision_id` nullable, `created_by` FK, timestamps,
     unique `(transcription_id, version)`);
   - `transcript_revision_segments` (`revision_id` FK, `segment_key`,
     `position`, `start_seconds` decimal(12,3), `end_seconds` decimal(12,3),
     `text`, `language` string(16), unique `(revision_id, segment_key)`,
     unique `(revision_id, position)`);
   - `transcriptions.active_revision_id` nullable FK.
   No column on `transcriptions`/`transcription_segments` is dropped or
   semantically mutated.
2. **Models** (`TranscriptRevisionModel`, `TranscriptRevisionSegment`) with casts
   and relationships, following existing model conventions.
3. **`RevisionRepository` implementation** satisfying the P6-001 interface:
   `activeFor`, `find`, `historyFor`, `childrenOf`, `redoTargetFor`,
   `nextVersionFor`, `append`, `activate`, all CAS-fenced and append-only.
   `nextVersionFor` allocates the transcription-scoped next version and `append`
   re-validates it atomically (unique `(transcription_id, version)` is the
   durable backstop; a lost allocation race surfaces as
   `RevisionConflictException`, never a raw DB unique violation).
4. **Machine-source materialization:** map completed `TranscriptionSegment` rows
   into `MachineSegmentSnapshot`s and materialize the initial revision through
   `RevisionFactory`, preserving machine timing/text/language and machine
   provenance identities (`machine:<index>`).
5. **Optimistic concurrency:** stale base revision ids are rejected with
   `RevisionConflictException` without partial writes; transactions fence the
   revision row and its segments and the active pointer update.
6. **Durable history / undo-redo base:** activating an ancestor revision is a
   compare-and-set pointer move leaving all rows intact; appending after an
   undo branches from the active revision. Persist branch ancestry
   (`parent_revision_id`) and expose `childrenOf`; implement `redoTargetFor` as
   the unique-child rule (null at a branch point). A new edit from a non-tip
   active revision invalidates the prior redo path (forward-path semantics)
   without deleting any historical revision.
7. **Ownership:** all mutations require `TranscriptionPolicy::update`; revision
   reads require `view` (owner/admin).
8. **Feature/unit tests** covering persistence, CAS, history ordering, ownership,
   machine materialization, Unicode round-trip for `ms/en/zh/ta/und`, and
   empty/zero-length/overlap sequences.

## Non-Scope

- editing UI / Alpine / Blade (P6-003/P6-004/P6-006);
- split/merge implementation and translation-invalidation persistence
  (P6-005) — P6-002 must not add `translations.stale_at` /
  `translations.staleness_reason`;
- comparison UI (P6-007); revision-history UI (P6-008); integration gate (P6-009);
- speaker labels/annotations/bookmarks (D6-08, deferred); waveform/timeline
  (D6-09, deferred);
- any change to frozen Phase 3/4/5 contracts; tenancy/authorization redesign;
- translation/revision export changes beyond reading the active revision.

## Dependencies

- P6-001 = `DONE` (independently VERIFIED; `DECISION-P6-001-CLOSURE-001`); its
  semantics are frozen in `PHASE6-EDITING-DOMAIN-CONTRACT.md`.
- HPO READY promotion for P6-002: **granted** (`DECISION-P6-002-READY-001`).
- P3 persistence/immutability contracts; P4 read-model primitives.

## Acceptance Criteria

1. Migrations add only the named P6-001 §10 shape; no existing column is dropped
   or semantically changed; rollback is clean.
2. Revision rows and their segments persist and reload losslessly (text,
   timing, language, identity, position, version, parent).
3. Active revision round-trips; null active = machine source authoritative.
4. `append`/`activate` reject a stale base revision id with
   `RevisionConflictException` and leave state unchanged (no partial write).
5. History is ordered by version and never mutated in place; undo/redo is a CAS
   pointer move across durable history. Version allocation is
   transcription-scoped and monotonic independent of ancestry: after an
   undo-then-branch, every revision keeps a unique `(transcription_id,
   version)`, and a non-monotonic append is rejected as
   `RevisionConflictException` (not a raw DB unique-constraint violation).
6. Machine materialization copies machine timing/text/language verbatim and
   assigns machine provenance identities; machine rows are never written.
7. Ownership: unauthorized actors cannot append/activate; owner/admin can.
8. Unicode round-trip for `ms/en/zh/ta/und`; overlap/zero-length sequences
   persist and reload unchanged.
9. No split/merge or translation-staleness persistence introduced.
10. Relevant tests pass; Pint clean; PHPStan 0 errors; full regression green.
11. Redo invalidation / forward-path semantics: `redoTargetFor` returns the
    unique child before a branch and `null` once the active revision is a
    branch point; abandoned branches remain durable and visible in history;
    the active pointer deterministically follows the newly appended branch.

## Verification Requirements

- Feature tests against the real database (SQLite in-memory) for persistence,
  CAS, ownership, materialization, and reload.
- Regression tests for undo-then-branch version uniqueness (linear, single
  undo, multiple undos, multiple historical branches) and for redo
  invalidation / forward-path semantics (`childrenOf`/`redoTargetFor`).
- Unit tests for model/factory mapping.
- Full PHP suite regression; Pint; PHPStan.
- No browser evidence (no browser behavior changed by P6-002).

## Expected Reviewer

Claude Code (independent).

## Browser Evidence Required

No.

## Owner Decision Dependencies

D6-01, D6-02, D6-03, D6-04 (adopted); ADR-025; P6-001 frozen semantics.

## Implementation (2026-09-23)

Authorized by `DECISION-P6-002-READY-001` after P6-001 closure. Consumes the
frozen P6-001 semantics; redefines none.

Schema (additive migrations only):

- `2026_09_23_000001_create_transcript_revisions_table.php` — uuid PK,
  `transcription_id` FK, `version`, `parent_revision_id` nullable, `created_by`
  FK, timestamps, unique `(transcription_id, version)`.
- `2026_09_23_000002_create_transcript_revision_segments_table.php` —
  `revision_id` FK, `segment_key`, `position`, decimal(12,3) timings, `text`,
  `language`, unique `(revision_id, segment_key)`, unique `(revision_id,
  position)`.
- `2026_09_23_000003_add_active_revision_id_to_transcriptions_table.php` —
  nullable FK pointer (`nullOnDelete`).

Models/factories: `TranscriptRevisionModel`, `TranscriptRevisionSegment`
(+ factories); `Transcription` gains `active_revision_id`, `revisions()`,
`activeRevision()`.

Persistence (`app/Editing/Persistence/`):

- `EloquentRevisionRepository` implements the P6-001 interface
  (`activeFor`, `find`, `historyFor`, `childrenOf`, `redoTargetFor`,
  `nextVersionFor`, `append`, `activate`).
- `MachineSourceMaterializer` maps completed machine segments into a pure
  initial revision (no machine row is written).

Ownership (`app/Editing/RevisionService.php`): mutations require
`TranscriptionPolicy::update`; reads require `view`.

Concurrency/transaction strategy:

- `append`/`activate` run inside `DB::transaction(..., 5)` with
  `lockForUpdate` on the transcription row (row locking where the datastore
  supports it) and an active-pointer CAS.
- `append` re-reads the transcription max version immediately before insert and
  rejects a non-monotonic version with `RevisionConflictException`.
- The `(transcription_id, version)` unique constraint is the durable backstop;
  `UniqueConstraintViolationException` is translated into
  `RevisionConflictException::nonMonotonicVersion()` so a raw uniqueness
  exception is never the ordinary outcome.
- Append ancestry rule: the revision's `parent_revision_id` must equal the
  expected active revision.

Tests:

- `tests/Feature/Editing/RevisionSchemaTest.php` — schema shape, uniqueness
  constraints, cascade, machine-table non-mutation.
- `tests/Feature/Editing/RevisionRepositoryPersistenceTest.php` — materialize +
  round-trip, monotonic versions, undo-then-branch, multiple branches,
  redo/redo-invalidation, stale base, CAS, Unicode/overlap/zero-length,
  rollback on segment failure.
- `tests/Feature/Editing/RevisionOwnershipTest.php` — owner/admin/non-owner.
- `tests/Feature/Editing/MachineSourceImmutabilityTest.php` — machine timing,
  text, row identity/timestamps unchanged; no translation data moved.
- `tests/Feature/Editing/RevisionConcurrencyTest.php` — lost-allocation race,
  stale writers, active-pointer CAS, DB unique backstop.
- `tests/Feature/Editing/RevisionAppendRaceTest.php` + hidden
  `test:revision-append-race-worker` command — genuine two-process SQLite race
  proving exactly one writer persists a given `(transcription_id, version)` and
  the loser surfaces a domain conflict.
- `tests/Feature/Editing/RevisionMigrationRollbackTest.php` — clean reversal of
  the three additive P6-002 migrations.
- `tests/Unit/Editing/` (existing) — frozen P6-001 domain semantics.

Quality: full PHP suite 740/739 (1 skipped, 0 failures); Pint clean; PHPStan 0.

## Completion

Required flow completed: READY → IN_PROGRESS → REVIEW
(IMPLEMENTED_PENDING_REVIEW) → VERIFIED → DONE. P6-001 is DONE and frozen; P6-002
READY promotion was granted (`DECISION-P6-002-READY-001`); a corrective cycle
(2026-09-23) addressed the independent review's single MEDIUM finding
(strict-ancestor undo enforcement; corrective handoff
`reviews/pre-review/P6-002-corrective-pre-review.md`); the fresh independent
corrective re-review returned **VERIFIED**; and the HPO closed P6-002 DONE
(`DECISION-P6-002-CLOSURE-001`, 2026-09-23). No later Phase 6/7 task was started.
The P6-001/P6-002 foundation is frozen downstream input; the remaining Phase 6
candidates and their eligibility are reconciled in
`PHASE6-7-ELIGIBILITY-MATRIX.md` §M.