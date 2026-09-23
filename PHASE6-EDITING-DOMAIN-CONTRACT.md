# Phase 6 — Advanced Transcript Editing Domain Contract

Task: `tasks/P6-001-phase6-editing-domain-contract.md`
Status: **FROZEN** — canonical contract base authored 2026-09-23 under
`DECISION-PHASE6-AUTHORIZATION-001`; adopted D6 decisions
(`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025); corrected for the independent
review BLOCKER/HIGH/MEDIUM findings; P6-001 independently VERIFIED and closed
DONE (`DECISION-P6-001-CLOSURE-001`). The semantics in this document are frozen
inputs for downstream Phase 6 work (P6-002+); downstream tasks must consume them
without redefining them.
Owner: OpenCode (implementation), Claude Code (independent review).

This document is the **canonical Phase 6 editing domain contract**. It fixes the
semantics that P6-002+ implement. It does **not** build the editing UI and does
not perform any persistence migration.

## 0. Frozen P6-001 inputs (HPO-recorded 2026-09-23)

Recorded by `DECISION-P6-001-CLOSURE-001` as frozen inputs for downstream Phase 6
work. Downstream tasks consume these; they must not redefine them.

- **Editing model:** immutable machine transcript source; append-only editable
  revisions; one active revision pointer; `null` active revision means machine
  source; durable revision graph/history.
- **Version semantics:** transcription-scoped and monotonic; independent of
  ancestry; branching never reuses an existing version; `parent_revision_id`
  represents ancestry; persistence must enforce `(transcription_id, version)`
  uniqueness transactionally.
- **Concurrency:** stale base revisions fail; no silent merge; active-pointer CAS
  and version uniqueness are separate invariants; persistence races surface as
  domain conflict, not raw database uniqueness failures.
- **Undo/redo:** durable active-pointer movement; old descendants remain durable;
  a new edit after undo creates a new branch; the prior redo path is invalidated
  for automatic redo semantics; `redoTargetFor()` is valid only where a unique
  deterministic child exists.
- **Timing:** finite and non-negative; `start <= end`; millisecond precision;
  overlap legal; zero-length legal but never active; positions unique and
  contiguous; no cross-segment timestamp monotonicity; active resolution uses the
  lowest position; revision timing never mutates machine-source timing.
- **Navigation:** edited transcript navigation follows active revision segment
  identity/position; do not assume machine `segment_index` after structural
  editing.
- **Translation invalidation:** canonical precedence
  `SegmentStructureChanged > TimingChanged > SourceTextChanged`; all relevant
  edit kinds remain invalidating.

## 1. Model: immutable source + editable revision

The completed machine transcription (`transcriptions` + `transcription_segments`)
is an **immutable source layer** (D6-01). Once a transcription reaches
`completed`, the machine text, per-segment language, and segment timestamps are
never overwritten in place. ADR-018 B3-04 (immutable historical attempts)
continues to apply to the machine original.

User edits live in an explicit **editable revision layer**:

- A **revision** is an immutable snapshot of the ordered revision segments plus
  its provenance (which revision it was derived from, who created it, when).
- Editing produces a **new** revision; existing revisions are never mutated.
- Exactly one revision may be the **active/current revision** of a transcription
  at a time (D6-05).
- When no active revision exists, presentation/export fall back to the immutable
  machine source. The machine original is always recoverable.

Ownership is inherited from the transcription: mutations require
`TranscriptionPolicy::update` (owner or admin).

## 2. Revision identity, versions, and durable history (D6-02)

- `RevisionId`: opaque, server-generated, globally unique (UUID string). It is
  the stable identity of a revision and is **not** reused.
- `version`: a **monotonic sequence scoped to the transcription**, independent
  of revision ancestry. Every newly created revision receives a version
  strictly greater than every version already allocated for that transcription
  (starting at 1). `(transcription_id, version)` is unique. Branching from an
  older active revision never reuses an earlier version number.
- `parent_revision_id`: **ancestry** — the revision a new revision was derived
  from (nullable for the initial materialization). Ancestry and version are
  independent: `parent_revision_id` records provenance; `version` records
  creation order.
- `created_by`: the acting user id.
- `created_at`: server timestamp.

Version allocation is **not** derived from the base revision. A new revision
obtains the next transcription-scoped version from a
`RevisionVersionAllocator` (`RevisionVersionAllocator::nextVersionFor()`; the
repository implements it in P6-002), so an edit made after an undo receives a
fresh version instead of colliding with the abandoned descendant's version. The
repository re-validates monotonicity at append time and rejects a
non-monotonic version (`RevisionConflictException`), so `(transcription_id,
version)` cannot collide across branches.

### Durable revision graph vs. user undo/redo path

Two distinct structures must not be conflated:

- **Durable revision graph/history.** Every revision row and its ancestry is
  append-only and never rewritten or deleted. `parent_revision_id` expresses
  branch ancestry; a revision may have more than one child once an
  undo-then-edit branch exists.
- **User undo/redo navigation path.** Undo/redo is *derived* from that graph as
  active-revision-pointer movement; it is not browser-only state and never
  rewrites history. Undo activates a strict ancestor. Redo activates the
  deterministic redo target: the active revision's **unique** child.

A new edit always appends a new revision derived from the *current* active
revision, so undoing and then editing **branches** rather than overwriting
history. Creating a new revision from an undone/non-tip active revision
**invalidates the prior redo path** for user redo semantics:

- historical revisions remain durable and visible in revision history;
- old descendants are **not** deleted;
- once a new edit branches from the active historical revision, that revision
  has more than one child, so automatic redo must not choose among siblings:
  `RevisionRepository::redoTargetFor()` returns `null`;
- the new branch becomes the current forward history (the append moves the
  active pointer onto the new revision);
- explicit historical revision selection may remain possible later (P6-008),
  but it is **not** automatic redo.

Branch ancestry is queryable through `RevisionRepository::childrenOf()`; redo
never guesses among siblings.

## 3. Active revision and optimistic concurrency (revision token)

- The active revision is referenced by `transcriptions.active_revision_id`
  (additive, nullable). `null` means "machine source is authoritative".
- Every edit request must state the **base revision id** it was composed
  against. An edit is applied only if the stated base equals the current active
  revision id. Otherwise the write is rejected as a **stale-write conflict**
  (`RevisionConflictException`); there is no silent merge and no last-writer
  wins.
- Activating a revision is itself a compare-and-set against the current active
  revision id.
- Appending a revision is compare-and-set against the current active revision
  id **and** enforces branch ancestry: the new revision's `parent_revision_id`
  must equal the base revision id it was composed against (both `null` for the
  initial materialization). A new edit therefore always branches from the
  current active revision.
- Append additionally re-validates transcription-scoped monotonicity: a version
  that is not strictly greater than every version already allocated for the
  transcription is rejected with `RevisionConflictException`.
- The active revision id is the revision token. No separate token is introduced.
- Active-pointer movement is deterministic: appending sets the active revision
  to the newly appended revision (the new branch is the current forward
  history); activating sets it to the explicitly requested revision.

## 4. Revision segment: identity and ordering

- `RevisionSegmentIdentity` is an opaque, non-empty string key, unique within a
  revision. It is the **navigation and selection identity** for the active
  revision (see §8).
- `position` is a non-negative integer that defines presentation/reading order.
  Positions within a revision are unique and **contiguous from 0**
  (`0..n-1`). Ordering is by `position`, never by array index or timestamp.
- Timestamps are **not** required to be monotonic across positions (see §5).

## 5. Timing invariants (D6-03) — explicit policy

All timing values are seconds with at most millisecond precision, matching the
frozen Phase 3/4 storage precision (`decimal(12,3)`).

Per-segment validation (rejects with `InvalidArgumentException`):

1. `start_seconds` and `end_seconds` are finite numbers (not NaN/±INF).
2. Both are `>= 0`.
3. `start_seconds <= end_seconds` (equality is the zero-length case).

Cross-segment policy (explicit, no longer implicit):

- **Overlaps are LEGAL.** A revision may contain segments whose
  `[start, end)` intervals overlap. This preserves machine transcripts that
  already contain overlaps; rejecting them would make some machine sources
  unrepresentable as a revision.
- **Overlap resolution** follows the frozen Phase 4 rule: when resolving an
  active segment for a playback time, the segment with the **lowest `position`**
  wins. (Phase 4 used the lowest `segment_index`; revisions use the revision's
  ordered `position`.)
- **Zero-length segments (`start == end`) are LEGAL.** They are valid, editable,
  exportable rows but are **never active** for playback, mirroring
  `App\TranscriptExperience\ActiveSegmentResolver` (`start <= t < end`).
- **Cross-segment timestamp monotonicity is NOT required.** Ordering is defined
  solely by `position`; timestamps may overlap or be non-monotonic.
- **Relation to the immutable machine source:** a revision's timing edits are
  stored only in the revision layer. They never mutate
  `transcription_segments.start_seconds` / `end_seconds`. When a revision is
  first materialized from the machine source, machine timestamps are copied
  verbatim; the machine timestamps remain the provenance anchor.

## 6. Editable fields and validation bounds (D6-01 / D6-03)

| Field | Editable | Bound |
|---|---|---|
| segment text | yes | any Unicode string, including empty (an empty text is distinct from deletion) |
| segment `start_seconds` | yes | finite, `>= 0`, `<= end_seconds`, ms precision |
| segment `end_seconds` | yes | finite, `>= start_seconds`, ms precision |
| segment language | no (carried) | copied from machine source on materialization; structural edits carry per-segment language (D6-04) |
| segment `position`/identity | structural only | split/merge/redact operations (P6-005) |

Uniqueness and order are enforced by §4.

## 7. Split / merge, stable identity, and translation invalidation (D6-04)

Deferred details are owned by P6-005; the contract-level rules fixed here:

- **Split** replaces one revision segment with two ordered segments inserted at
  the original position; the two new segments receive **new identities**. The
  original identity is retired.
- **Merge** replaces two or more adjacent revision segments with one; the merged
  segment receives a **new identity** positioned at the earliest replaced
  position.
- **Boundary monotonicity:** a structural edit must not produce positions that
  violate §4 (unique, contiguous `0..n-1`) or per-segment timing validity (§5).
- **Per-segment language carry:** split/merge preserve the contributing source
  language(s); a merge of differing languages carries the language of the
  earliest contributing segment and is flagged as mixed-language provenance.
- **Never silently preserve a translation as current.** A structural edit that
  changes segment identity or textual source semantics, a textual edit, or a
  timing edit marks every affected translation **explicitly stale**. Translation
  content is **never silently remapped** across changed segment structure.

### Translation staleness policy

`TranslationInvalidationPolicy` classifies every edit kind as invalidating, with
an explicit reason:

| Edit kind | Staleness reason | Translation may remain current? |
|---|---|---|
| Textual (segment text changed) | `SourceTextChanged` | no |
| Timing (`start`/`end` changed) | `TimingChanged` | no |
| Structural (split/merge/reorder/insert/delete) | `SegmentStructureChanged` | no |

Every edit kind is invalidating; the policy exposes `reasonFor()` and
`mustMarkStale()` and has no "leave current" branch. The persisted marker shape
(`translations.stale_at`, `translations.staleness_reason`) is owned and
implemented by the P6-005 translation-invalidation contract; P6-001 only fixes
the policy.

#### Mixed-category edit precedence

When one edit operation spans more than one category (for example a single save
that changes both segment text and timing), the canonical reason recorded is
selected by a fixed precedence — **most invasive wins**:

`SegmentStructureChanged` > `TimingChanged` > `SourceTextChanged`

That is: any structural identity/split/merge-style change wins; otherwise a
timing change wins over a text-only change; otherwise the source-text change is
used. This precedence selects only the canonical staleness reason (audit/display
trail); **every applicable edit kind remains translation-invalidating**, and no
translation is ever silently preserved as current. The rule is implemented by
`TranslationInvalidationPolicy::reasonForKinds()` (and
`EditKind::precedence()`).

## 8. Navigation identity over edited transcripts

Incorporates the P6-006 finding: after split/merge, machine `segment_index` is
no longer a safe navigation identity.

- Navigation, selection, filtering, and active-segment resolution over an edited
  transcript use the **active revision's** `RevisionSegmentIdentity` and
  `position`.
- Machine `segment_index` is provenance only; it is **not** the navigation
  identity once an editable revision exists.
- If no active revision exists, navigation uses the machine source order
  (Phase 4 behavior, unchanged).

## 9. Export / consumption semantics

- When an active revision exists, the active revision is authoritative for
  presentation, copy, and export (TXT/SRT/VTT/DOCX), preserving the revision's
  order, text, language, and timing.
- When no active revision exists, the machine source remains authoritative
  (unchanged Phase 3/4/5 behavior).
- The machine original is always recoverable regardless of revision state.
- Exports never read a stale/mixed revision; a stale-write conflict blocks the
  write, not the read.

## 10. Additive schema shape (named for P6-002; no migration here)

P6-002 will add the following additive shape. No column on
`transcriptions`/`transcription_segments` is dropped or semantically mutated.

`transcript_revisions`

| Column | Type | Notes |
|---|---|---|
| `id` | string (uuid), primary | server-generated revision id |
| `transcription_id` | bigint FK → `transcriptions`, cascade delete | owning transcription |
| `version` | unsigned integer | monotonic transcription-scoped sequence, independent of ancestry, starts at 1; next value allocated via `RevisionVersionAllocator` and re-validated at append |
| `parent_revision_id` | string nullable | prior revision |
| `created_by` | bigint FK → `users` | actor |
| `created_at` / `updated_at` | timestamps | server time |
| unique | `(transcription_id, version)` | prevents version collisions |

`transcript_revision_segments`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint, primary | |
| `revision_id` | string FK → `transcript_revisions`, cascade delete | |
| `segment_key` | string | `RevisionSegmentIdentity` |
| `position` | unsigned integer | contiguous `0..n-1` per revision |
| `start_seconds` | decimal(12,3) | |
| `end_seconds` | decimal(12,3) | |
| `text` | text | |
| `language` | string(16) | BCP 47 vocabulary `ms/en/zh/ta/und` |
| unique | `(revision_id, segment_key)` | stable identity |
| unique | `(revision_id, position)` | ordering |

`transcriptions` (additive only)

| Column | Type | Notes |
|---|---|---|
| `active_revision_id` | string nullable FK → `transcript_revisions` | null = machine source authoritative |

Owned by P6-005 (not P6-001/P6-002): `translations.stale_at` (timestamp,
nullable) and `translations.staleness_reason` (string, nullable).

## 11. Phase 6 integration-gate checklist (P6-009)

Recorded here; executed at the Phase 6 terminal gate:

1. edit → persist → reload round-trips (text, timing, language carried);
2. undo/redo derives from durable history and survives reload; versions remain
   unique per transcription across undo-then-branch, and automatic redo is
   unavailable (not guessed) at a branch point;
3. timing edits validated under §5 (reject invalid; accept overlap/zero-length);
4. split/merge alignment, identity rotation, and language carry;
5. source/translation comparison reflects the active revision;
6. exports after edit reflect the active revision; machine original recoverable;
7. stale-write conflict rejected (optimistic concurrency);
8. Unicode round-trip for `ms/en/zh/ta/und`;
9. Phase 3/4/5 regression (including reserved Phase 4 hooks) green;
10. no D6-08/D6-09 feature introduced.

## 12. Reserved compatibility hooks

`data-seek-seconds` and `data-segment-language` are **reserved Phase 4 browser
selectors**. Editing/revision/split-merge UI must not reuse them on container or
row elements; any migration of these hooks requires a separately verified change.

## 13. Non-scope

- No schema migration, model, route, controller, view, or JavaScript in P6-001.
- No translation-invalidation persistence (P6-005).
- No UI (P6-003/P6-004/P6-006/P6-007/P6-008).
- No change to frozen Phase 3/4/5 contracts; no tenancy/authorization redesign.