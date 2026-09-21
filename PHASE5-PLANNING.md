# Phase 5 — Translation — Planning Package

Date: 2026-09-20
Status: PLANNING ONLY — NOT AUTHORIZED FOR IMPLEMENTATION
Authority: `RTFTT-MASTER-ROADMAP.md` (Phase 5 = Translation; reserved);
`plan.md`; `CURRENT_STATE.md`; ADR-019 (`Phase 5 = Translation`);
`.ai/guidelines/orchestration-policy.md`
Supersedes: none. This package does not supersede any accepted ADR.
Scope: Phase 5 = Translation over completed, persisted Phase 3 transcripts,
consumed through the Phase 4 transcript-experience baseline.

This package is a planning artifact. It is NOT an authorization. Phase 5
implementation remains NOT AUTHORIZED. No task file is created. No task may be
promoted to READY. No application code, test, migration, route, controller,
Livewire component, JavaScript, schema, worker file, or configuration change is
authorized by this package. Owner decisions in §F are OPEN/CANDIDATE until the
Human Product Owner records them in `DECISION_QUEUE.md` and a durable ADR.

## A. Repository State Confirmed

Verified directly against the repository at planning time (HEAD
`55c620a5739c9727d4f9884f7116b267e0ee2aae`):

- Phase 1 = ACCEPTED.
- Phase 2 = COMPLETE_WITH_DEFERRED_DEBT (closed 2026-09-17); Option D (ADR-013)
  remains in force; P2-004A/P2-004A1 remain BLOCKED historical debt.
- Phase 3 = CLOSED (2026-09-19, DECISION-PHASE3-CLOSURE-001); P3-001..P3-008
  DONE; canonical model `large-v3`.
- Phase 4 = CLOSED (2026-09-20, DECISION-PHASE4-CLOSURE-001); P4-001..P4-006
  DONE; residual LOW/INFO debt retained (`reviews/PHASE4-final-closure.md`).
- Phase 5 = NOT AUTHORIZED. No `DECISION-P5-*` entry exists. No `P5-*` task file
  exists. No translation model, table, route, provider, config key, or worker
  endpoint exists.
- `RTFTT-MASTER-ROADMAP.md`, `plan.md`, `CURRENT_STATE.md`, `AGENTS.md`,
  `DECISIONS.md` (ADR-019), and `DECISION_QUEUE.md` consistently record Phase 5
  as reserved and not authorized.

## B. Existing Capability Baseline (Verified)

| Capability | Current state | Phase 5 relevance |
|---|---|---|
| `Transcription` | `user_id`, `media_file_id`, `language`, `detected_language`, `speech_detected`, `model`, `status`, `full_text`, timestamps, `processing_seconds`, `error_message` | immutable completed source; `language` already reserved for a requested language |
| `TranscriptionSegment` | `transcription_id`, `segment_index` (unique per transcription), `start_seconds`/`end_seconds` `decimal(12,3)`, `text`, `language` (enum `LanguageIdentifier`) | per-segment source language and millisecond alignment already persisted |
| `LanguageIdentifier` | enum `ms`, `en`, `zh`, `ta`, `und`; BCP-47-compatible; `fromBcp47()` prefix match | canonical language vocabulary to reuse for source and target identifiers |
| Transcript experience primitives | `SegmentTimestamp`, `ActiveSegmentResolver`, `WorkspaceAvailability`, `WorkspaceState`, `TranscriptCopy` | reusable by translated-transcript presentation |
| Media playback | authorized range-stream endpoint (P4-002), `MediaFilePolicy`/`TranscriptionPolicy` | translated workspace reuses the same player; no media path to any provider |
| Export | `TranscriptionExportController` TXT/SRT/VTT/DOCX, completed-gated, ownership-authorized | translated export is a sibling surface; naming must not overwrite source |
| Queue architecture | Redis (without Horizon), `ProcessTranscription` with CAS claim + idempotent `TranscriptionResultWriter`, at-most-once inference, manual domain retry (P3-007/ADR-018) | Phase 5 reuses the same queue, claim, idempotency, and manual-retry principles |
| Worker | FastAPI `/transcribe` + `/health`, bearer token, opaque shared-media resolution, faster-whisper `large-v3` | Phase 5 may add a text-only translation surface; media preparation is unrelated |
| Ownership | `TranscriptionPolicy` (owner/admin), `MediaFilePolicy`, `FolderPolicy` | translation authorization must derive from `TranscriptionPolicy` |
| Translation provider | none | Phase 5 introduces it; provider strategy is an owner decision (§F, D5-04) |

