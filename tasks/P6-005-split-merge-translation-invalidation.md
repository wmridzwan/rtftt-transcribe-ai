# P6-005 — Split / Merge + Translation Invalidation

## Status

DONE — closed by the Human Product Owner on 2026-09-25
(`DECISION-P6-005-CLOSURE-001`) on the basis of the fresh independent review
VERIFIED verdict (`reviews/P6-005-INDEPENDENT-REVIEW.md`; no BLOCKER/MAJOR;
all 14 acceptance criteria PASS; MINOR-1/OPTIONAL-1 non-blocking, preserved in
the review artifact). Previous status `IMPLEMENTED_PENDING_REVIEW`
(2026-09-24) → `VERIFIED` (2026-09-25 review) → `DONE` (HPO closure).

Promoted to READY by the Human Product Owner under
`DECISION-P6-005-READY-001`, then implemented. The five surfaced owner decisions
are DECIDED and this canonical contract is reconciled to incorporate all of them:

- `DECISION-P6-005-SPLIT-BOUNDARY-001` (strict interior split boundary);
- `DECISION-P6-005-MERGE-JOIN-001` (plain-space join; earliest/latest-position
  timing);
- `DECISION-P6-005-LANGUAGE-PROVENANCE-001` (explicit mixed-language provenance);
- `DECISION-P6-005-STALENESS-LIFECYCLE-001` (persisted staleness lifecycle);
- `DECISION-P6-005-SCHEMA-001` (minimum additive schema).

P6-005 was `IMPLEMENTED_PENDING_REVIEW` (not self-verified, not DONE) pending
the fresh independent review (Claude Code). That review is complete
(`reviews/P6-005-INDEPENDENT-REVIEW.md`, VERIFIED, 2026-09-25) and the HPO has
closed P6-005 DONE (`DECISION-P6-005-CLOSURE-001`). Pre-review artifact:
`reviews/pre-review/P6-005-pre-review.md`; browser evidence:
`verification/p6-005/P6-005-BROWSER-VERIFICATION-EVIDENCE.md`; batch report:
`PHASE6-P6-005-IMPLEMENTATION-BATCH-REPORT.md`.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 6 — Advanced Transcript UX (ADR-019 boundary; ADR-025 D6-04).

## Objective

Deliver user-facing **structural segment editing (split / merge)** plus the
canonical **persisted translation-invalidation** state, built entirely on the
frozen revision foundation:

- split one active-revision segment into two, and merge adjacent active-revision
  segments into one, as append-only revisions;
- assign new opaque revision segment identities (never reuse machine
  `segment_index`);
- never mutate the immutable machine source or any prior revision;
- persist explicit translation invalidation for affected translations with the
  frozen reason taxonomy and precedence;
- never rewrite or silently remap Phase 5 translation segments.

## Frozen foundation consumed (must not be redefined)

- `PHASE6-EDITING-DOMAIN-CONTRACT.md` (FROZEN):
  - §2 durable revision graph / versions / ancestry;
  - §3 active pointer + expected-base compare-and-set;
  - §4 `RevisionSegmentIdentity` (opaque, unique within a revision) and
    contiguous `position` (`0..n-1`), ordering by `position` only;
  - §5 D6-03 timing invariants (finite, non-negative, `start <= end`, ms
    precision, overlap legal, zero-length legal but never active, no cross-segment
    monotonicity);
  - §7 split/merge identity, position, boundary validity, and per-segment language
    carry; the translation-invalidation policy and mixed-category precedence
    `SegmentStructureChanged > TimingChanged > SourceTextChanged`;
  - §8 navigation identity over edited transcripts;
  - §10 additive schema shape (including `translations.stale_at` /
    `translations.staleness_reason` owned by P6-005);
  - §12 reserved Phase 4 hooks.
- P6-002 (`DECISION-P6-002-CLOSURE-001`): `RevisionService`,
  `RevisionFactory`, `RevisionRepository` / `EloquentRevisionRepository`
  (append-only, expected-base CAS, monotonic version), `RevisionConflictException`.
- P6-003 (`DECISION-P6-003-CLOSURE-001`): text editing + undo/redo; the
  `EditKind::Textual` path.
- P6-004 (`DECISION-P6-004-CLOSURE-001`): timing editing + validation; the
  `EditKind::Timing` path; timing composer/validation patterns.
- `App\Editing\EditKind` / `TranslationInvalidationPolicy` /
  `TranslationStalenessReason`: the frozen taxonomy and precedence.
