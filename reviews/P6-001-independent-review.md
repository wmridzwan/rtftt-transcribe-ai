# P6-001 — Independent Review

Date: 2026-09-23
Task: `tasks/P6-001-phase6-editing-domain-contract.md`
Reviewer: Claude Code (fresh independent review; no reliance on the implementer's
batch report)
Authority for review scope: `PHASE6-EDITING-DOMAIN-CONTRACT.md`; ADR-025
(D6-01..D6-09, DC-01); `DECISION-PHASE6-OWNER-DECISIONS-001`;
`tasks/P6-001-phase6-editing-domain-contract.md`;
`tasks/P6-002-revision-persistence-version-history.md`

## Verdict

**CHANGES_REQUESTED**

One BLOCKER: the revision/version model, as specified, is not internally
coherent for the exact undo-then-branch scenario the contract itself declares
legal. A domain-level fix (not a P6-002 persistence workaround) is required
before P6-002 may be promoted.

## Findings

### BLOCKER — Version numbering collides on undo-then-branch; `(transcription_id, version)` uniqueness is not preserved

**File:** [PHASE6-EDITING-DOMAIN-CONTRACT.md:37-38](PHASE6-EDITING-DOMAIN-CONTRACT.md:37),
[app/Editing/RevisionFactory.php:80-95](app/Editing/RevisionFactory.php:80)