Nothing in the repository currently implements translation. There is no hosted
translation provider dependency, no translation config key, and no translation
worker endpoint. `PROJECT_CONTEXT.md` §7 and §20 describe the product direction
(natural Bahasa Melayu Malaysia; original transcript never destroyed; translation
is derived data) but are product direction, not authorization.

## C. Objective

Phase 5 delivers reliable translation of completed, persisted transcripts into a
user-selected target language, preserving the original transcript unchanged,
preserving segment alignment and timestamps, using the provider-neutral,
queue-based, self-hosted architecture established in Phase 3, and surfacing a
baseline original-vs-translated experience over the Phase 4 transcript workspace.

## D. Phase 5 Scope (proposed)

- a translation domain contract (translation unit, alignment, lifecycle,
  persistence shape, ownership, completed-only gating);
- a translation persistence model and atomic result writer;
- translation queue orchestration + idempotent delivery, reusing Phase 3
  principles (CAS claim, small queue payload, terminal protection);
- a provider-neutral translation boundary with a self-hosted default
  implementation (subject to D5-04);
- translation failure/retry/recovery hardening (manual retry only, no automatic
  schedule, mirroring ADR-018);
- a baseline translation UI: target-language selection, translate action,
  progress, failed/retry, original/translated toggle, copy translated text;
- translated export (TXT/SRT/VTT/DOCX) that never overwrites source exports;
- a real integration verification gate (real translation model + browser +
  multilingual/code-switch fixture), subject to D5-09.

## E. Explicit Phase 5 Non-Scope

- transcript editing, timestamp editing, split/merge (Phase 6);
- automatic/real-time/streaming translation (out of current roadmap);
- translator chat, AI summary, action items (unroadmapped; out of current
  scope);
- speaker diarization / labels (Phase 6 candidate, separately gated);
- provider-abstraction redesign, multi-provider registry, routing, fallback
  (future capability requiring second-provider evidence per the roadmap);
- Horizon, automatic domain retry, retranscription/reprocessing of completed
  transcriptions;
- production deployment, monitoring, retention/deletion, backup/restore,
  PostgreSQL/Redis certification (Phase 7);
- actor-vs-owner, admin-on-behalf-of, multi-tenancy semantics;
- any change to Phase 3 queue/retry/failure-taxonomy/language/no-speech/
  atomic-persistence/media contracts;
- any schema change to `transcriptions` or `transcription_segments`.

## F. Translation Product / Technical Contract (proposed)

### 4.1 Translation unit (D5-01)

Candidate units investigated:

- **Whole transcript** — one provider call over the full text. Best global
  context; poorest alignment because the provider may reflow/split/merge, and
  code-switching and long transcripts risk truncation and drift. Re-aligning
  output back to persisted segments is not deterministic.
- **Segment-by-segment** — one unit per persisted `TranscriptionSegment`. Exact
  1:1 alignment, trivial idempotency and partial-failure semantics, natural fit
  for per-segment `language`. Weakness: little context, and terse segments can
  produce lower-quality translations.
- **Grouped / chunked** — consecutive segments grouped into context windows.
  Better context; requires deterministic re-splitting back to segment indexes,
  which is fragile when the provider changes sentence boundaries.
- **Hybrid** — translate per segment for the persisted artifact, but optionally
  supply grouped neighbouring context to the provider without allowing the
  provider to change segmentation. Preserves alignment exactly while recovering
  most context.

Default alignment rule (all units): translated segments retain the source
segment's `segment_index`, `start_seconds`, `end_seconds`, and source-language
marker. Phase 5 does not attempt semantic re-segmentation.

### 4.2 Source-language semantics

The transcript can contain `ms`, `en`, `zh`, `ta`, `und` within one recording.
`TranscriptionSegment::language` is per-segment and authoritative.

- **Per-segment source language** — used as the source hint for that unit.
- **Mixed-language transcript** — each segment is handled independently; the
  transcript-level `detected_language` is informational only and must not force
  a single source language.
