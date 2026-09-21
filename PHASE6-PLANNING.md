# Phase 6 — Advanced Transcript UX — Planning Package

Date: 2026-09-20
Status: PLANNING ONLY — NOT AUTHORIZED FOR IMPLEMENTATION
Authority: `RTFTT-MASTER-ROADMAP.md` (Phase 6 = Advanced Transcript UX;
reserved); `plan.md`; `CURRENT_STATE.md`; ADR-019 (`Phase 6 reserved for
Advanced Transcript UX`); ADR-019 D4-05 (transcript editing deferred to
Phase 6); `.ai/guidelines/orchestration-policy.md`
Supersedes: none.
Scope: Phase 6 = advanced interaction over completed transcribed/translated
transcripts. Exact scope is a set of OPEN owner decisions.

This package is a planning artifact, NOT an authorization. Phase 6
implementation remains NOT AUTHORIZED until Phase 5 closure and a separate HPO
authorization, except where a task is independently demonstrated to have no
Phase 5 dependency and the HPO separately approves it. No task file is created;
no task may be promoted to READY; no code/schema/test change is authorized.

## A. Repository State Confirmed

- Phase 4 = CLOSED (2026-09-20, DECISION-PHASE4-CLOSURE-001). Phase 4 is
  explicitly read-only (D4-05): no editing, timestamp editing, merge/split,
  autosave, or revision history exists.
- Phase 4 delivered: authorized media playback, click-to-seek, synchronized
  active-segment highlighting, client-side search/copy, export hardening, and
  the shared primitives `SegmentTimestamp`, `ActiveSegmentResolver`,
  `WorkspaceAvailability`, `WorkspaceState`, `TranscriptCopy`.
- Phase 5 (Translation) = NOT AUTHORIZED. Phase 6 = NOT AUTHORIZED.
- No edit surface, no revision model, no speaker/diarization model, no
  annotation/chapter/bookmark model, no waveform, and no versioning exist.
- `PROJECT_CONTEXT.md` §19 lists future transcript interactions (seek, sync
  highlight, search, copy, edit, exports); §21 lists future AI capabilities
  (summary, key points, action items, chapters, notes, Q&A, chat, topics,
  translation, speaker analysis). These are product direction, not authorization.

## B. Objective

Phase 6 delivers advanced, interactive transcript authoring/consumption beyond
the Phase 4 read-only baseline, without weakening the immutability and
provenance guarantees of the machine transcript and the derived translation.

The central architectural question is whether Phase 6 editing is a
**non-destructive user layer over an immutable machine transcript** or a
**direct mutation of the stored transcript**. This is an owner decision
(D6-01), and it determines the persistence, export, and translation-alignment
design.

## C. Candidate Scope Classification

Each candidate is classified **IN PHASE 6**, **DEFER**, or
**OUT OF PRODUCT**. Classifications marked with an owner-decision ID are
candidates only and become binding only when the HPO decides.

| Candidate capability | Classification | Rationale |
|---|---|---|
| Transcript segment text correction | IN PHASE 6 (D6-01/D6-02) | Explicitly deferred here by ADR-019 D4-05; core "advanced transcript UX" |
| Timestamp editing / correction | IN PHASE 6 (D6-03) | Natural companion to text correction; must be validated and non-destructive by default |
| Split segment | IN PHASE 6 (D6-04) | Core segment-level authoring; affects index/alignment/translation |
| Merge segment | IN PHASE 6 (D6-04) | Companion to split; affects alignment/translation |
| Undo/redo | IN PHASE 6 (D6-02) | Prerequisite for safe editing UX |
| Save/version semantics + revision history | IN PHASE 6 (D6-02/D6-05) | Required to preserve provenance if editing is permitted |
| Richer navigation (keyboard next/prev, jump) | IN PHASE 6 (D6-07) | Low-risk, builds on Phase 4 primitives |
| Advanced search/filter (by language) | IN PHASE 6 (D6-07) | Filter by persisted per-segment language is low-risk; keep client-side |
| Source/translation comparison UX (side-by-side) | IN PHASE 6 (D6-06) | Depends on Phase 5; comparison belongs to advanced UX |
| Manual speaker label assignment (no auto-diarization) | DEFER / owner (D6-08) | No diarization engine exists; automatic speaker analysis is a separate capability |
| Automatic speaker diarization | DEFER (separate phase) | Requires a new model/capability and its own contract; not Phase 6 baseline |
| Annotations / comments | DEFER / owner (D6-08) | Not required for transcript authoring; product value unproven |
| Chapters | DEFER (separate capability) | In `PROJECT_CONTEXT.md` §21 future AI direction; not transcript UX baseline |
| Bookmarks | DEFER (D6-08) | Low value vs cost; can be modeled later |
| Waveform / timeline / clipping | DEFER (D6-09) | Explicitly excluded from Phase 4 (D4-06 waveform); high browser/compute cost; not required for editing |
| Playback-follow mode improvement | IN PHASE 6 or DEFER (D6-07) | Phase 4 already has synchronized highlighting + auto-scroll; only incremental value |
| Real-time collaboration / multi-user editing | OUT OF PRODUCT (current roadmap) | Requires actor-vs-owner, tenancy, concurrency, and a separate program |
| AI summary / action items / chat | OUT OF PRODUCT (current roadmap) | Unroadmapped; separate product capability |
| Live / realtime transcription/translation | OUT OF PRODUCT (current roadmap) | Separate engineering program (PROJECT_CONTEXT §22–§23) |