- Phase 5 translation identity (ADR-022, D5-01/D5-03): `translations`
  (`transcription_id`, `target_language`, `status`, …; one active translation per
  `(transcription_id, target_language)`), and `translation_segments`
  (`translation_id`, `segment_index` aligned to machine
  `transcription_segments.segment_index`). There is no revision-linked
  translation identity.

P6-005 may consume these interfaces. It must not change version/ancestry/redo
semantics, the timing invariants, or the translation-invalidation taxonomy.

## Split semantics

A **split** replaces one revision segment with two ordered revision segments,
inserted at the original position, within a **new** revision derived from the
active base. The prior revision is never mutated.

Input (per operation):

- `expected_base`: the active revision id the operation was composed against;
- `segment`: the `RevisionSegmentIdentity` of the source revision segment;
- a boundary time `t` (seconds, ms precision) and a text boundary offset `k`
  (Unicode code points).

Rules:

- **Identity.** The two resulting segments receive **two new, opaque**
  `RevisionSegmentIdentity` values (unique within the revision), generated by the
  structural-identity factory. The original identity is retired from the new
  revision. A newly created revision segment must **not** reuse a
  `machine:<index>` identity or any prior identity.
- **Positions.** If the source segment is at position `p`, the first child takes
  `p` and the second takes `p+1`; every subsequent position shifts `+1`. The
  result remains contiguous `0..n-1` with unique identities and positions.
- **Text distribution.** The first child receives `text[0, k)`, the second
  `text[k, length)`, preserving order and Unicode code-point boundaries.
- **Timing distribution.** The first child is `[start, t]`, the second `[t, end]`.
  Both must satisfy the frozen per-segment timing invariants.
- **Source-language metadata.** Both children carry the split source segment's
  language marker verbatim (frozen P6-001 §7;
  `DECISION-P6-005-LANGUAGE-PROVENANCE-001`). No new language is inferred, and no
  mixed-language provenance is propagated to the children (the provenance
  representation describes a merge composition only).
- **Parent/base expectation.** The new revision's `parent_revision_id` is the
  stated `expected_base`; the append is compare-and-set against the current active
  revision (P6-001 §3). A stale base is the canonical conflict.
- **Append-only.** A split always appends a new revision; existing revision rows
  and their segments are immutable.
- **Classification.** A split is `EditKind::Structural` →
  `TranslationStalenessReason::SegmentStructureChanged`.

### Boundary policy (`DECISION-P6-005-SPLIT-BOUNDARY-001`)

Split is allowed **only at a strict interior boundary**:

- timing: `start < t < end` (a zero-length source segment cannot be split);
- text: `0 < k < length(text)` (an empty source text cannot be split).

A split at the beginning (`t == start` or `k == 0`) or at the end (`t == end` or
`k == length`) is rejected with a domain validation error; the operation must
produce two meaningful (non-degenerate) child segments. Degenerate structural
splits — zero-duration or empty-text children created merely to permit a boundary
split — are not allowed. This is an explicit per-split rule, **not** a
cross-segment monotonicity constraint, and it does not change the general P6
timing rule that zero-length segments may exist through other valid editing
operations.

## Merge semantics

A **merge** replaces an ordered run of two or more **adjacent** revision segments
with one segment, at the earliest replaced position, within a **new** revision
derived from the active base. The prior revision is never mutated.

Input (per operation):

- `expected_base`: the active revision id the operation was composed against;
- an ordered run of `RevisionSegmentIdentity` values at contiguous positions
  `p..p+m-1` (`m >= 2`).

Rules:

- **Adjacency.** Only adjacent revision segments may merge (frozen P6-001 §7). A
  non-adjacent or gapped run is rejected with a domain validation error; the
  segments must be contiguous in `position`.
- **Ordering.** Contributing segments are processed in `position` order.
- **Identity.** The merged segment receives **one new, opaque**
  `RevisionSegmentIdentity` at the earliest replaced position `p`; the
  contributing identities are removed only from the **new** revision
  representation. No machine `segment_index` identity is reused.
- **Positions.** The merged segment takes `p`; every subsequent position shifts
  `-(m-1)`; the result remains contiguous `0..n-1`.
- **Text joining (`DECISION-P6-005-MERGE-JOIN-001`).** Contributing texts are
  joined in `position` order using exactly one canonical plain-space separator:
  `segment1_text + " " + segment2_text [+ ...]`. Contributor text is **not**
  trimmed or rewritten; there is no punctuation-aware rewriting and no
  sentence-structure inference. The join is deterministic.
- **Resulting timing (`DECISION-P6-005-MERGE-JOIN-001`).** `start` = start of the
  earliest-position contributing segment; `end` = end of the latest-position
  contributing segment. The result must satisfy the frozen per-segment timing
  invariants.
