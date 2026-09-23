# P6-002 — Revision Persistence / Version History

## Status

CONTRACT_AUTHORED_PENDING_P6-001_CLOSURE — canonical contract authored
2026-09-23 under `DECISION-PHASE6-AUTHORIZATION-001` and the adopted D6
decisions (`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025). **Not READY; not
implemented.**

- P6-002 is `EARLY_START_ELIGIBLE` (`PHASE6-7-ELIGIBILITY-MATRIX.md`) with its
  own bounded contract + an explicit HPO READY promotion as prerequisites.
- Its semantics are fixed by P6-001 (`PHASE6-EDITING-DOMAIN-CONTRACT.md`), which
  is currently `IMPLEMENTED_PENDING_REVIEW`. This contract is reconciled against
  those semantics; implementation must not begin until P6-001 is independently
  VERIFIED and the HPO promotes P6-002 to READY.
- Do not implement P6-002 in the same batch as its contract.

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
- `RevisionId` opaque identity, monotonic `version` per transcription,
  `parent_revision_id`, durable history (D6-02);
- active revision pointer (`transcriptions.active_revision_id`, null = machine
  source authoritative);
- optimistic concurrency: base revision id must equal the active revision id,
  otherwise `RevisionConflictException`; no silent merge / last-writer-wins;
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
   `activeFor`, `find`, `historyFor`, `append`, `activate`, all CAS-fenced and
   append-only.
4. **Machine-source materialization:** map completed `TranscriptionSegment` rows
   into `MachineSegmentSnapshot`s and materialize the initial revision through
   `RevisionFactory`, preserving machine timing/text/language and machine
   provenance identities (`machine:<index>`).
5. **Optimistic concurrency:** stale base revision ids are rejected with
   `RevisionConflictException` without partial writes; transactions fence the
   revision row and its segments and the active pointer update.
6. **Durable history / undo-redo base:** activating an ancestor revision is a
   compare-and-set pointer move leaving all rows intact; appending after an
   undo branches from the active revision.
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

- P6-001 = `IMPLEMENTED_PENDING_REVIEW`; **implementation blocked until P6-001
  is independently VERIFIED** and its semantics are frozen.
- HPO READY promotion for P6-002 (required; not granted by this contract).
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
   pointer move across durable history.
6. Machine materialization copies machine timing/text/language verbatim and
   assigns machine provenance identities; machine rows are never written.
7. Ownership: unauthorized actors cannot append/activate; owner/admin can.
8. Unicode round-trip for `ms/en/zh/ta/und`; overlap/zero-length sequences
   persist and reload unchanged.
9. No split/merge or translation-staleness persistence introduced.
10. Relevant tests pass; Pint clean; PHPStan 0 errors; full regression green.

## Verification Requirements

- Feature tests against the real database (SQLite in-memory) for persistence,
  CAS, ownership, materialization, and reload.
- Unit tests for model/factory mapping.
- Full PHP suite regression; Pint; PHPStan.
- No browser evidence (no browser behavior changed by P6-002).

## Expected Reviewer

Claude Code (independent).

## Browser Evidence Required

No.

## Owner Decision Dependencies

D6-01, D6-02, D6-03, D6-04 (adopted); ADR-025; P6-001 frozen semantics.

## Completion

Required flow: READY → IN_PROGRESS → REVIEW (IMPLEMENTED_PENDING_REVIEW) →
VERIFIED → DONE. Implementation owner must not self-verify. P6-002 must not
reach READY until P6-001 is VERIFIED and the HPO grants the READY promotion.