- **`und`** — source is unknown. Policy is an owner-derived contract detail;
  candidate policies: (a) translate with provider auto-detect; (b) pass through
  unchanged and mark `und`; (c) fail the unit. Recommended default: translate
  with auto-detect and persist the source marker as `und`.
- **Code-switching within one segment** — the provider receives the segment text
  as-is; output is one target-language segment. No word/span-level tagging
  (ADR-017).
- **Already-target-language segments** — recommended passthrough with identical
  text (no unnecessary provider call), recorded as such, so alignment and
  ordering remain intact. Alternatively provider round-trip; passthrough is
  cheaper and preserves meaning.
- **Unsupported source language** — the vocabulary is fixed, so unsupported tags
  are not expected. Any unrecognized tag normalizes to `und` via
  `LanguageIdentifier::fromBcp47()`; no new vocabulary is introduced.

### 4.3 Target-language contract (D5-02, D5-03)

- **Supported targets** — candidate baseline mirrors the source vocabulary minus
  `und`: `ms`, `en`, `zh`, `ta`. A target-language enum (or a validated
  `LanguageIdentifier` excluding `Undetermined`) is the canonical identifier;
  format is lowercase BCP-47 (`ms`, `en`, `zh`, `ta`), consistent with
  `LanguageIdentifier`.
- **One vs multiple translations** — candidate: allow multiple translations per
  transcription (one per distinct target language), presented one target at a
  time. Alternative: exactly one translation per transcription.
- **User selection** — user chooses one target at a time; the workspace shows
  source + the selected target.
- **Duplicate-target prevention** — a uniqueness boundary must prevent two
  simultaneously active/successful translations for the same
  `(transcription_id, target_language)`. Retry/re-translate must supersede or
  create a new attempt rather than silently duplicating.
- **Retry behavior** — manual only, mirroring ADR-018 (B3-01..B3-05); completed
  translations are protected and never overwritten by a retry.

### 4.4 Provider strategy (D5-04)

Investigated options:

- **Self-hosted translation model** integrated into the existing Python worker
  (for example an OPUS-MT / Marian or NLLB-family model served alongside
  faster-whisper). Preserves the Phase 3 self-hosted, provider-neutral posture;
  no transcript text leaves the private boundary; no new hosted dependency; adds
  model/runtime capacity and language-pair quality risk.
- **Hosted provider** (any third-party translation API) called from Laravel.
  Fast to deliver and strong quality; introduces a data-processing/privacy
  boundary for private transcripts and an external cost/runtime dependency.
- **Pluggable provider abstraction with a self-hosted default** — a
  Laravel-side provider-neutral `TranslationProvider` boundary, a self-hosted
  default implementation in the Python worker, and a documented seam for a
  future hosted adapter. Highest architectural consistency; slightly more
  contract work up front.

Recommendation: Option C (pluggable boundary, self-hosted default). See §F
recommendation block and the Decision Register.

RECOMMENDATION: **Option C — provider-neutral boundary with a self-hosted
default.**
RATIONALE: Matches ADR-017/ADR-003 architecture, keeps private transcripts
inside the private boundary, avoids committing Phase 5 to a hosted vendor, and
keeps the worker transport/auth/queue already built in Phase 3.
TRADE-OFFS: More up-front contract and worker-model work; self-hosted
translation quality for `ms`/code-switching must be validated at the Phase 5
gate; a hosted provider later requires a new ADR (privacy/cost).

### 4.5 Translation lifecycle (D5-05)

Candidate states: `pending`, `queued`, `translating`, `completed`, `failed`,
with retry reachable only through an explicit authorized action (mirroring
ADR-018). `retryable` is a derived property, not necessarily a stored state.

Where the lifecycle lives:

- **On `Transcription`** — rejected: a completed transcription is protected and
  immutable (ADR-018 B3-04); translation is derived data and may have multiple
  targets.
- **Separate `Translation` entity, one per (transcription, target)** —
  recommended; keeps the source immutable, supports multiple targets, and gives
  a clean ownership/authorization join.
- **Per attempt** — attempt-level history should remain observable like
  `ProcessingJob`, but the authoritative lifecycle belongs to the `Translation`,
  not the attempt.

Recommended: a `Translation` entity keyed to `transcription_id` +
`target_language`, with attempt history modeled per attempt (either a dedicated
attempt model or reuse of the existing attempt pattern). The final shape is an
owner decision because it affects schema and retry semantics.

### 4.6 Translation persistence (D5-06)