- **Language metadata (`DECISION-P6-005-LANGUAGE-PROVENANCE-001`).** If every
  contributor has the same language, the merged segment retains that language. If
  contributors contain different language markers, the merged segment language is
  `und` and the original contributor-language provenance is retained explicitly in
  the revision-segment provenance representation (ordered list of contributor
  markers). The first/earliest language is never silently selected, and no
  automatic language redetection occurs.
- **Zero-length / overlap implications.** Contributing zero-length or overlapping
  segments are legal; the merged result must satisfy per-segment timing validity.
  No cross-segment monotonicity constraint may be introduced merely because the
  edit is structural.
- **Append-only.** A merge always appends a new revision; all old revisions and
  their segments remain durable.
- **Classification.** A merge is `EditKind::Structural` →
  `TranslationStalenessReason::SegmentStructureChanged`.

## Language provenance representation

`transcript_revision_segments` gains one explicit nullable representation
(`language_provenance`), storing the ordered contributor language markers
(`["en","ms"]`, …) when and only when a merge combined differing languages.

- Nullable when unnecessary (machine materialization, text/timing edits, splits,
  same-language merges).
- Deterministic and ordered by contributor position.
- Independent of machine `segment_index`.
- Validated at the domain boundary; not a generic metadata dumping field.
- Rendered `language` remains the BCP 47 vocabulary value (`ms/en/zh/ta/und`);
  `und` plus non-null provenance is the explicit mixed-language case.

## Translation-invalidation persistence

P6-005 is the canonical owner of persisted translation staleness/invalidation.

Persisted marker (`DECISION-P6-005-SCHEMA-001`), additive on `translations`:

- `stale_at` — nullable timestamp;
- `staleness_reason` — nullable string; one of the frozen
  `TranslationStalenessReason` values;
- `stale_caused_by_revision_id` — nullable reference to the revision responsible
  for the stored canonical invalidation reason.

The taxonomy and precedence are **not** redefined:

| Edit kind | Stored reason |
|---|---|
| Textual (segment text changed) | `SourceTextChanged` |
| Timing (`start`/`end` changed) | `TimingChanged` |
| Structural (split/merge/reorder/insert/delete) | `SegmentStructureChanged` |

Precedence: `SegmentStructureChanged > TimingChanged > SourceTextChanged`.

Scope/identity: staleness is recorded per `translations` row, i.e. per
`(transcription_id, target_language)` — the only authorized Phase 5 translation
identity. A structural edit changes segment identity/position globally, so
**every** persisted translation row for the transcription is invalidated (marked
stale with `SegmentStructureChanged`).

### Staleness lifecycle (`DECISION-P6-005-STALENESS-LIFECYCLE-001`)

- **Scope.** Per translation row / translation target identity. Existing Phase 5
  translations remain historical outputs; structural or other approved source
  revision changes never rewrite or remap existing translation segments.
- **First invalidation.** A currently non-stale translation becoming invalidated
  gets `stale_at` set, `staleness_reason` set, and the causing revision recorded
  in `stale_caused_by_revision_id`.
- **Repeated invalidation.** If an already-stale translation is affected again:
  preserve the original `stale_at`; choose the canonical reason by the frozen
  precedence; **upgrade** `staleness_reason` only when the new reason outranks the
  stored reason; retain/update `stale_caused_by_revision_id` consistently with the
  stored canonical reason. A stronger reason is never downgraded to a weaker one.
- **Historical translation.** A stale translation remains viewable as historical
  output where existing product surfaces permit it, and must not be represented as
  current for the active revision.
- **Retranslation.** Staleness is never cleared on the historical row. A later
  successful retranslation creates/uses the canonical new translation identity for
  that source/revision/target lifecycle; the old translation remains stale
  historical evidence and its translated segment content is not deleted or mutated
  to appear current.
- **Failed retranslation.** A failed retranslation must not make the previous
  stale translation current again.

## Translation identity / provenance boundary

Existing Phase 5 translations belong to the machine-source transcript/segment
alignment. There is no revision-linked translation identity. Therefore P6-005 must
**not**:

- rewrite Phase 5 `translation_segments` rows;
- silently remap translation content onto new revision segment identities;
- assign new revision segment identities to old translations;
- fabricate revision linkage for machine-source translations;
- pretend a structurally modified revision remains aligned to the machine
  translation.

Instead, a structural change invalidates the appropriate translation state, and
the translation remains explicitly attributable to the machine source. P6-007 must
continue to present provenance truthfully.

## Concurrency and atomicity

Split/merge + invalidation must be transactionally coherent. The contract
requires:

