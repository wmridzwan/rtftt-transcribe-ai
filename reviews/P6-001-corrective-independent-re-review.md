# P6-001 — Corrective Independent Re-Review

Date: 2026-09-23
Task: `tasks/P6-001-phase6-editing-domain-contract.md`
Reviewer: Claude Code (fresh independent re-review; no reliance on the
implementer's corrective summary or on `reviews/pre-review/P6-001-corrective-pre-review.md`
beyond using it as a pointer to what to check)
Prior independent verdict: `reviews/P6-001-independent-review.md` — CHANGES_REQUESTED
(BLOCKER: version collision on undo-then-branch; HIGH: undefined redo target;
MEDIUM: no mixed-category staleness precedence; LOW: test-support PHPStan)

## Verdict

**VERIFIED**

All four findings from the prior CHANGES_REQUESTED review are resolved at the
domain-contract level, independently reproduced, and not merely asserted by
the implementer. No new BLOCKER or HIGH issue was found. One INFO note is
recorded (pre-existing, unrelated flaky test) and does not block this
verdict.

## Status of each original finding

### BLOCKER — undo-then-branch version collision → RESOLVED

`RevisionFactory::derive()` (`app/Editing/RevisionFactory.php:91-117`) no
longer computes `$base->version + 1`. It now takes a `RevisionVersionAllocator
$allocator` and obtains the version via `$allocator->nextVersionFor($base->transcriptionId)`,
asserting only that the allocated version is strictly greater than the base's
own version (a sanity check, not the uniqueness mechanism). `RevisionRepository`
(`app/Editing/RevisionRepository.php:24`) now `extends RevisionVersionAllocator`,
and its `append()` contract documents (and the reference implementation
enforces) re-validation: a version that is not strictly greater than every
version already allocated for the transcription is rejected with
`RevisionConflictException` (`RevisionConflictException::nonMonotonicVersion()`,
`app/Editing/RevisionConflictException.php:25-32`).

Independently reproduced the exact adversarial transcript from the original
BLOCKER finding, both by re-running the shipped regression test and by
tracing the code manually:

1. `v1`(1) → `v2`(2, parent v1) → `v3`(3, parent v2) — linear.
2. Undo: `activate(v2, expected=v3)` — CAS succeeds, active pointer → v2.
3. Edit again from v2: `derive($v2, ..., $allocator)`. `nextVersionFor(10)`
   scans full transcription history (max=3) and returns 4, **not**
   `$v2->version + 1 = 3`.
4. `append($v4, expectedActive=v2)` succeeds; `v4.version = 4`,
   `v4.parentRevisionId = v2`.
5. `historyFor(10)` → versions `[1,2,3,4]`, all unique;
   `(transcription_id, version)` uniqueness holds.

This exact scenario is covered by `tests/Unit/Editing/RevisionVersioningTest.php`
("does not reuse a version when branching after a single undo (original
blocker regression)"), plus additional adversarial coverage this reviewer
independently traced and re-ran: multiple undos then branch, three
independent historical branches from the same ancestor (all get unique
versions 2/3/4), cross-transcription version reuse (versions independently
restart at 1 per `transcription_id`), and a hand-crafted duplicate-version
revision that bypasses the factory entirely and is rejected by `append()`'s
independent re-validation (`RevisionConflictException`), proving the
uniqueness guarantee does not depend on callers using the factory correctly.
**Genuinely fixed**, not just for the exact reported transcript but for the
general branch-after-undo class of scenario.

### HIGH — undefined redo target once branching exists → RESOLVED

The contract now explicitly separates the durable revision graph (ancestry,
append-only, `parent_revision_id`) from the user undo/redo navigation path
(derived active-pointer movement). `RevisionRepository::childrenOf()` enumerates
a revision's children ordered by version; `RevisionRepository::redoTargetFor()`
returns the active revision's child **only when there is exactly one**,
`null` when there are zero or more than one (`app/Editing/RevisionRepository.php:53-65`).
The reference implementation (`InMemoryRevisionRepository::redoTargetFor()`,
lines 72-83) matches this precisely: `count($children) === 1 ? $children[0] : null`.

Verified via `RedoSemanticsTest.php`, independently re-run and traced:

- undo → redo before any branch: `redoTargetFor` returns the unique child;
  activating it restores the tip and `redoTargetFor` correctly returns `null`
  again (no further child);
