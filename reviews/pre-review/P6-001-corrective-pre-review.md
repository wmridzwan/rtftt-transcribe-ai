# P6-001 — Builder Pre-Review Handoff (corrective cycle)

Date: 2026-09-23
Task: `tasks/P6-001-phase6-editing-domain-contract.md`
Status: `IMPLEMENTED_PENDING_REVIEW` (corrective; not VERIFIED, not DONE)
Authority: `DECISION-PHASE6-AUTHORIZATION-001`;
`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025 (D6-01..D6-09, DC-01)
Reviewer: Claude Code (fresh independent re-review required)

## Why this corrective exists

`reviews/P6-001-independent-review.md` returned **CHANGES_REQUESTED** with:

- **BLOCKER** — `RevisionFactory::derive()` computed `version = base.version + 1`,
  so `undo → edit from an older active revision` produced duplicate
  `(transcription_id, version)` values, contradicting the contract and the
  planned P6-002 unique constraint.
- **HIGH** — redo target was undefined once a revision had more than one child
  (branching).
- **MEDIUM** — no reason-precedence rule for a single edit spanning multiple
  `EditKind` categories.
- **LOW** — two trivial PHPStan findings in shipped test-support code.

This corrective resolves all four. P6-002 was **not** promoted and **not**
implemented.

## 1. Exact contract changes

### `PHASE6-EDITING-DOMAIN-CONTRACT.md`

- §2 rewritten: `version` is a **monotonic sequence scoped to the
  transcription, independent of ancestry**; `parent_revision_id` is ancestry
  only; version is allocated via `RevisionVersionAllocator` and re-validated at
  append. Added a "Durable revision graph vs. user undo/redo path" subsection
  defining redo invalidation on branch and `redoTargetFor`/`childrenOf`.
- §3 extended: append enforces branch ancestry (`parent_revision_id` equals the
  base it was composed against), re-validates monotonic version, and
  active-pointer movement is deterministic (append follows the new branch).
- §7 added a "Mixed-category edit precedence" subsection:
  `SegmentStructureChanged > TimingChanged > SourceTextChanged`, selecting the
  canonical reason only (all kinds remain invalidating).
- §10 `version` note updated (transcription-scoped, ancestry-independent,
  allocated via `RevisionVersionAllocator`).
- §11 integration-gate item 2 extended (version uniqueness across
  undo-then-branch; automatic redo unavailable at a branch point).

### `tasks/P6-002-revision-persistence-version-history.md`

Reconciled (contract only) — see §7 below. Status unchanged:
`CONTRACT_AUTHORED_PENDING_P6-001_CLOSURE`; not READY.

## 2. Version-allocation semantics (BLOCKER)

Adopted rule: **`version` is a monotonic sequence scoped to the transcription,
independent of ancestry.**

- New interface `app/Editing/RevisionVersionAllocator.php`:
  `nextVersionFor(int $transcriptionId): int` — strictly greater than every
  version already allocated for the transcription, or `1` when empty. This is
  an abstract domain contract (no Eloquent/SQL/storage coupling).
- `RevisionRepository extends RevisionVersionAllocator`.
- `RevisionFactory::derive(TranscriptRevision $base, int $createdBy,
  array $segments, RevisionVersionAllocator $allocator, ?DateTimeImmutable
  $createdAt = null)`: obtains the version from `$allocator->nextVersionFor()`
  instead of `$base->version + 1`, and asserts the allocated version is
  strictly greater than the base version.
- `RevisionRepository::append()` re-validates monotonicity and rejects a
  non-monotonic version with `RevisionConflictException::nonMonotonicVersion()`
  — so a lost allocation race surfaces as a deliberate conflict, never a raw DB
  unique-constraint violation.
- `RevisionConflictException` gained `nonMonotonicVersion()`.

Branching from an older active revision therefore never reuses a version:
undo to `v2` then edit yields the transcription max + 1, not `v2.version + 1`.

## 3. Redo semantics after branching (HIGH)

Adopted rule: **creating a new revision from an undone/non-tip active revision
invalidates the prior redo path for user redo semantics.**

- The contract now separates:
  - the **durable revision graph/history** — append-only, ancestry via
    `parent_revision_id`, never deleted/rewritten; and
  - the **user undo/redo navigation path** — derived active-pointer movement.
- `RevisionRepository::childrenOf(string $revisionId): list<TranscriptRevision>`
  exposes branch ancestry (ordered by version).
- `RevisionRepository::redoTargetFor(int $transcriptionId): ?TranscriptRevision`
  returns the active revision's **unique** child, or `null` when redo is
  unavailable (no active revision, no child, or a branch point with >1 child).
  Automatic redo never chooses among siblings.
- Historical revisions stay durable and visible; the new branch becomes the
  current forward history (append moves the active pointer onto it). Explicit
  historical selection is deferred to P6-008 and is not automatic redo.

## 4. Translation-staleness precedence (MEDIUM)

Adopted precedence: `SegmentStructureChanged` > `TimingChanged` >
`SourceTextChanged`.

- `EditKind::precedence(): int` — `Structural` 3, `Timing` 2, `Textual` 1.
- `TranslationInvalidationPolicy::reasonForKinds(EditKind ...$kinds)` selects
  the highest-precedence reason; throws `InvalidArgumentException` when no kind
  is supplied.
- Every applicable edit kind remains translation-invalidating; precedence only
  selects the canonical recorded reason. No silent remap path added.

## 5. LOW test-support PHPStan findings

- Removed the redundant `array_values()` in
  `InMemoryRevisionRepository::historyFor()`.
- Added the missing `@param list<RevisionSegmentData> $segments` PHPDoc to the
  `editingRevision()` test helper.
- Extended-scope PHPStan (`app/Editing` + `tests/Unit/Editing` + support) now
  reports **0 errors**.

## 6. Changed files

Modified:

- `PHASE6-EDITING-DOMAIN-CONTRACT.md`
- `app/Editing/EditKind.php`
- `app/Editing/RevisionConflictException.php`
- `app/Editing/RevisionFactory.php`
- `app/Editing/RevisionRepository.php`
- `app/Editing/TranslationInvalidationPolicy.php`
- `tests/Support/InMemoryRevisionRepository.php`
- `tests/Unit/Editing/RevisionFactoryTest.php`
- `tests/Unit/Editing/RevisionRepositoryContractTest.php`
- `tests/Unit/Editing/TranscriptRevisionTest.php`
- `tests/Unit/Editing/TranslationInvalidationPolicyTest.php`
- `tasks/P6-001-phase6-editing-domain-contract.md`
- `tasks/P6-002-revision-persistence-version-history.md`

Added:

- `app/Editing/RevisionVersionAllocator.php`
- `tests/Unit/Editing/RevisionVersioningTest.php`
- `tests/Unit/Editing/RedoSemanticsTest.php`
- `reviews/pre-review/P6-001-corrective-pre-review.md` (this file)

Commits: corrective changes are staged in the working tree for the next commit
(no commit made by the implementer; commit is the HPO/flow's action).

## 7. Tests / results

- `php artisan test --compact tests/Unit/Editing` → **48 passed**, 186
  assertions, 0 failures.
- `php artisan test --compact` (full suite) → **709 tests, 708 passed, 1
  skipped, 0 failures** (2 warnings).
- `vendor/bin/pint --dirty --format agent` → clean.
- `vendor/bin/phpstan analyse` (project scope) → **0 errors**.
- `vendor/bin/phpstan analyse app/Editing tests/Unit/Editing
  tests/Support/InMemoryRevisionRepository.php tests/Support/EditingFixtures.php`
  (extended scope) → **0 errors**.

New regression coverage:

- `RevisionVersioningTest` — linear sequence; single undo then branch (the
  original BLOCKER, asserted unique); multiple undos then branch; multiple
  historical branches; no duplicate version within one transcription;
  cross-transcription version reuse; non-monotonic version rejected by the
  repository (hand-crafted duplicate reproduces the original collision at the
  append boundary); ancestry-mismatch rejection; `nextVersionFor` behavior.
- `RedoSemanticsTest` — undo → redo before branching; undo → new edit → redo
  unavailable on the old path; durable old branch still present; branch
  ancestry via `childrenOf`; no redo target for machine source/no-child; active
  pointer deterministically follows the most recent branch.
- `TranslationInvalidationPolicyTest` — mixed-category precedence for all
  combinations; precedence ordering; empty-kinds rejection; all kinds remain
  invalidating.

## 8. P6-002 contract impact

`tasks/P6-002-revision-persistence-version-history.md` was reconciled (contract
text only, no implementation, no migration) so it explicitly plans persistence
for:

- transcription-scoped monotonic version allocation (`nextVersionFor` +
  append-time atomic re-validation; unique `(transcription_id, version)` as the
  durable backstop; lost races surface as `RevisionConflictException`);
- unique `(transcription_id, version)`;
- parent revision ancestry (`parent_revision_id`, `childrenOf`);
- active pointer CAS;
- redo invalidation / forward-path semantics (`redoTargetFor` unique-child
  rule; abandoned branches durable).

Scope item 3, scope item 6, AC5, the new AC11, and the verification
requirements were updated accordingly.

## 9. P6-002 remains not READY

**Confirmed.** P6-002 is `CONTRACT_AUTHORED_PENDING_P6-001_CLOSURE`; it was not
promoted to READY, not marked READY, and not implemented. No migration, model,
or persistence code was added. P6-002 still requires independent P6-001
VERIFICATION plus an explicit HPO READY promotion.

## 10. Handoff for fresh independent re-review

Requested focus for a **fresh** reviewer (do not rely on this handoff):

1. Reproduce the original BLOCKER adversarially against the corrected code:
   `v1 → v2 → v3`, undo to `v2`, edit again — assert `version = 4` and all
   versions unique for the transcription.
2. Verify `RevisionFactory::derive()` no longer derives version from the base
   alone and that the domain layer has no persistence coupling
   (`app/Editing/` references no Eloquent/DB/`Illuminate\Database`).
3. Verify `redoTargetFor()` returns the unique child before branching and
   `null` at a branch point; that old branches remain durable; and that the
   active pointer deterministically follows the new branch.
4. Verify mixed-category precedence
   (`Structural > Timing > Textual`) and that every kind remains invalidating.
5. Confirm no frozen Phase 3/4/5 contract, migration, model, route, view, or JS
   was changed; re-run the full suite, Pint, and PHPStan independently.
6. Confirm P6-002 is still not READY and no additional Phase 6/7 task was
   started.

## Not done (correctly)

- P6-001 is not VERIFIED and not DONE; no self-verification.
- P6-002 was not implemented, migrated, or promoted to READY.
- No P6-003/P6-004/P6-005/P6-007/P6-008/P6-009 and no further P7 work started.
- No browser behavior changed (no browser evidence required).