- every structural edit states the **expected active/base revision**;
- a stale base fails as the canonical conflict (`RevisionConflictException`); no
  silent merge and no last-writer-wins;
- the structural revision append and the translation-invalidation persistence are
  committed **atomically** in a single database transaction;
- a failure must not leave a new active revision with a translation incorrectly
  marked current;
- a failure must not mark a translation stale without the structural revision
  having succeeded;
- an invalid/rejected structural edit leaves persistence unchanged (no new
  revision, no invalidation, active pointer unchanged);
- a stale-base conflict writes nothing;
- an ownership failure writes nothing.

Implementation uses the existing P6-002 transaction/CAS/version rules rather than
inventing a second concurrency model. The `EloquentRevisionRepository::append` is
already transactional; P6-005 extends the transactional boundary (a service-level
transaction around the append and the invalidation write) so both commit or roll
back together, without redefining the repository's CAS/version semantics.

## Timing rules

Split/merge must preserve the frozen timing semantics:

- finite;
- non-negative;
- `start <= end`;
- millisecond precision (no silent rounding);
- overlaps legal;
- zero-length legal (but never active);
- `position` defines ordering;
- no cross-segment timestamp monotonicity.

No hidden monotonicity may be introduced merely because split/merge is structural.

## Navigation / playback implications

After structural edits:

- the active revision's `RevisionSegmentIdentity` and `position` drive navigation,
  selection, filtering, and active-segment resolution (P6-001 §8);
- playback uses the active revision's timing (P6-004 behavior);
- newly created revision segments must be addressable by their new
  `RevisionSegmentIdentity` — never by a pretended machine `segment_index`;
- P6-006 navigation must remain ordered by the active revision's `position`;
- machine-source timing and rows remain immutable.

## P6-007 comparison implications

P6-007 currently remains truthful by refusing silent structural remapping
(alignment is presented as unavailable for structurally incompatible revisions).
P6-005 must preserve that: structurally created revision segments carry
non-machine identities and are therefore unaligned in the comparison. If persisted
staleness is later consumed by P6-007, that is a separate, explicitly approved
interface; P6-005 must not silently alter P6-007 presentation ownership or rewrite
its comparison semantics.

## Schema (authorized, additive only)

Owned by P6-005:

`translations` (additive):

| Column | Type | Notes |
|---|---|---|
| `stale_at` | timestamp nullable | first-invalidation instant; preserved on repeated invalidation |
| `staleness_reason` | string nullable | canonical `TranslationStalenessReason` value |
| `stale_caused_by_revision_id` | uuid nullable | revision responsible for the stored canonical reason |

`transcript_revision_segments` (additive):

| Column | Type | Notes |
|---|---|---|
| `language_provenance` | text nullable (JSON) | ordered contributor language markers for mixed-language merges; null otherwise |

No column on `transcriptions` / `transcription_segments` / Phase 5 translation
tables is dropped or semantically mutated. Existing Phase 5 translation rows and
translation segments are preserved; Phase 5 translation identity is not rewritten.

## Non-Scope

- P6-008 (revision history / audit surface) and P6-009 (Phase 6 integration gate);
- any change to the frozen P6-001/P6-002/P6-003/P6-004 semantics or the Phase 5
  translation contracts;
- rewriting Phase 5 translation segments or remapping translations to revision
  identities;
- translation re-generation / lifecycle orchestration changes beyond consuming the
  existing Phase 5 lifecycle;
- waveform/timeline (D6-09); speaker labels/annotations/bookmarks (D6-08);
- new public API surface beyond what the workspace requires;
- any new Phase 7 work.

## Dependencies

- P6-001 = DONE (frozen semantics); P6-002 = DONE (revision persistence);
- P6-003 = DONE (text editing workspace); P6-004 = DONE (timing editing);
- Phase 5 = CLOSED (persisted translation identity and lifecycle);
- D6-04 (adopted); DC-01 browser verification (browser behavior is material);
- the five owner decisions are DECIDED; `DECISION-P6-005-READY-001` promotes
  P6-005 to READY.

## Acceptance Criteria

1. A valid interior split creates two new-identity segments at the original and
   next position; text and timing are distributed as specified; both children
   carry the source language; the result is a new append-only revision; the prior
   revision is byte-for-byte unchanged.
2. A split boundary that violates the strict-interior policy is rejected with no
   write.
3. A valid adjacent merge creates one new-identity segment at the earliest
   position; contributing identities are absent from the new revision only; old
   revisions remain durable; text uses the single-space join; timing is
   earliest-position start → latest-position end; language follows the confirmed
   rules.
