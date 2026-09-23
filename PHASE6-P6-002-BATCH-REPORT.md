# Phase 6 — P6-001 Closure + P6-002 Implementation Batch Report

Date: 2026-09-23
Scope: HPO closure of P6-001 and implementation of P6-002 (Revision Persistence /
Version History)
Authority: `DECISION-P6-001-CLOSURE-001`; `DECISION-P6-002-READY-001`;
`DECISION-PHASE6-AUTHORIZATION-001`; `DECISION-PHASE6-OWNER-DECISIONS-001`;
ADR-025; frozen `PHASE6-EDITING-DOMAIN-CONTRACT.md`

## 1. HPO closure decision for P6-001

P6-001 was transitioned `VERIFIED → DONE` under
`DECISION-P6-001-CLOSURE-001`, on the fresh independent corrective re-review
`reviews/P6-001-corrective-independent-re-review.md` (VERIFIED; no remaining
BLOCKER/HIGH/MEDIUM). The closure decision records the final P6-001 domain
semantics as **frozen inputs** for downstream Phase 6 work
(`PHASE6-EDITING-DOMAIN-CONTRACT.md` §0). All historical artifacts are preserved
unchanged:

- `reviews/P6-001-independent-review.md` (original CHANGES_REQUESTED);
- `reviews/pre-review/P6-001-corrective-pre-review.md` (corrective handoff);
- `reviews/P6-001-corrective-independent-re-review.md` (VERIFIED).

**Confirmation:** P6-001 was closed only after an independent VERIFIED verdict.

## 2. P6-002 READY promotion

P6-002 was promoted to **READY** and authorized for implementation under
`DECISION-P6-002-READY-001`, after P6-001 closure and after its authored contract
was reconciled against the corrected P6-001 version-allocation / redo semantics.
Implementation consumes the frozen P6-001 semantics and redefines none.

## 3. Schema / migrations added (additive only)

- `2026_09_23_000001_create_transcript_revisions_table.php` — uuid PK
  (`id`), `transcription_id` FK (cascade), `version`, `parent_revision_id`
  nullable (indexed), `created_by` FK, timestamps, **unique
  `(transcription_id, version)`**.
- `2026_09_23_000002_create_transcript_revision_segments_table.php` —
  `revision_id` FK (cascade), `segment_key`, `position`, `decimal(12,3)`
  timings, `text`, `language` string(16), **unique `(revision_id, segment_key)`**,
  **unique `(revision_id, position)`**.
- `2026_09_23_000003_add_active_revision_id_to_transcriptions_table.php` —
  nullable uuid FK → `transcript_revisions` (`nullOnDelete`).

No column on `transcriptions`/`transcription_segments` was dropped or
semantically changed.

## 4. Repository / persistence implementation

- `app/Editing/Persistence/EloquentRevisionRepository.php` implements
  `App\Editing\RevisionRepository` (`activeFor`, `find`, `historyFor`,
  `childrenOf`, `redoTargetFor`, `nextVersionFor`, `append`, `activate`),
  mapping rows to immutable `TranscriptRevision` value objects.
- `app/Editing/Persistence/MachineSourceMaterializer.php` maps completed
  `TranscriptionSegment` rows into pure `MachineSegmentSnapshot` objects and
  materializes the initial revision; it never writes a machine row.
- `app/Editing/RevisionService.php` enforces ownership:
  mutations require `TranscriptionPolicy::update`; reads require `view`.
- `app/Editing/RedoUnavailableException.php` for branch-point redo.
- Models `TranscriptRevisionModel`, `TranscriptRevisionSegment` (+ factories);
  `Transcription` gains `active_revision_id`, `revisions()`, `activeRevision()`.
- Binding: `AppServiceProvider` maps `RevisionRepository` →
  `EloquentRevisionRepository`.

## 5. Version-allocation transaction strategy

- `append` runs in `DB::transaction(..., 5)`.
- Inside the transaction it re-reads `MAX(version)` for the transcription and
  rejects a non-monotonic version with
  `RevisionConflictException::nonMonotonicVersion()` (strictly greater required).
- `(transcription_id, version)` unique constraint is the durable backstop;
  `UniqueConstraintViolationException` is caught and translated to
  `RevisionConflictException` — a raw DB uniqueness exception is never the
  ordinary outcome.
- `nextVersionFor` is the transcription-scoped allocator consumed by
  `RevisionFactory::derive` (P6-001), so branching never reuses a version.

## 6. Active-pointer CAS strategy

- `append`/`activate` take `lockForUpdate()` on the transcription row (row
  locking where the datastore supports it) and compare-and-set
  `active_revision_id` against the caller's expected token.
- `append` additionally enforces branch ancestry: the revision's
  `parent_revision_id` must equal the expected active revision id.
- Stale expectations raise `RevisionConflictException` and leave state unchanged
  (no partial write; whole transaction rolls back).

## 7. Conflict / error translation

- Stale base / lost active-pointer race → `RevisionConflictException::staleBase`.
- Non-monotonic version (application re-check) →
  `RevisionConflictException::nonMonotonicVersion`.
- Lost allocation race hitting the DB unique constraint →
  `RevisionConflictException::nonMonotonicVersion` (translated).