The contract states `version` is "monotonically increasing... `(transcription_id,
version)` is unique" (§2), and explicitly declares branching legal: "undoing and
then editing branches rather than overwriting history" (§2). `RevisionFactory::derive()`
computes `version: $base->version + 1` from the **base revision's own version**,
not from the transcription's history. This is correct only for a linear
(non-branching) edit chain.

Reproduced adversarially (verified independently, not from the builder's report):

1. Create `v1` (version 1), append.
2. Derive/append `v2` (version 2, parent v1).
3. Derive/append `v3` (version 3, parent v2).
4. Undo: `activate(v2)` (CAS against `v3`) — legal per §2/§3.
5. Edit again from the now-active `v2`: `derive($v2, …)` produces version
   `$v2->version + 1 = 3`.
6. `append($v4, expectedActive: v2->revisionId)` succeeds (CAS only checks the
   active pointer, not version uniqueness) — **`v3` and `v4` now both hold
   `version = 3` for the same `transcription_id`.**

This directly contradicts §2's own uniqueness rule. Reproduced with an isolated
Pest test against `RevisionFactory` + `Tests\Support\InMemoryRevisionRepository`
(assertion `count($versions) === count(array_unique($versions))` fails: 4
revisions, 3 unique versions). Test file was written to `tests/Unit/Editing/`
for verification only and removed afterward per review instructions not to
modify tests; it is reproducible with the transcript above using only the
shipped classes.

**Why this is a BLOCKER, not a P6-002-only issue:** P6-002's own scope item 6
is "appending after an undo branches from the active revision," and its schema
(§10, carried verbatim into P6-002 AC1) adds a DB-level `unique
(transcription_id, version)` constraint. Given the current P6-001
version-derivation rule, that DB constraint and P6-002's own AC5 ("undo/redo
is a CAS pointer move across durable history") are mutually unsatisfiable for
any transcription where a user undoes and then edits again — the most basic
undo-then-edit user flow. P6-002 would hit a **raw DB unique-constraint
violation** on a request that passes CAS cleanly (the active-pointer check
succeeds; only the derived version number collides), an entirely different and
undocumented failure mode from the deliberate `RevisionConflictException`
the contract defines for stale writes. P6-002 cannot resolve this without
inventing semantics P6-001 was supposed to fix (e.g., "next version = max
existing version + 1" computed from full history, which also requires
threading repository state into what is currently a pure, persistence-free
`RevisionFactory`, or having the repository re-derive/re-stamp the version at
append time instead of trusting the immutable value object built by the
factory). The review brief for this task explicitly asks P6-002 not to invent
missing P6-001 domain semantics; this is exactly such a case.

**Amendment required before P6-002 promotion:** P6-001 must specify how
`version` is assigned when a new revision is appended after activating a
non-latest (ancestor) revision — e.g., either (a) version is the next integer
after the highest version in the transcription's full history, not
`base.version + 1`, and the repository (which has that history) — not the
pure factory — is responsible for stamping it at append time, or (b) branches
are disallowed and a new edit against a non-latest active revision must first
force a fast-forward/rebase. The contract currently implies (a) is intended
("branches rather than overwriting history") but does not specify version
assignment consistently with it.

### HIGH — Redo target is undefined once a revision has more than one child (branching)

**File:** [PHASE6-EDITING-DOMAIN-CONTRACT.md:44-49](PHASE6-EDITING-DOMAIN-CONTRACT.md:44),
[app/Editing/RevisionRepository.php](app/Editing/RevisionRepository.php)

§2 states "redo = activate a descendant revision," but once undo-then-edit
branching occurs (explicitly declared legal by the same section), a revision
can have more than one child (in the reproduction above, `v2` has children
`v3` and `v4`). "The descendant" is no longer well-defined:

- `RevisionRepository` exposes no method to enumerate a revision's children
  (only `activeFor`, `find`, `historyFor` — a flat, transcription-wide list —
  `append`, `activate`).
- `TranscriptRevision` does not track children, only `parentRevisionId`.
- Nothing in the contract states whether redo should pick the most-recently-created
  child, whether the pre-undo branch (`v3`) should be preferentially treated as
  "the" redo target, or whether creating `v4` should simply drop the notion of
  a redo target for the abandoned `v3` branch (the conventional editor
  behavior: a new edit after undo clears the redo stack).

This is precisely the ambiguity the review brief asked to be checked
("whether redo history survives or is invalidated by a branch... whether
history can become ambiguous"): it does become ambiguous, and P6-001 supplies
no rule to resolve it. P6-002 cannot safely implement even a minimal "redo"
primitive without inventing this policy itself.

**Amendment required:** P6-001 should state explicitly either (a) redo is
undefined/unsupported once a branch exists (only undo — activating a strict
ancestor — is guaranteed safe), deferring "pick among sibling branches" UI/
policy to a later task, or (b) a specific deterministic rule (e.g., "redo
activates the child with the highest version created most recently" and "a
new edit from a non-latest active revision invalidates the redo target of the
abandoned branch, but does not delete it — it remains reachable via
`historyFor`"). Right now neither is written down, and D6-05's "lightweight
history surface" (deferred to P6-008) cannot be scoped without it.

### MEDIUM — No reason-precedence rule for a single edit spanning more than one `EditKind` category

**File:** [PHASE6-EDITING-DOMAIN-CONTRACT.md:138-153](PHASE6-EDITING-DOMAIN-CONTRACT.md:138),
[app/Editing/TranslationInvalidationPolicy.php](app/Editing/TranslationInvalidationPolicy.php)

`TranslationInvalidationPolicy::reasonFor()` takes one `EditKind` and returns
one `TranslationStalenessReason`. Every kind is invalidating
(`mustMarkStale()` always `true`), so a mixed edit (e.g., a single save that
changes both `text` and `end_seconds`) is **safe** either way — the
translation becomes stale regardless of which reason is chosen. But the
contract does not say which reason (or whether multiple reasons) should be
recorded when an edit spans categories, leaving P6-005 (which owns the
persisted `staleness_reason` marker) to invent a precedence rule with no
guidance. Low risk (doesn't affect correctness of staleness itself, only the
recorded reason/audit trail), but worth fixing before P6-005 is authored so
two implementations don't diverge.

**Suggested amendment:** state an explicit precedence (e.g.,
`Structural > Timing > Textual`, reflecting "most invasive wins") or state
that P6-005 may persist a set of reasons rather than one.

### LOW — Trivial PHPStan findings in shipped test-support code (outside the project's configured PHPStan scope)

**File:** [tests/Support/InMemoryRevisionRepository.php:50](tests/Support/InMemoryRevisionRepository.php:50),
[tests/Unit/Editing/TranscriptRevisionTest.php:7](tests/Unit/Editing/TranscriptRevisionTest.php:7)

Running PHPStan (level 7, project's own `larastan` extension) scoped to these
files directly (not the project's default `paths:` in `phpstan.neon`, which
only covers `app/`) surfaces two trivial issues:

- `InMemoryRevisionRepository::historyFor()` calls `array_values()` on a value
  PHPStan already infers as a list — a no-op call.
- The test helper `editingRevision(array $segments = [])` has no
  `list<RevisionSegmentData>` PHPDoc type, so PHPStan cannot verify the
  `TranscriptRevision` constructor's `list<RevisionSegmentData>` parameter
  type at the test's call sites.

These do **not** contradict the builder's "PHPStan 0 errors" claim — the
project's `phpstan.neon` only analyzes `app/`, `bootstrap/app.php`, `config/`,
`database/`, `routes/`, so `tests/` was never in scope and the claim is
accurate as scoped. Flagged only because `InMemoryRevisionRepository` is a
shipped reference implementation of the P6-001 contract, not throwaway test
code, and future consumers may reasonably expect it to be typed cleanly. Not
blocking.

## Section-by-section verification

**1. Editing model.** Verified: `TranscriptRevision` and `RevisionSegmentData`
are immutable (`readonly` classes, no setters); `RevisionFactory` never reads
or writes Eloquent (`MachineSegmentSnapshot` is the only machine-facing type,
constructed from plain scalars/enum); `RevisionRepository::append()` is
documented and modeled as insert-only (the in-memory reference implementation
enforces this by rejecting a duplicate `revisionId`); the active pointer is
external to `TranscriptRevision` (tracked by `transcriptions.active_revision_id`,
not modeled here, consistent with "P6-001 defines the shape, P6-002 persists
it"); `null` active revision correctly means "machine source authoritative"
throughout (`RevisionRepository::activeFor()` return type, `RevisionConflictException::describe()`).
No domain type reads or assumes any specific persistence engine — confirmed by
grep: `app/Editing/` has zero references to Eloquent, `DB`, or any
`Illuminate\Database` symbol other than `Str::uuid()`.

**2. Revision identity and concurrency.** `RevisionId` is an opaque
server-generated UUID string (`Str::uuid()`), never caller-supplied, never
reused (verified: `InMemoryRevisionRepository::append()` throws if the id
already exists — a defensive but appropriate check for the reference
implementation). `version` semantics: see BLOCKER above — coherent on a
strictly linear chain, **not** coherent once branching occurs, despite
branching being explicitly declared legal by the same document.
`parent_revision_id` correctly represents ancestry (`derive()` always sets
`parentRevisionId: $base->revisionId`). New edits branch from the current
active revision by construction (callers must derive from `activeFor()`'s
result and pass its id as `expectedActiveRevisionId`, though this is a calling
convention the interface does not itself enforce — `append()` takes the
revision and the expected id as independent parameters, so a caller could in
principle derive from one revision and claim a different `expectedActiveRevisionId`;
this is a caller-discipline concern for P6-002, not a defect in the interface
contract, since the interface is a pure CAS primitive by design). Stale base
revisions fail deterministically: reproduced (`RevisionRepositoryContractTest`
"rejects a stale append without mutating history" — confirmed passing,
re-run independently). CAS semantics are explicit in the interface docblock
and enforced by the reference implementation. No silent merge exists anywhere
in the reviewed code. Undo-after-edit and branch-from-an-older-active-revision:
see BLOCKER/HIGH above — the contract permits this but its consequences
(version collision, redo ambiguity) are not fully specified.

**3. Undo/redo contract.** Undo/redo is correctly modeled as durable
active-revision-pointer movement (`RevisionRepository::activate()`), not
browser-only state — confirmed no session/cache/cookie reference anywhere in
`app/Editing/`. Previous/next selection: "previous" (undo, activating a strict
ancestor) is well-defined via `parentRevisionId` chasing. "Next" (redo) is
**not** well-defined once branching exists — see HIGH finding. Conflict
handling for two actors racing a pointer move: correctly CAS-fenced
(`RevisionConflictException` on mismatch) — reproduced via
`RevisionRepositoryContractTest` "activates a prior revision only against the
expected current revision," re-run independently.

**4. Timing invariants.** All nine listed invariants verified against
`TimingInvariants` and reproduced independently by re-running
`tests/Unit/Editing/TimingInvariantsTest.php` (8 tests, all passing): finite-only
(`is_finite` guards reject `NAN`/`INF`); non-negative timestamps; `start <= end`
(equality legal); millisecond precision is a storage-layer concern deferred to
P6-002's `decimal(12,3)` column (not enforceable in the pure PHP float domain
layer — correctly out of scope here, `float` arithmetic does not silently
introduce sub-millisecond meaningful precision the domain would need to
reject, and the contract is explicit this is a storage-precision match, not a
domain-layer runtime check); overlaps legal (`overlaps()` uses a correct
half-open-interval test, `startA < endB && startB < endA`, and a zero-length
segment never "overlaps" per this formula since `startA < endA` fails when
equal — confirmed by the shipped test `overlaps(0.0, 0.0, 0.0, 1.0) === false`);
zero-length legal but never active (`activeAt()` uses `start <= time < end`,
so `start === end` can never satisfy `time < end` when `time >= start`);
unique contiguous positions `0..n-1` (`assertValidSequence()`, reproduced by
re-running the "rejects duplicate identities, duplicate positions, and
non-contiguous positions" test); no required cross-segment monotonicity
(`assertValidSequence()` never inspects timestamps across segments, only
`position`; reproduced via the shipped "does not require cross-segment
timestamp monotonicity" test); revision timing never mutates machine-source
timing (structural: `RevisionSegmentData`/`TranscriptRevision` hold no
reference to any `TranscriptionSegment` Eloquent row, so no mutation path
exists in this layer by construction). Active-segment resolution by lowest
`position` is deterministic and matches the frozen
`App\TranscriptExperience\ActiveSegmentResolver` rule exactly (`start <= t <
end`, lowest index/position wins on overlap, ordering independent of array
input order) — compared the two implementations line-by-line; the only
difference is field name (`segment_index` vs `position`), which is the
intended and documented substitution (§5, §8). Adversarial checks performed:
overlapping segments (verified legal, resolves to lowest position); nested
overlaps (three-way overlap in the shipped test resolves correctly to position
0); equal start times (covered by the overlap formula, no special-case bug
found); zero-length segment between two active-eligible segments (traced
through `activeAt()` manually — correct, the zero-length segment can never
match and adjacent normal segments resolve normally); out-of-time-order but
position-ordered segments (shipped test confirms this is accepted and
`position` alone governs order).

**5. Navigation identity.** Correctly incorporates the P6-006 handoff:
`RevisionSegmentIdentity` is the documented and implemented navigation
identity for an edited transcript (§8); machine `segment_index` is
demonstrably provenance-only — `RevisionSegmentIdentity::forMachineSegment()`
only ever produces the initial materialization's identities
(`machine:<index>`), and nothing in `app/Editing/` re-derives navigation from
`segment_index` after that point. Compatible with the existing read-only
machine path: confirmed no changes to `ActiveSegmentResolver` or any
Phase 4 browser/view file in this commit (verified via `git show --stat`).

**6. Translation invalidation.** Canonical mapping verified exactly as
specified: `Textual → SourceTextChanged`, `Timing → TimingChanged`,
`Structural → SegmentStructureChanged` (`EditKind::stalenessReason()`).
`TranslationInvalidationPolicy::mustMarkStale()` returns `true` for all three
`EditKind` cases with no default/fallthrough branch that could return `false`
— confirmed by reading the `match` expression, which is exhaustive over the
enum and would fail to compile (PHP `match` throws `UnhandledMatchError` at
runtime, and PHPStan's enum exhaustiveness check would flag a missing case
statically) if a case were silently omitted. No silent remap path exists
anywhere in the reviewed classes — there is no method that copies translation
content across segments. Persistence/lifecycle (the actual
`translations.stale_at`/`staleness_reason` write) is correctly and explicitly
deferred to P6-005 (§7, confirmed absent from `app/Editing/` and from the
schema shape in §10, which the P6-002 contract also correctly excludes from
its own scope). Mixed-category edits: see MEDIUM finding — safe but
underspecified for reason precedence.

**7. Revision segment structure.** `RevisionSegmentIdentity` is a distinct
type from machine segment index (a wrapped opaque string vs. a plain `int`);
the only bridge is the explicit `forMachineSegment()` factory method, which is
appropriately narrow. Position uniqueness/contiguity is enforced at both
`RevisionSegmentData` construction (non-negative only) and
`TranscriptRevision` construction (`TimingInvariants::assertValidSequence()`,
full uniqueness/contiguity check) — an impossible ordering state (duplicate
position, duplicate identity, or a gap) cannot survive construction of a
`TranscriptRevision`. Text/timing/source references are represented
consistently (plain `string`/`float`/`LanguageIdentifier` value types, no
partial/optional fields that would allow an inconsistent segment). No
split/merge implementation is present or pre-authorized — confirmed no method
on any `app/Editing/` class performs a split or merge; §7 of the contract
describes the *rules* split/merge must follow (owned by P6-005) without
implementing them.

**8. Repository abstraction.** `RevisionRepository` is persistence-neutral:
five methods, no Eloquent/SQL leakage in the interface signature, confirmed by
reading the interface directly. Sufficient for P6-002 to implement against
directly (P6-002's own contract restates the same five methods with no
additions needed). Not overfitted to Eloquent/SQLite — types are
`int`/`string`/`?string`/`TranscriptRevision`, nothing storage-specific.
Explicit about CAS/active-pointer operations (`append`/`activate` both
document `@throws RevisionConflictException`). Hidden generic-CRUD semantics
that would weaken revision rules: none found — there is no `update()` or
`delete()` method on the interface, so in-place mutation of a persisted
revision is structurally impossible through this contract. One caveat already
noted under §2 above: the interface's `append(TranscriptRevision $revision,
?string $expectedActiveRevisionId)` signature does not itself enforce that
`$revision->parentRevisionId === $expectedActiveRevisionId` — a
caller could construct a revision derived from one base but assert a
different expected active id. This is consistent with keeping the interface a
minimal CAS primitive (P6-002's repository implementation is expected to
enforce this consistency check itself, matching P6-002 AC1/AC4's "no partial
write" / "reject a stale base" language), but the contract document should
say so explicitly rather than leaving it implicit — see recommendation below.

**9. Schema contract.** The three tables/columns named in §10 are
sufficient to persist everything `TranscriptRevision`/`RevisionSegmentData`
expose, and no more: `id`/`transcription_id`/`version`/`parent_revision_id`/
`created_by`/timestamps for `transcript_revisions`; `revision_id`/`segment_key`/
`position`/`start_seconds`/`end_seconds`/`text`/`language` for
`transcript_revision_segments`; `active_revision_id` nullable FK addition to
`transcriptions`. No implementation detail beyond the approved architecture
(indexes/uniqueness constraints only) is dictated. Confirmed no migration file
exists yet (`find database/migrations -iname "*revision*"` — no results).
**However**, given the BLOCKER above, the named `unique (transcription_id,
version)` constraint as currently specified will reject a legitimate
undo-then-edit sequence at the database layer; the schema section is correct
given the current (incomplete) version-assignment rule, but will need to be
revisited once that rule is amended.

**10. Phase 4 compatibility.** `data-seek-seconds` and `data-segment-language`
are correctly recorded as reserved (§12); confirmed via `git show --stat` that
this commit touched zero Blade/JS/CSS files. Phase 4 playback/range semantics:
unaffected (no changes to `ActiveSegmentResolver` or its tests; full
Phase 3/4/5 regression suite re-run independently, 691 tests / 690 passed / 1
skipped / 0 failures, identical to the builder's reported numbers). Machine
source immutability: no model, migration, or write path to
`TranscriptionSegment` was added or touched.

**11. P6-002 readiness impact.** P6-002's contract text (inspected, not
edited) correctly restates the frozen P6-001 semantics and correctly excludes
split/merge and translation-staleness persistence from its own scope. Its
scope item 6 ("appending after an undo branches from the active revision")
and AC5 ("undo/redo is a CAS pointer move across durable history") are the
exact surface where the BLOCKER above bites: P6-002 cannot fulfill both its
own AC1 (unique `(transcription_id, version)`, per the P6-001 §10 shape it is
required to implement verbatim) and AC5 without an amendment to P6-001's
version-assignment rule. P6-002 also inherits the HIGH finding's ambiguity
(no domain-level way to enumerate a revision's children, needed for any redo
primitive) — though P6-002's stated scope only claims "durable history / undo
base," not redo UI, so this may be acceptable to defer to a later task if
P6-001 says so explicitly, which it currently does not.
**P6-002 may not safely be promoted to READY on the current P6-001 text.**

## Verification performed (reproduced vs. inspected)

**Reproduced independently (commands re-run by this reviewer, not taken from
the builder's report):**
- `php artisan test --compact tests/Unit/Editing` → 30 tests, 30 passed, 122
  assertions, 0 failures (matches builder's report exactly).
- `php artisan test --compact` (full suite) → 691 tests, 690 passed, 1
  skipped, 0 failures, 2520 assertions (matches builder's report exactly).
- `vendor/bin/pint --test app/Editing tests/Unit/Editing
  tests/Support/InMemoryRevisionRepository.php` → passed, no style violations.
- `vendor/bin/phpstan analyse` (project default config: `app/`,
  `bootstrap/app.php`, `config/`, `database/`, `routes/`, level 7) → 0 errors
  (matches builder's "PHPStan 0" claim as scoped).
- `vendor/bin/phpstan analyse app/Editing tests/Unit/Editing
  tests/Support/InMemoryRevisionRepository.php` (reviewer-only extended scope,
  beyond the project's configured paths) → 3 trivial findings in `tests/`,
  reported as LOW above; 0 findings in `app/Editing` itself.
- Adversarial Pest probe (written, run, and removed by this reviewer; not
  committed) reproducing the undo-then-branch version collision described in
  the BLOCKER finding — failed as predicted (`4` revisions, `3` unique
  versions).
- `git show --stat` on the P6-001 commit (`49c563d`) — confirmed the
  file list is exactly `PHASE6-EDITING-DOMAIN-CONTRACT.md` +
  `app/Editing/*` + `tests/Unit/Editing/*` + `tests/Support/*` +
  `reviews/pre-review/P6-001-pre-review.md` + the task file; zero
  migration/model/route/view/JS files touched.
- `grep -rn "active_revision_id"` / migration and model searches — confirmed
  no schema, model, or route artifacts exist yet for the revision layer.

**Inspected only (read, traced, or reasoned about; not independently executed
beyond reading):**
- `PHASE6-EDITING-DOMAIN-CONTRACT.md` full text against ADR-025/D6-01..D6-09.
- All twelve `app/Editing/` classes/enums/interface, read in full.
- `tests/Support/InMemoryRevisionRepository.php` and `EditingFixtures.php`,
  read in full.
- All seven test files under `tests/Unit/Editing/`, read in full (the shipped
  assertions were also reproduced by running them, above).
- `tasks/P6-002-revision-persistence-version-history.md`, read in full, for
  readiness-impact analysis (not edited, not implemented, not promoted).
- `app/TranscriptExperience/ActiveSegmentResolver.php` and
  `app/Transcription/LanguageIdentifier.php`, read for cross-contract
  consistency checks (§5, §8, §10 language vocabulary).
- `app/Policies/TranscriptionPolicy.php` — confirmed it exists and is
  referenced by the contract; did not re-audit its internal authorization
  logic (out of scope: unchanged by this commit, previously reviewed under
  its own task).

**Not reproduced:** browser evidence (correctly not required — this task
changes no browser behavior, confirmed by the file-list check above).

## Answers to the seven required questions

1. **P6-001 verdict:** CHANGES_REQUESTED.
2. **Severity-labelled findings:** one BLOCKER (version-collision on
   undo-then-branch), one HIGH (undefined redo target once branching occurs),
   one MEDIUM (no reason-precedence rule for mixed-category edits), one LOW
   (trivial PHPStan findings in test-support code outside the project's
   configured analysis scope).
3. **Is the editing/revision model internally coherent?** Not fully. It is
   coherent for a strictly linear edit history. It becomes incoherent
   (duplicate version numbers, undefined redo target) under the
   undo-then-branch scenario the contract itself explicitly declares legal
   in the same section that defines version uniqueness.
4. **Are timing semantics sufficiently explicit?** Yes. §5's nine invariants
   are unambiguous, internally consistent, correctly implemented, and
   verified against the frozen Phase 4 `ActiveSegmentResolver` rule with no
   discrepancy found.
5. **Is translation invalidation safe?** Yes, for single-category edits — no
   edit kind can leave a translation silently current, and no remap path
   exists. Underspecified (not unsafe) for edits that span more than one
   category at once (MEDIUM finding).
6. **May P6-002 safely proceed after HPO promotion?** Not on the current
   text. P6-002's own scope item 6 and AC5 sit directly on the BLOCKER above;
   promoting P6-002 to READY before this is resolved would hand it an
   unsatisfiable pair of acceptance criteria (unique version constraint vs.
   undo-then-edit support).
7. **Contract amendment required before P6-002 implementation:** Yes — two
   amendments to `PHASE6-EDITING-DOMAIN-CONTRACT.md` §2 (and correspondingly
   `app/Editing/RevisionFactory.php`'s `derive()` contract) are needed before
   P6-002 may be promoted: (a) specify how `version` is assigned so that
   `(transcription_id, version)` uniqueness holds across branches (e.g.,
   version = next integer after the highest existing version in the
   transcription's full history, assigned by the repository at append time
   rather than baked into the immutable value object by the pure factory
   before persistence is known to succeed), and (b) either explicitly scope
   "redo" as undefined/unsupported across a branch point for now, or define a
   deterministic sibling-selection rule and the repository method needed to
   support it.

## Explicitly not done (per review scope)

- P6-001 is not marked DONE or VERIFIED by this review.
- P6-002 was inspected only; it was not edited, implemented, or promoted.
- No code, test, or governance file was modified by this review. The
  adversarial probe test used for reproduction was written, executed, and
  removed; the working tree was confirmed clean before and after.
- No additional Phase 6/7 task was started.