- undo → new edit → branch: the branch point (`v2`) now has two children
  (`v3`, `v4`); `redoTargetFor` returns `null` at that branch point — redo
  never guesses among siblings;
- the new branch (`v4`) becomes the active forward history immediately on
  append (`append()` sets the active pointer to the newly appended revision);
- the abandoned branch (`v3`) remains durable and reachable via `historyFor()`
  and `find()` — not deleted;
- `childrenOf()` correctly reports both siblings ordered by version.

No hidden ambiguity found in either method: `redoTargetFor`'s only branching
condition is `count($children)`, which is unambiguous for 0/1/>1.

### MEDIUM — no mixed-category staleness precedence → RESOLVED

`EditKind::precedence()` (`app/Editing/EditKind.php:37-44`) assigns
`Structural=3 > Timing=2 > Textual=1`. `TranslationInvalidationPolicy::reasonForKinds()`
(`app/Editing/TranslationInvalidationPolicy.php:49-64`) scans the supplied
kinds and keeps the one with the highest precedence — a simple linear max-scan
independent of input order. Verified deterministic regardless of ordering by
tracing all six 2-permutation and both 3-permutation cases exercised in
`TranslationInvalidationPolicyTest.php` ("selects the most invasive reason for
an edit spanning multiple categories") — e.g. `(Textual, Structural)` and
`(Structural, Textual)` both resolve to `SegmentStructureChanged`. Every kind
remains independently invalidating regardless of precedence
(`mustMarkStale()` is unconditional per-kind and unaffected by
`reasonForKinds()`), confirmed by `EditKind::cases()` iteration in the "keeps
every category... invalidating regardless of precedence" test. Precedence
selects only the canonical audit-trail reason; no suppression of staleness for
any kind.

### LOW — test-support PHPStan findings → RESOLVED

The redundant `array_values()` call in `InMemoryRevisionRepository::historyFor()`
is gone (it now returns the `usort`-mutated `$revisions` list directly — `usort`
re-indexes in place, so no separate `array_values()` was ever necessary).
`tests/Unit/Editing/TranscriptRevisionTest.php`'s `editingRevision()` helper
now carries `@param list<RevisionSegmentData> $segments`. Independently
re-ran the extended-scope PHPStan analysis (see Verification below): 0 errors.

## Version-allocation contract safety for P6-002 (concurrent writers)

The two-step flow — `RevisionFactory::derive()` calls `nextVersionFor()`
*advisory-only* to stamp an immutable `TranscriptRevision`, then
`RevisionRepository::append()` independently re-validates monotonicity before
committing — is a standard optimistic-CAS pattern and is safe **provided**
the P6-002 implementation makes `append()`'s version check atomic with the
insert (transaction + `unique(transcription_id, version)` as the durable
backstop, translating a caught constraint violation into
`RevisionConflictException` rather than leaking a raw DB exception). This
division of responsibility is explicit, not merely implied:

- `RevisionRepository::append()`'s docblock states the version re-validation
  as a hard `@throws RevisionConflictException` obligation on any
  implementation (`app/Editing/RevisionRepository.php:76-81`), independent of
  how the value was derived by the caller.
- `tasks/P6-002-revision-persistence-version-history.md` (inspected, not
  edited) scope item 3 explicitly assigns this responsibility to P6-002:
  "`nextVersionFor` allocates the transcription-scoped next version and
  `append` re-validates it atomically (unique `(transcription_id, version)`
  is the durable backstop; a lost allocation race surfaces as
  `RevisionConflictException`, never a raw DB unique violation)."

Challenged the race scenarios from the review brief:

- **Two writers read the same max version.** Both `derive()` calls compute
  the same candidate version (e.g. 4). Whichever `append()` commits first
  wins; the second's insert collides with the DB unique constraint, which
  P6-002's contract requires be caught and re-raised as
  `RevisionConflictException` — not silently retried or merged. The P6-001
  interface neither prevents nor silently masks this; it requires the
  translation explicitly.
- **Active revision changes between allocation and append.** Independent of
  version allocation, the CAS on `expectedActiveRevisionId` still applies at
  `append()` — a version race and an active-pointer race are checked as two
  separate, independently-enforceable invariants (see next section).
- **Stale/non-monotonic proposed version reaching append.** Covered directly
  by `RevisionVersioningTest`'s hand-crafted-duplicate test, which bypasses
  the factory/allocator entirely and asserts `append()` alone rejects it.

P6-001 correctly does not implement DB locking itself (out of scope, and the
domain layer has zero Eloquent/DB/`Illuminate\Database` references — reverified
by grep, matching the original review's finding and unchanged in the
corrective). The atomicity obligation is delegated to P6-002 explicitly and
is not contradicted anywhere in the current interface or reference
implementation. This is safe to build against.

## Stale-base / CAS interaction

Verified the two invariants are independent and both remain enforceable:

- **Active-pointer CAS** (`expectedActiveRevisionId` vs. current active) is
  still checked first in the reference `append()`
  (`tests/Support/InMemoryRevisionRepository.php:96-103`) and independently in
  `activate()` (lines 128-134). A stale base is rejected deterministically
  regardless of version correctness — re-verified via
  `RevisionRepositoryContractTest`'s "rejects a stale append without mutating
  history" and "activates a prior revision only against the expected current
  revision," both re-run and passing.
- **Ancestry consistency** (`parentRevisionId === expectedActiveRevisionId`)
  is checked as a distinct rule (lines 108-114), independently verified by
  `RevisionVersioningTest`'s "rejects a revision whose parent does not match
  the expected active revision" — a hand-crafted revision with correct CAS
  token but mismatched parent is rejected by the ancestry check alone, not by
  the version check.
- **Version uniqueness** is checked as a third, independent rule (lines
  116-120), verified in isolation by the hand-crafted-duplicate test (correct
  CAS token and correct ancestry, only the version collides).

No silent merge exists anywhere in the reviewed code; every failure mode
raises `RevisionConflictException` deterministically. The three checks
(active-pointer CAS, ancestry, version monotonicity) are structurally
independent in the reference implementation, so an implementation cannot
satisfy one at the expense of silently skipping another.

## Redo semantics vs. P6-002

`tasks/P6-002-revision-persistence-version-history.md` (inspected only; not
edited) correctly restates — never re-invents — the corrected semantics:
branch ancestry via `parent_revision_id`/`childrenOf`, durable old
descendants ("abandoned branches stay durable and are never deleted"),
forward-path invalidation (redo unavailable once a branch point has >1
child), active-pointer movement (append always moves the pointer to the new
branch), and version-sequence independence from parent version (scope item 3,
quoted above). No ambiguity remains for P6-002 to resolve on its own; the
task text's own "P6-001 semantics this task consumes (frozen)" section is a
faithful restatement of `app/Editing/RevisionRepository.php` and
`PHASE6-EDITING-DOMAIN-CONTRACT.md` §2/§3, not an invention.

## Existing P6-001 semantics — regression check

Re-verified (by reading `TranscriptRevision.php`, `RevisionRepository.php`,
and re-running the full `tests/Unit/Editing` suite) that the corrective did
not touch or regress: immutable machine source (no Eloquent/DB coupling
anywhere in `app/Editing/`, reconfirmed by grep); append-only revisions
(`TranscriptRevision` is `final readonly`; no `update`/`delete` method exists
on `RevisionRepository`); active-revision/branch-from-active semantics;
revision identity (`RevisionId` opaque, server-generated, never caller
supplied); timing invariants (`TimingInvariants` file untouched — not in the
diff — and `TimingInvariantsTest` still passes as part of the full run);
overlap/zero-length legality (unchanged files); contiguous position ordering
and lowest-position active resolution (unchanged files); active-revision
navigation identity (unchanged); machine timestamp immutability (unchanged);
Phase 4 reserved hooks (`data-seek-seconds`/`data-segment-language`,
untouched, confirmed no Blade/JS files in the diff); no
browser/schema/persistence implementation leakage (confirmed: `git diff
--name-only HEAD` touches only `app/Editing/*`, `tests/*`, the two contract
docs, and this review's own artifacts — zero migrations, models, routes,
controllers, views, or JS).

## Repository abstraction quality

`RevisionRepository` remains persistence-neutral: the interface signature
uses only `int`/`string`/`?string`/`TranscriptRevision`/`list<TranscriptRevision>`
types, no Eloquent/SQL leakage. It is no longer just five methods but seven
(`activeFor`, `find`, `historyFor`, `childrenOf`, `redoTargetFor`,
`nextVersionFor` (inherited from `RevisionVersionAllocator`), `append`,
`activate`) — sufficient for P6-002 to implement directly, and P6-002's own
scope item 3 restates exactly this method list with no additions needed.
Version allocation, append validation, active-pointer CAS, child enumeration,
and redo-target resolution are placed at the repository boundary consistently
— none of them leak into the pure `RevisionFactory` (which only consumes the
allocator interface, never a concrete implementation). No API in the current
contract makes a correct transactional P6-002 implementation impossible or
unnecessarily race-prone; the atomicity delegation is explicit (see above).

## Scope discipline

Confirmed via `git diff --name-only HEAD` and `git status --porcelain`: no
migration, Eloquent model, route, controller, view, JavaScript, split/merge
implementation, or translation-persistence implementation was introduced.
Changed/added files are exactly: `PHASE6-EDITING-DOMAIN-CONTRACT.md`, six
`app/Editing/*.php` files (five modified, one new —
`RevisionVersionAllocator.php`), seven `tests/*` files (five modified, two
new — `RevisionVersioningTest.php`, `RedoSemanticsTest.php`), and the two
task/contract markdown files. `tasks/P6-002-revision-persistence-version-history.md`
remains `CONTRACT_AUTHORED_PENDING_P6-001_CLOSURE`, explicitly "Not READY; not
implemented" — confirmed unchanged in status. No other Phase 6/7 task file
was touched.

## Verification performed (independently reproduced vs. inspected)

**Independently reproduced (commands re-run by this reviewer):**

- `php artisan test --compact tests/Unit/Editing` → 48 tests, 48 passed, 186
  assertions, 0 failures (matches the corrective pre-review's reported
  numbers).
- `php artisan test --compact` (full suite) → 709 tests, 707 passed, 1
  skipped, 1 error, 2581 assertions. The one failure
  (`Tests\Feature\Observability\LogContextTest::it_counts_attempt_ordinals_across_attempts_for_the_same_transcription`)
  is a `processing_jobs.transcription_id` unique-constraint collision, in a
  file **outside** the P6-001 diff (`tests/Feature/Observability/LogContextTest.php`
  is not in `git diff --name-only HEAD`), and passed cleanly when re-run in
  isolation (`php artisan test --compact tests/Feature/Observability/LogContextTest.php`
  → 6/6 passed). This is a pre-existing factory/shared-state flake unrelated
  to the Editing domain, not a regression introduced by this corrective.
  Recorded as INFO, not a blocking finding.
- `vendor/bin/pint --test app/Editing tests/Unit/Editing tests/Support/InMemoryRevisionRepository.php`
  → passed, no style violations.
- `vendor/bin/phpstan analyse` (project default scope: `app/`,
  `bootstrap/app.php`, `config/`, `database/`, `routes/`) → 0 errors.
- `vendor/bin/phpstan analyse app/Editing tests/Unit/Editing tests/Support/InMemoryRevisionRepository.php tests/Support/EditingFixtures.php`
  (extended scope, matching the original review's reviewer-added scope) → 0
  errors (previously 3 LOW findings; now clean).
- Manual code trace of the exact adversarial undo-then-branch transcript from
  the original BLOCKER finding, cross-checked line-by-line against
  `RevisionFactory::derive()`, `RevisionRepository::append()`'s contract, and
  the reference implementation's enforcement — reproduces the fix, not just
  the shipped test's assertion.
- `git status --porcelain` / `git diff --name-only HEAD` — confirmed the
  full, exact set of changed/added files and the absence of any
  migration/model/route/view/JS/split-merge/translation-persistence file.

**Inspected only (read and reasoned about, not independently re-executed
beyond what's listed above):**

- `PHASE6-EDITING-DOMAIN-CONTRACT.md` full text (§2, §3, §7 changes) against
  the corrective pre-review's claimed changes — matches.
- All modified/added `app/Editing/*.php` files, read in full:
  `RevisionVersionAllocator.php`, `RevisionFactory.php`, `RevisionRepository.php`,
  `RevisionConflictException.php`, `TranslationInvalidationPolicy.php`,
  `EditKind.php`, and `TranscriptRevision.php` (unchanged, re-read for
  regression confirmation).
- `tests/Support/InMemoryRevisionRepository.php`, read in full.
- `RevisionVersioningTest.php`, `RedoSemanticsTest.php`,
  `TranslationInvalidationPolicyTest.php`, `RevisionRepositoryContractTest.php`,
  `RevisionFactoryTest.php`, `TranscriptRevisionTest.php`, all read in full
  (assertions also reproduced by running them, above).
- `tasks/P6-002-revision-persistence-version-history.md`, read in full, for
  readiness-impact analysis (not edited, not implemented, not promoted).
- `reviews/P6-001-independent-review.md` and
  `reviews/pre-review/P6-001-corrective-pre-review.md`, read in full as the
  basis for this re-review's scope, not relied on for conclusions.

**Not reproduced:** browser evidence (correctly not required — this task
changes no browser behavior; confirmed via the file-list check above).

## Residual findings

None BLOCKER/HIGH/MEDIUM. One INFO item, non-blocking:

**INFO — unrelated pre-existing flaky test in the full suite.**
`tests/Feature/Observability/LogContextTest.php`'s
"it counts attempt ordinals across attempts for the same transcription" test
hit a `processing_jobs.transcription_id` unique-constraint violation during
the full-suite run but passed in isolation. This file is untouched by the
P6-001 corrective and the failure mode (a factory-generated duplicate
`transcription_id` under full-suite shared state) is unrelated to the
Editing domain. Worth a separate ticket to make that factory/test
collision-proof, but it does not affect this task's verdict.

## Answers to the ten required questions

1. **P6-001 verdict:** VERIFIED.
2. **Status of each original finding:** BLOCKER (version collision) —
   RESOLVED; HIGH (undefined redo target) — RESOLVED; MEDIUM (no
   mixed-category precedence) — RESOLVED; LOW (test-support PHPStan) —
   RESOLVED.
3. **Is branch-after-undo version uniqueness genuinely fixed?** Yes —
   reproduced the exact original transcript plus multiple-undo,
   multiple-branch, and hand-crafted-bypass variants; `(transcription_id,
   version)` uniqueness holds in every case, enforced independently at
   `append()` regardless of how the caller derived the version.
4. **Are version-allocation semantics safe for P6-002 to implement
   transactionally?** Yes. The interface requires `append()` to re-validate
   monotonicity and translate any race into `RevisionConflictException`; this
   obligation is explicit in the interface docblock and restated in P6-002's
   own contract text, not contradicted by anything in the current API. P6-001
   correctly does not implement DB locking itself.
5. **Are redo semantics deterministic?** Yes — `redoTargetFor()`'s only
   branching condition is the count of children (0/1/>1), unambiguous in all
   cases, verified against undo-before-branch, undo-after-branch, and
   no-active/no-child scenarios.
6. **Is translation-invalidation precedence deterministic?** Yes — a
   simple order-independent max-scan over supplied `EditKind`s, verified
   across all tested permutations, with every kind remaining independently
   invalidating regardless of the selected canonical reason.
7. **Residual findings:** none blocking; one non-blocking INFO (unrelated
   pre-existing flaky test).
8. **Exact evidence independently reproduced:** listed in full above — test
   suite (Editing-scoped and full), Pint, PHPStan (default and extended
   scope), manual adversarial trace, and file-diff/scope audit.
9. **May P6-001 proceed to HPO closure?** Yes — no BLOCKER or HIGH finding
   remains; all acceptance criteria in `tasks/P6-001-phase6-editing-domain-contract.md`
   are met; this reviewer recommends VERIFIED, with closure to DONE remaining
   the Human Product Owner's decision.
10. **May P6-002 safely be promoted to READY after that closure?** Yes, on
    the current contract text — `tasks/P6-002-revision-persistence-version-history.md`
    faithfully restates the corrected P6-001 semantics without inventing new
    domain rules, and no ambiguity requiring further P6-001 amendment
    remains. READY promotion itself is an HPO decision, not granted by this
    review.

## Explicitly not done (per review scope)

- P6-001 is not marked DONE by this review (Human Product Owner decision).
- P6-002 was inspected only; it was not edited, implemented, or promoted to
  READY.
- No code, test, or governance file was modified by this review.
- No additional Phase 6/7 task was started.
