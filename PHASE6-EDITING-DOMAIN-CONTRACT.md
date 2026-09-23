# Phase 6 — Advanced Transcript Editing Domain Contract

Task: `tasks/P6-001-phase6-editing-domain-contract.md`
Status: Canonical contract base authored 2026-09-23 under
`DECISION-PHASE6-AUTHORIZATION-001`; adopted D6 decisions (`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025).
Owner: OpenCode (implementation), Claude Code (independent review).

This document is the **canonical Phase 6 editing domain contract**. It fixes the
semantics that P6-002+ implement. It does **not** build the editing UI and does
not perform any persistence migration.

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
- `version`: monotonically increasing integer per transcription, starting at 1.
  `(transcription_id, version)` is unique.
- `parent_revision_id`: the revision a new revision was derived from (nullable
  for the initial materialization).
- `created_by`: the acting user id.
- `created_at`: server timestamp.

History is **durable**: every revision row and its segments persist; undo/redo
navigates the active-revision pointer across that durable history (undo =
activate an ancestor revision; redo = activate a descendant revision) and never
rewrites revision rows. A new edit always appends a new revision derived from
the *current* active revision, so undoing and then editing branches rather than
overwriting history.

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
- The active revision id is the revision token. No separate token is introduced.

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
| `version` | unsigned integer | monotonic per transcription, starts at 1 |
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
2. undo/redo derives from durable history and survives reload;
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