Candidate persisted fields:

- translated full text;
- translated per-segment text;
- original `transcription_id` + `segment_index` reference (alignment);
- target language;
- per-unit source language (as persisted from source);
- provider + model identity;
- attempt identity;
- inherited timestamps (start/end) — copied from source, never recomputed;
- completion/failure metadata (started/completed, error, failure code).

Shape options: (a) normalized `translations` + `translation_segments` tables;
(b) a `translation_segments` JSON payload on `translations`. Recommended for
correctness, export, and queryability: normalized tables. No schema is
implemented in this planning phase.

### 4.7 Alignment

Default: translated segments retain the source `segment_index`, `start_seconds`,
`end_seconds`, and source-language marker. Phase 5 does not re-segment. This is
a hard rule unless D5-01 explicitly selects a unit that requires re-segmentation;
even then the persisted artifact must be segment-aligned.

### 4.8 Translation UI (D5-07)

Baseline Phase 5 UX candidates:

- target-language selector;
- translate action (per target);
- progress state (queued/translating);
- failed + retry state;
- original vs translated display (a toggle/tabs baseline);
- copy translated full text / segment;
- export translated transcript.

The **side-by-side comparison** experience and any advanced diff/compare UX are
candidate Phase 6 scope (see PHASE6-PLANNING.md §4). Phase 5 should ship the
toggle/tabs baseline and reuse the Phase 4 playback/highlight primitives. The
final split is an owner decision (D5-07).

### 4.9 Translation export (D5-08)

Candidate: translated TXT, SRT, VTT, DOCX. Rules:

- translated timestamps are inherited from the source segment, never recomputed;
- filenames must be distinguishable from source exports (for example a
  `-{target}` suffix or an equivalent naming contract) and must never overwrite
  source exports;
- completed-only and ownership authorization mirror the source export rule;
- export must never invoke the provider; it reads persisted derived data only.

Owner decision on whether all four formats are in Phase 5 or a subset.

### 4.10 Translation security / ownership

- Translation authorization derives from `TranscriptionPolicy` (owner/admin);
  no cross-user access by ID manipulation.
- Provider data boundary documented: with the self-hosted default, only
  transcript text (no media path, no binary) crosses to the worker; if a hosted
  provider is ever chosen (new ADR), that boundary must be explicitly reviewed.
- No private-media path may be exposed to any translation surface.
- Export and provider invocation are strictly separated: export never calls a
  provider.

### 4.11 Translation failure / retry

Reuse Phase 3 principles (ADR-018):

- retry is manual only; no automatic schedule;
- repeated/concurrent retry submissions produce exactly one new active attempt
  (CAS/guarded transition; genuine concurrency evidence per the ADR-013/016
  precedent);
- failed attempts remain immutable historical evidence;
- completed translations are protected;
- provider timeout, malformed output, and missing/duplicate segments must fail
  atomically (`persist` in one transaction) so a partial translation is never
  presented as complete;
- unsupported/undetermined source handled per §4.2 policy;
- worker crash/restart handled by stale-attempt recovery plus idempotent writer;
- `TranscriptionFailure`-equivalent taxonomy is authoritative on the Laravel
  side; worker flags are advisory only.

### 4.12 Translation verification gate (D5-09)

A real Phase 5 integration gate should verify:

- multilingual/code-switch input (ms/en/zh/ta, `und`, mixed);
- one or more target languages end-to-end;
- segment alignment preserved (index + ms timestamps);
- translation persistence and reload;
- failure + manual retry;
- source transcript unchanged (immutability);
- ownership isolation (cross-user denial);
- browser UX (selector, progress, toggle, copy);
- translated exports (all formats),
- no regression to Phase 3/4 contracts.

Mocks must not substitute for the real provider/model or browser where those
matters. Whether a mandatory real-model gate (mirroring B3-07) applies is an
owner decision (D5-09).

## G. Recommended Phase 5 Task Decomposition (candidate — NOT created)