## D. Detailed Contract (proposed)

### 5.1 Editing model (D6-01)

Options investigated:

- **Direct mutation of `transcription_segments` / `transcriptions`** — simplest
  read model but destroys the machine-original provenance, complicates audit,
  and breaks the current "completed transcriptions protected" guarantees. Not
  recommended without an explicit provenance redesign.
- **Immutable machine transcript + editable derived layer** — the Phase 3
  persisted rows become the immutable "machine original"; edits create
  revision/override rows in a separate user layer that the workspace renders.
  Preserves provenance, supports undo/redo and revision history, and gives
  translation a stable source reference. Recommended.
- **Hybrid with in-place edits plus full audit log** — mutate rows but write
  every change to an audit table. Simpler rendering; weaker immutability and
  harder reconciliation with translation alignment.

Recommended: **Option B (immutable machine transcript + editable layer)**.

Contract details (candidate):

- editable fields: segment text; optionally segment start/end (D6-03);
  optionally segment split/merge (D6-04);
- ownership: edits authorized by `TranscriptionPolicy` (owner/admin);
- validation: non-empty text where required, Unicode-preserving, length bounds;
- persistence: revision rows referencing `transcription_id` + `segment_index`
  (or a stable segment identity if split/merge is allowed — see D6-04);
- concurrency: optimistic concurrency (revision/version token) is recommended
  for a single-user product; pessimistic locking is unnecessary baseline;
- conflict handling: reject stale writes with a clear, accessible error and a
  reload path;
- audit/history: append-only revision entries with actor, timestamp, before/after;
- relation to original: the machine transcript is never overwritten; the
  workspace can always reveal the original text.

### 5.2 Timing editing (D6-03)

If included:

- precision: millisecond (`decimal(12,3)`), consistent with Phase 3;
- invariants: `start_seconds >= 0`, `start_seconds < end_seconds`;
- overlap prevention: policy must be decided (allow overlaps vs reject);
  recommended reject-by-default with an explicit override, preserving the
  Phase 4 `ActiveSegmentResolver` assumptions;