4. A non-adjacent/gapped merge is rejected with no write.
5. Structural edits are `EditKind::Structural` →
   `TranslationStalenessReason::SegmentStructureChanged`; the frozen taxonomy and
   precedence are not redefined.
6. Every persisted translation row for the transcription is marked stale with the
   confirmed reason; `translation_segments` are never rewritten and never remapped
   by `segment_index`.
7. The structural revision append and the invalidation write are atomically
   coherent: no active revision with a translation marked current, and no stale
   translation without the revision; a rejected edit leaves persistence unchanged.
8. A stale base is rejected as the canonical conflict; no silent merge.
9. The immutable machine source is unchanged across split/merge.
10. Newly created revision segments are addressable by their new
    `RevisionSegmentIdentity`; navigation/playback follow the active revision's
    position/timing; P6-006 navigation remains position-based.
11. P6-007 continues to refuse silent structural alignment; it is not altered by
    P6-005.
12. Ownership: a non-owner cannot split/merge; owner/admin can.
13. Reload durability: split/merge results, active revision, and invalidation
    state survive reload.
14. Relevant tests pass; Pint clean; PHPStan 0 errors; full regression green.

## Verification Requirements

At minimum:

### Split

- valid interior split;
- boundary split (beginning/end) rejected;
- resulting identities unique/new (never `machine:<index>`);
- positions contiguous;
- text/timing deterministic;
- language inherited;
- reload durability;
- translation invalidated.

### Merge

- adjacent merge succeeds;
- non-adjacent merge rejected;
- text canonical single-space join (no trimming/punctuation rewriting);
- timing result (earliest-position start → latest-position end);
- same-language merge retains the language;
- mixed-language merge → `und` + explicit ordered provenance;
- reload durability;
- translation invalidated.

### Staleness

- first invalidation;
- repeated weaker invalidation (no downgrade, `stale_at` preserved);
- repeated stronger invalidation (reason + causing revision upgraded);
- first `stale_at` preserved;
- reason precedence;
- causing-revision provenance;
- multiple target-language translations;
- no translation case;
- historical translation content unchanged;
- failed structural operation does not invalidate;
- failed invalidation rolls back the structural edit.

### Safety

- stale base;
- ownership denial;
- machine source unchanged;
- concurrency/race behavior;
- CAS/version semantics unchanged.

### Integration

- P6-006 navigation remains position-based;
- P6-007 never silently aligns structural revisions to machine translations.

## Browser Verification Requirements

Real-browser DC-01 verification is mandatory. Required coverage (at minimum):

Split:

- valid split (persist + visible result + reload durability);
- split at beginning/end — rejected explicitly per the strict-interior policy;
- resulting text/timing/language;
- revision history preservation (old revision reachable);
- translation invalidation visible/durable where the persisted marker is surfaced.

Merge:

- valid adjacent merge;
- non-adjacent merge rejected;
- resulting text/timing/language (including mixed-language → `und`);
- reload durability;
- translation invalidation visible/durable where the persisted marker is surfaced.

Concurrency / safety:

- stale structural edit conflict (clear, accessible, no silent merge);
- cancel / no write;
- ownership denial;
- machine source unchanged;
- a failed operation leaves the translation lifecycle coherent.

Comparison / navigation:

- P6-006 navigation still valid (position-based) after structural edits;
- P6-007 does not silently align a structurally changed revision to the machine
  translation.

Browser evidence must record exact steps, environment metadata, fixture identity,
observed vs expected state, and must not substitute backend tests for browser
behavior.

## Expected Reviewer

Claude Code.

## Shared Workspace-File Collision Risk

P6-005 targets `resources/views/transcriptions/show.blade.php` (and its
`partials/`), which contains `transcriptPlayback`, `transcriptSearch`,
`transcriptEditing`, `transcriptTiming`, the P6-007 comparison partial, the row
markup, and the reserved/owned `data-*` hooks.

- P6-003/P6-004 (the previous owners of the edit surface) are DONE, so the
  structural-editing surface is currently free.
- P6-005 adds its structural controls via a distinct partial and/or Alpine
  component with dedicated `data-struct-*` hooks, and must not regress
  P6-003/P6-004 editing, P6-006 navigation/search/filter, P6-007 comparison, or
  the reserved Phase 4 hooks.
- `RevisionService` / revision persistence are consumed write-through; P6-005 must
  not modify the frozen P6-001/P6-002 semantics.

## Readiness

READY — AUTHORIZED FOR IMPLEMENTATION (`DECISION-P6-005-READY-001`). All five owner
decisions are DECIDED and this contract is reconciled to incorporate them. On
completion P6-005 becomes `IMPLEMENTED_PENDING_REVIEW` and is handed to a fresh
independent review.