| Task | Title | Objective | Depends on |
|---|---|---|---|
| P5-001 | Translation Domain Contract | Translation unit, alignment, target-language enum, lifecycle, persistence contract, provider interface, ownership, export naming, failure taxonomy | D5-01..D5-09 resolved; Phase 5 authorization |
| P5-002 | Translation Persistence + Atomic Completion | `Translation`(+segments) schema/model, unique target boundary, atomic idempotent writer, completed/immutability | P5-001 DONE |
| P5-003 | Translation Queue Orchestration + Provider Boundary | Job, small payload, CAS claim, at-most-once inference, provider-neutral invocation | P5-002 DONE |
| P5-004 | Self-Hosted Translation Provider | Worker translation endpoint + model/config + normalized result + tests | P5-003 DONE (D5-04) |
| P5-005 | Translation Failure / Retry / Recovery Hardening | Failure taxonomy, manual retry, stale-attempt recovery, concurrency evidence | P5-003 DONE |
| P5-006 | Translation UI | Target selector, translate, progress, failed/retry, toggle, copy | P5-002 DONE; retry surface P5-005 DONE; browser required |
| P5-007 | Translated Export | TXT/SRT/VTT/DOCX derived export, naming, inherited timestamps | P5-002 DONE |
| P5-008 | Phase 5 Integration Verification | Real provider + browser + multilingual + alignment + retry + export + ownership + regression | P5-004/005/006/007 DONE |

Each task must additionally carry, at contract-authoring time: scope,
non-scope, acceptance criteria, verification requirements, expected reviewer
(Claude Code), browser-evidence flag, and owner-decision dependencies.

## H. Dependency / Authorization Order

```text
Phase 5 planning (this package)
→ HPO resolves D5-01..D5-09 (and DC-01) and records an ADR
→ HPO Phase 5 task/batch authorization
→ P5 task contracts authored (BACKLOG; non-executable)
→ HPO promotes wave to READY
→ READY → IN_PROGRESS → REVIEW → independent review
→ VERIFIED → HPO closure → DONE
```

Per `.ai/guidelines/orchestration-policy.md`, completing a phase never
authorizes the next phase, and `VERIFIED` never equals `DONE` until HPO closure.

## I. Migration / Compatibility

- Phase 5 is expected to require **additive** schema only (new translation
  tables); no historical migration is modified.
- `transcriptions` and `transcription_segments` content is never mutated by
  Phase 5.
- Phase 3 queue/retry/failure/language/no-speech/atomic-persistence/media
  contracts and completed-transcription protection remain authoritative.
- Phase 4 read model and playback are reused unchanged; translation is layered
  on top.

## J. Risks (summary; full register in `PHASE5-7-RISK-REGISTER.md`)

| Risk | Mitigation |
|---|---|
| Translation quality below product expectation (esp. BM + code-switch) | Real-model gate; benchmark fixture; quality acceptance before closure |
| Provider data/privacy boundary | Self-hosted default; hosted requires new ADR; text-only boundary |
| Alignment drift / re-segmentation | Segment-aligned persisted artifact; no re-segmentation in Phase 5 |
| Duplicate/concurrent translations | Unique target boundary + CAS claim + concurrency evidence |
| Partial/atomicity failure | Atomic idempotent writer; never present partial as complete |
| Schema evolution risk | Additive-only, reviewed in P5-001 |
| Scope creep into Phase 6 editing | Explicit non-scope; comparison UX owner decision |
| Runtime cost/latency of self-hosted model | Phase 7 capacity gate; RTF-style measurement |

## K. Files Created or Updated

- `PHASE5-PLANNING.md` — this package.
- `PHASE5-7-WAVE-PLAN.md`, `PHASE5-7-DECISION-REGISTER.md`,
  `PHASE5-7-DEPENDENCY-GRAPH.md`, `PHASE5-7-RISK-REGISTER.md` — coordinated
  planning artifacts.

No application code, test, migration, route, controller, Livewire component,
streaming endpoint, JavaScript, worker file, or UI is created. No P5 task file
is created. No governance file (`CURRENT_STATE.md`, `plan.md`, `DECISIONS.md`,
`DECISION_QUEUE.md`, `AGENTS.md`, `RTFTT-MASTER-ROADMAP.md`) is modified.

## L. Explicit Non-Actions

- Phase 5 implementation is NOT authorized and requires a separate HPO
  authorization.
- No task is promoted to READY; no implementation batch is authorized.
- No Phase 3/4 VERIFIED/DONE contract is reopened or changed.
- No reviewer artifact is modified.
- Option D (ADR-013) is not lifted.
- No owner decision is made on behalf of the Human Product Owner; §F records
  OPEN/CANDIDATE questions only.