- gap handling: allowed (Phase 4 already treats inter-segment gaps as "no
  active segment");
- validation: server-side enforcement, never client-only;
- export impact: SRT/VTT must reflect edited timestamps consistently;
- playback sync impact: edited timestamps change seek/highlight behavior.

Destructive timestamp editing is not assumed acceptable; the immutable-layer
model (D6-01) makes timing edits reversible.

### 5.3 Segment split / merge (D6-04)

If included:

- index reordering: split/merge changes the segment ordering; the stable
  identity of edited segments must be defined (candidate: a per-transcription
  stable segment key distinct from the display `segment_index`);
- timestamp boundaries: split divides one interval into two contiguous
  intervals; merge unions two contiguous intervals; both must preserve
  monotonicity;
- language metadata: split/merge must define how per-segment `language` is
  carried (candidate: inherit source segment language; merge of differing
  languages requires a policy);
- translation alignment impact: existing translations reference segment
  indices; split/merge can invalidate translated alignment. Candidate policy:
  supersede affected translations (mark stale) rather than silently misalign;
- export behavior: exports must use the edited view consistently;
- dependency on Phase 5: this is a cross-phase dependency — split/merge is only
  safe once translation alignment semantics are defined.

### 5.4 Waveform / timeline (D6-09)

Evaluated and recommended **DEFER**:

- browser implementation complexity is high (decode + peaks either server-side
  or client-side);
- performance and memory concerns for 500 MiB media;
- audio/video parity adds complexity;
- server-side waveform generation would add a new processing surface and
  storage artifacts;
- it is not required to deliver transcript editing.

It should not be included merely because it is attractive UX.

### 5.5 Version / edit history (D6-02/D6-05)

Candidate requirements: revision history, append-only audit log, undo/redo,
immutable machine transcript + editable presentation layer. Material
architectural choice → owner decision.

### 5.6 Phase 6 integration gate

Browser-heavy verification (see also DC-01 browser governance):

- edit a segment; persist; reload; edit survives;
- undo/redo behavior;
- seek/playback sync after timing edits;
- split/merge and index/alignment integrity;
- source/translation comparison correctness (if P5 present);
- exports after edit reflect the edited view;
- conflict/error behavior (stale write);
- Unicode round-trip (`ms`, `en`, `zh`, `ta`, `und`);
- no-speech / empty state;
- original machine text remains recoverable;
- no regression to Phase 3/4/5 contracts.

## E. Recommended Phase 6 Task Decomposition (candidate — NOT created)

| Task | Title | Objective | Depends on |
|---|---|---|---|
| P6-001 | Phase 6 UX + Editing Contract | Edit model, editable fields, validation, concurrency, revision model, timing/split-merge rules, translation-invalidation policy | Phase 5 CLOSED; D6-01..D6-09 resolved |
| P6-002 | Edit Persistence + Revision Layer | Immutable machine transcript + revision/override tables, atomic save, audit entries | P6-001 DONE |
| P6-003 | Segment Text Editing UI + Undo/Redo | Edit controls, optimistic concurrency, save, undo/redo, accessible errors | P6-002 DONE; browser required |
| P6-004 | Timing Editing + Validation | ms-precise start/end editing, invariants, overlap/gap policy | P6-002 DONE; browser required |
| P6-005 | Segment Split / Merge + Translation Invalidation | Stable segment identity, boundaries, language carry, translation staleness | P6-004 DONE; P5 semantics DONE |
| P6-006 | Advanced Navigation + Search/Filter | Keyboard nav, jump, language filter (client-side) | P4 primitives DONE |
| P6-007 | Source/Translation Comparison UX | Side-by-side/compare over Phase 5 translations | P5 closure DONE; browser required |
| P6-008 | Revision History / Audit Surface | View revisions, restore/compare (if D6-02 selects history) | P6-002 DONE |
| P6-009 | Phase 6 Integration Verification | Browser-heavy end-to-end edit/timing/split-merge/export/regression gate | P6-003..P6-008 DONE |

## F. Dependency / Authorization Order

```text
Phase 5 CLOSED (required baseline for full Phase 6)
→ HPO resolves D6-01..D6-09 + DC-01
→ HPO Phase 6 task/batch authorization
→ P6 contracts authored (BACKLOG; non-executable)
→ HPO promotes wave to READY
→ READY → IN_PROGRESS → REVIEW → independent review
→ VERIFIED → HPO closure → DONE
```

Exception path: an individual P6 task with no Phase 5 dependency (for example
P6-006 advanced navigation/filter over source transcripts) may be authorized
before Phase 5 closure only if the HPO separately and explicitly approves it.

## G. Migration / Compatibility

- Expected additive schema (revision/edit layer, possibly stable-segment keys).
- Machine transcript and Phase 3/4/5 contracts remain authoritative; the
  `completed` transcription protection (ADR-018 B3-04) continues to apply to the
  machine original.
- Any split/merge or timing change must not silently corrupt translation
  alignment; staleness must be explicit.

## H. Risks (summary; full register in `PHASE5-7-RISK-REGISTER.md`)

| Risk | Mitigation |
|---|---|
| Non-destructive vs destructive edit ambiguity | D6-01 explicit owner decision; default immutable layer |
| Edit/translation synchronization drift | Translation-invalidation policy (D6-04); stale marking |
| Timestamp edits break Phase 4 playback assumptions | Server-side validation; explicit overlap policy |
| Split/merge index churn | Stable segment identity; tested invariants |
| UX complexity / scope explosion | Classification table; IN/DEFER/OUT boundaries |
| Browser automation flakiness (editing) | DC-01 determinism strategy |
| Schema evolution risk | Additive migrations; P6-001 contract review |

## I. Explicit Non-Actions

- Phase 6 implementation is NOT authorized; Phase 6 requires Phase 5 closure
  and a separate HPO authorization.
- No task is promoted to READY; no batch is authorized.
- No application, test, migration, route, controller, view, JavaScript, or
  worker change is authorized.
- No owner decision is made on behalf of the HPO; §C/§D are candidate
  classifications and OPEN questions.