- Unknown revision / transcription / ancestry mismatch → `InvalidArgumentException`.
- Automatic redo at a branch point → `RedoUnavailableException`.
- No raw `QueryException`/`UniqueConstraintViolationException` is the ordinary
  user/domain outcome.

## 8. Tests and concurrency evidence

- `tests/Feature/Editing/` → **31 passed, 135 assertions**:
  schema constraints/cascade; migration reversal; persistence round-trip;
  monotonic versions; undo-then-branch; multiple branches; deterministic redo +
  invalidation; stale base; active-pointer CAS; unknown/foreign revision
  rejection; multilingual Unicode / overlap / zero-length round-trip; rollback
  on segment failure; ownership/isolation; machine-source immutability;
  lost-allocation race; DB unique backstop; genuine two-process append race.
- `tests/Unit/Editing/` (frozen P6-001 semantics) → **48 passed, 186 assertions**.
- `app/Console/Commands/RevisionAppendRaceWorker.php` (hidden, testing-only) and
  `tests/Feature/Editing/RevisionAppendRaceTest.php` spawn two independent OS
  processes with separate SQLite connections to one database file; exactly one
  persists version 2 and the loser surfaces `RevisionConflictException`. Re-run
  4× independently, all passing.
- Full PHP suite → **740 tests, 739 passed, 1 skipped, 0 failures**.
- Pint clean; PHPStan **0** errors.

## 9. Changed files

Modified: `AGENTS.md`, `CURRENT_STATE.md`, `DECISIONS.md`, `DECISION_QUEUE.md`,
`PHASE6-7-ELIGIBILITY-MATRIX.md`, `PHASE6-EDITING-DOMAIN-CONTRACT.md`,
`app/Editing/{EditKind,RevisionConflictException,RevisionFactory,RevisionRepository,TranslationInvalidationPolicy}.php`,
`app/Models/Transcription.php`, `app/Providers/AppServiceProvider.php`,
`tasks/P6-001-phase6-editing-domain-contract.md`,
`tasks/P6-002-revision-persistence-version-history.md`,
`tests/Support/InMemoryRevisionRepository.php`,
`tests/Unit/Editing/{RevisionFactoryTest,RevisionRepositoryContractTest,TranscriptRevisionTest,TranslationInvalidationPolicyTest}.php`.

Added: `app/Console/Commands/RevisionAppendRaceWorker.php`,
`app/Editing/Persistence/{EloquentRevisionRepository,MachineSourceMaterializer}.php`,
`app/Editing/{RedoUnavailableException,RevisionService,RevisionVersionAllocator}.php`,
`app/Models/{TranscriptRevisionModel,TranscriptRevisionSegment}.php`,
`database/factories/{TranscriptRevisionFactory,TranscriptRevisionSegmentFactory}.php`,
`database/migrations/2026_09_23_00000{1,2,3}_*.php`,
`tests/Feature/Editing/*`, `tests/Support/EditingPersistenceFixtures.php`,
`tests/Unit/Editing/{RedoSemanticsTest,RevisionVersioningTest}.php`,
`reviews/pre-review/{P6-001-corrective-pre-review,P6-002-pre-review}.md`,
`PHASE6-P6-002-BATCH-REPORT.md`.

Commits: none created by the implementation agent (repository policy: commits
only on explicit request). Changes are staged in the working tree for the
normal flow.

## 10. Known SQLite / production-datastore limitations

- SQLite serializes writers at the file level and `lockForUpdate()` compiles to a
  no-op; the local store cannot demonstrate production row-locking.
- The concurrency guarantee demonstrated locally is: application-level monotonic
  re-check + active-pointer CAS + the durable `(transcription_id, version)`
  unique backstop, with DB uniqueness violations translated to domain conflict.
- A production RDBMS (PostgreSQL/MySQL) must provide genuine row-level locking;
  enforcing that and load-testing concurrent writers is a P7 productionization
  concern. No production locking guarantee is claimed from the SQLite evidence.

## 11. P6-002 pre-review status

`P6-002 = IMPLEMENTED_PENDING_REVIEW`. Fresh independent review required; builder
handoff: `reviews/pre-review/P6-002-pre-review.md`. P6-002 is not VERIFIED and
not DONE.

## 12. Recommended fresh-review focus

Reproduce the undo-then-branch version uniqueness and stale-base/CAS paths
against the real database; inspect the migrations against P6-001 §10; run the
two-process append race; verify DB-unique-violation → domain-conflict
translation; verify ownership/isolation and machine-source immutability; re-run
the full suite, Pint, and PHPStan; confirm no frozen semantics were redefined and
no other task was started.

## 13. Explicit confirmations

- **P6-001 was closed only after an independent VERIFIED verdict.**
- **P6-002 did not redefine any frozen P6-001 semantics**; it consumes them.
- **No later P6 task was started** (P6-003/P6-004/P6-005/P6-007/P6-008/P6-009
  remain not started; no split/merge UI, no translation-staleness persistence).
- **No additional Phase 7 work occurred** (Phase 7 remains not generally
  authorized; P7-005 unchanged/DONE).
