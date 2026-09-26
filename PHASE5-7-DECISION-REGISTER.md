# Phase 5–7 — Owner Decision Register

Date: 2026-09-20
Status: PLANNING ONLY — candidate decisions (D6-01..D6-09 and DC-01 are now
DECIDED; see the note below)
Authority: `PHASE5-PLANNING.md`, `PHASE6-PLANNING.md`, `PHASE7-PLANNING.md`

> **Status update (2026-09-23):** D6-01..D6-09 and DC-01 have been adopted by the
> HPO (`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025) and are no longer open.
> D5-01..D5-09 were frozen under ADR-022. D7-01..D7-08 and DC-02 remain OPEN.

This register lists material unresolved owner decisions for Phases 5–7. Unless
marked decided above, every entry is **OPEN** (recorded here as a candidate
only). No decision is made on behalf of the Human Product Owner. None of these
decisions is recorded in `DECISION_QUEUE.md`; the HPO must promote and decide
them there (and, where durable, in an ADR) before implementation authorization.

Format per decision: ID, QUESTION, OPTION A/B/C (Pros/Cons), RECOMMENDATION,
WHY, IMPACT IF DEFERRED.

---

## Phase 5 — Translation

### D5-01 — Canonical translation unit
QUESTION: What is the canonical unit sent to the translation provider and
persisted?

OPTION A — Whole transcript (single provider call, re-align output).
Pros: maximum global context; one call per target.
Cons: non-deterministic re-alignment; truncation/drift on long transcripts;
breaks per-segment language handling and strict alignment.

OPTION B — Segment-by-segment (one unit per persisted segment).
Pros: exact 1:1 alignment; trivial idempotency and partial-failure semantics;
respects per-segment language.
Cons: little context; terse segments translate poorly.

OPTION C — Hybrid: persist segment-aligned output, optionally supply grouped
neighbouring context to the provider without allowing re-segmentation.
Pros: exact alignment plus most of the context benefit; consistent with Phase 4
primitives.
Cons: slightly more provider-contract work; context window policy must be
defined and tested.

RECOMMENDATION: Option C.
WHY: It preserves the hard alignment guarantee required by timestamps and
Phase 4 playback while recovering context quality; it fits the provider-neutral,
segment-oriented Phase 3 model.
IMPACT IF DEFERRED: P5-001 cannot define the provider request/response contract
or persistence shape; no translation implementation can be contracted.

### D5-02 — Supported target languages
QUESTION: Which target languages are supported in the Phase 5 baseline?

OPTION A — Mirror the source vocabulary minus `und`: `ms`, `en`, `zh`, `ta`.
Pros: consistent with `LanguageIdentifier`; covers the product's real audience;
no new vocabulary.
Cons: excludes other markets until later.

OPTION B — `ms` only (product-stated primary target).
Pros: smallest scope; focused quality bar (natural Bahasa Melayu Malaysia).
Cons: under-delivers the multilingual product intent; likely needs early
expansion.

OPTION C — `ms` + `en` only, expand later.
Pros: covers the two dominant practical targets.
Cons: still narrow; creates a second expansion cycle.

RECOMMENDATION: Option A with `ms` as the primary quality focus.
WHY: Reuses the established BCP-47 vocabulary and supports the documented
multilingual product without new identifiers.
IMPACT IF DEFERRED: Target enum/uniqueness and acceptance criteria cannot be
finalized; translation UI selector cannot be specified.

### D5-03 — Multiple translations per transcription
QUESTION: May one transcription hold more than one translation (one per target)?

OPTION A — One translation per transcription (single target).
Pros: simplest schema and UI.
Cons: forces re-translation to change target; wastes prior work; conflicts with
"present one target at a time".

OPTION B — One translation per (transcription, target), multiple targets.
Pros: natural model; supports target switching; clean uniqueness boundary.
Cons: more rows/tables and UI state management.

OPTION C — Multiple concurrent translations per target (versioned).
Pros: supports re-translation history per target.
Cons: heaviest model; overlaps with Phase 6 revision semantics.

RECOMMENDATION: Option B.
WHY: It matches the natural product model (derived data per target) and keeps
the uniqueness boundary clean; re-translation can supersede rather than
duplicate.
IMPACT IF DEFERRED: Persistence schema and duplicate-prevention contract
unresolved.

### D5-04 — Translation provider strategy
QUESTION: Which provider strategy is canonical for Phase 5?

OPTION A — Self-hosted translation model in the Python worker.
Pros: private transcripts stay inside the private boundary; no vendor/cost
dependency; consistent with ADR-017.
Cons: added model/runtime capacity; `ms`/code-switch quality must be validated;
larger worker image.

OPTION B — Hosted third-party translation API from Laravel.
Pros: fast delivery; strong quality; less infrastructure.
Cons: private transcript text leaves the boundary; vendor/cost/latency
dependency; requires privacy review.

OPTION C — Provider-neutral `TranslationProvider` boundary with a self-hosted
default and a documented hosted seam.
Pros: architectural consistency; keeps options open; hosted later needs only a
new adapter + ADR.
Cons: more up-front contract work.

RECOMMENDATION: Option C.
WHY: Preserves the established provider-neutral, self-hosted architecture and
avoids committing the program to a vendor while keeping a clean upgrade path.
IMPACT IF DEFERRED: P5-003/P5-004 cannot be contracted; no provider can be
implemented.

### D5-05 — Translation lifecycle ownership
QUESTION: Where does translation lifecycle state live?

OPTION A — On `Transcription`.
Pros: one record.
Cons: violates completed-transcription immutability (ADR-018 B3-04); cannot
represent multiple targets cleanly.

OPTION B — A separate `Translation` entity per (transcription, target), with
attempt history modeled per attempt.
Pros: keeps source immutable; supports multiple targets; clean auth join;
mirrors Phase 3 attempt semantics.
Cons: new enum/state surface.

OPTION C — A separate translation entity per attempt only.
Pros: simple attempt history.
Cons: no stable target-level state; UI must aggregate attempts; harder
protection of completed translations.

RECOMMENDATION: Option B.
WHY: It preserves source immutability, supports multiple targets, and reuses the
proven attempt/claim/idempotency pattern.
IMPACT IF DEFERRED: P5-002 schema/lifecycle cannot be defined.

### D5-06 — Translation persistence shape
QUESTION: How are translated units persisted?

OPTION A — Normalized `translations` + `translation_segments` tables.
Pros: queryable; export-friendly; alignment explicit; auditable.
Cons: two new tables.

OPTION B — `translations` with a JSON segment payload.
Pros: one table; simple writes.
Cons: weak querying/export; alignment stored as an opaque blob; harder
validation.

OPTION C — Hybrid: normalized metadata + JSON override payload.
Pros: flexible.
Cons: two representations; risk of divergence.

RECOMMENDATION: Option A.
WHY: Correctness, export, and auditability benefit from explicit per-segment
rows that mirror `transcription_segments`.
IMPACT IF DEFERRED: P5-002 cannot be planned; no migration can be authored.

### D5-07 — Original-vs-translated UX placement
QUESTION: What is Phase 5's baseline comparison UX, and what moves to Phase 6?

OPTION A — Phase 5 ships a toggle/tabs baseline (source ↔ translated).
Pros: minimal, accessible, reuses Phase 4 primitives; faster to verify.
Cons: no side-by-side.

OPTION B — Phase 5 ships side-by-side comparison.
Pros: richer comparison immediately.
Cons: larger scope; advanced UX overlaps Phase 6; more browser verification.

OPTION C — Phase 5 ships no display beyond per-target translated read view.
Pros: smallest.
Cons: weak product value; users cannot compare.

RECOMMENDATION: Option A; side-by-side/advanced comparison to Phase 6.
WHY: Keeps Phase 5 focused on reliable translation; comparison is an advanced
UX concern that ADR-019 reserved for Phase 6.
IMPACT IF DEFERRED: P5-006 cannot be scoped; browser acceptance criteria
unclear.

### D5-08 — Translated export format set
QUESTION: Which translated export formats are in Phase 5?

OPTION A — TXT/SRT/VTT/DOCX, mirroring source export (D4-03), with a
target-language filename suffix and no source overwrite.
Pros: consistent with Phase 4; complete.
Cons: more test surface.

OPTION B — TXT/SRT/VTT only.
Pros: smaller.
Cons: inconsistent with the established source format set.

OPTION C — TXT only.
Pros: minimal.
Cons: under-delivers for subtitle workflows.

RECOMMENDATION: Option A.
WHY: Consistency with the accepted source export set and the product's subtitle
use cases; the extra test surface is modest.
IMPACT IF DEFERRED: P5-007 cannot be scoped; export naming contract undefined.

### D5-09 — Real translation-model gate
QUESTION: Does the Phase 5 gate require a real self-hosted translation-model
execution (mirroring B3-07), beyond mocks?

OPTION A — Yes: mandatory real model + browser gate (mocks allowed for
unit/feature only).
Pros: genuine end-to-end evidence; consistent with Phase 3 precedent.
Cons: runtime/capacity requirements; slower gate.

OPTION B — No: mocks + browser enough for Phase 5.
Pros: faster.
Cons: risks shipping an unvalidated provider; weakens the gate.

OPTION C — Real model for a canonical subset (for example `ms` + one
code-switch fixture), mocks elsewhere.
Pros: balances evidence and cost.
Cons: partial coverage must be explicitly declared.

RECOMMENDATION: Option A at least for the canonical target(s), with Option C
fixture policy as the concrete test matrix.
WHY: The product's core claim is reliable translation; the Phase 3 gate set the
precedent that mocks cannot satisfy the final integration requirement.
IMPACT IF DEFERRED: P5-008 acceptance is undefined; closure could be claimed on
non-real evidence.

---

## Phase 6 — Advanced Transcript UX

### D6-01 — Editing model / provenance
QUESTION: Does Phase 6 edit the stored transcript or layer edits over an
immutable machine transcript?

OPTION A — Direct mutation of stored transcript rows.
Pros: simplest rendering; one source.
Cons: destroys machine provenance; complicates audit and translation alignment;
weakens the completed-transcript protection contract.

OPTION B — Immutable machine transcript + editable derived layer.
Pros: preserves provenance; supports undo/redo/history; translation keeps a
stable source; original always recoverable.
Cons: more schema and read-model complexity.

OPTION C — In-place mutation + full audit log.
Pros: simpler read model than B.
Cons: provenance reconstruction only via log; harder reconciliation; weaker
guarantees.

RECOMMENDATION: Option B.
WHY: It preserves the Phase 3/4 immutability and provenance guarantees that
translation alignment depends on, and it enables safe undo/redo.
IMPACT IF DEFERRED: P6-002 persistence and export/translation behavior cannot be
defined.

### D6-02 — Undo/redo and version semantics
QUESTION: Are undo/redo and version/revision history in Phase 6?

OPTION A — Revision history + undo/redo + append-only audit.
Pros: safe editing; full provenance; supports recovery.
Cons: more schema/UX.

OPTION B — Undo/redo only (client session), no persistent history.
Pros: cheaper.
Cons: no audit/recovery across sessions.

OPTION C — No undo/redo or history (corrections are immediate and permanent).
Pros: smallest.
Cons: risky user experience; contradicts safe editing.

RECOMMENDATION: Option A.
WHY: If transcripts are editable, durable revision history is the minimum
defensible provenance model; it also complements the immutable-layer choice in
D6-01.
IMPACT IF DEFERRED: P6-002/P6-003/P6-008 cannot be scoped.

### D6-03 — Timestamp editing
QUESTION: Is segment timestamp editing in Phase 6?

OPTION A — Yes, ms-precise, server-validated, non-destructive (editable layer),
overlap policy defined.
Pros: completes correction workflow; supports subtitle accuracy.
Cons: affects playback sync/exports; needs robust validation.

OPTION B — Text-only editing; timestamps read-only.
Pros: smaller, safer.
Cons: leaves a common correction need unmet.

OPTION C — Yes but only within existing boundaries (trim-only, no reorder).
Pros: bounded.
Cons: may not cover real corrections.

RECOMMENDATION: Option A with explicit invariants and an overlap policy (reject
by default).
WHY: Timing accuracy is intrinsic to transcript correction and subtitle export;
the immutable layer makes it reversible.
IMPACT IF DEFERRED: P6-004 cannot be scoped; playback/export acceptance
criteria undefined.

### D6-04 — Split/merge and translation invalidation
QUESTION: Are split/merge in Phase 6, and how are existing translations
handled?

OPTION A — Yes, with stable segment identity and explicit translation
invalidation (supersede/mark stale).
Pros: complete authoring; alignment integrity preserved; explicit staleness.
Cons: most complex; depends on Phase 5 alignment semantics.

OPTION B — Yes, but translations are left untouched (assumed alignment).
Pros: simpler.
Cons: silent misalignment; unacceptable correctness risk.

OPTION C — No split/merge in Phase 6; defer.
Pros: smallest.
Cons: limits authoring; may need a later phase.

RECOMMENDATION: Option A, gated on Phase 5 being CLOSED.
WHY: Split/merge without a translation-invalidation policy would corrupt
derived data; a stable identity plus explicit staleness is the safe design.
IMPACT IF DEFERRED: P6-005 cannot be scoped; cross-phase alignment risk
unresolved.

### D6-05 — Revision history surface
QUESTION: Is a user-facing revision history/restore surface in Phase 6, or is
history backend-only?

OPTION A — User-facing history view + restore/compare.
Pros: transparent; supports recovery.
Cons: additional UX/test surface.

OPTION B — Backend history only; no user surface.
Pros: cheaper.
Cons: history is opaque to users.

OPTION C — No history surface.
Pros: minimal.
Cons: contradicts D6-02 if history is retained.

RECOMMENDATION: Option A if D6-02 selects persistent history; otherwise cancel.
WHY: Persistent history without a way to inspect/recover it delivers limited
user value.
IMPACT IF DEFERRED: P6-008 scope undefined.

### D6-06 — Comparison UX
QUESTION: What comparison UX is in Phase 6 (beyond the Phase 5 toggle)?

OPTION A — Side-by-side source/translation with synchronized scrolling and
highlighting.
Pros: strong comparison experience; reuses Phase 4 sync.
Cons: browser-heavy; needs alignment guarantees.

OPTION B — Toggle/stacked comparison only.
Pros: simple.
Cons: limited.

OPTION C — No comparison beyond Phase 5.
Pros: minimal.
Cons: under-delivers "advanced transcript UX".

RECOMMENDATION: Option A.
WHY: It is the most valuable advanced-UX capability over translations and builds
directly on the Phase 4 synchronized workspace.
IMPACT IF DEFERRED: P6-007 cannot be scoped.

### D6-07 — Navigation and search/filter scope
QUESTION: Which advanced navigation/search/filter capabilities are in Phase 6?

OPTION A — Keyboard segment navigation, jump-to-segment, and language filter
over persisted per-segment language (client-side).
Pros: high value, low risk, builds on Phase 4.
Cons: modest UI surface.

OPTION B — Navigation only, no filter.
Pros: smaller.
Cons: leaves language filtering unmet.

OPTION C — Navigation, filter, and server-side indexed search.
Pros: powerful.
Cons: contradicts D4-04 (no server-side search) and adds infrastructure.

RECOMMENDATION: Option A.
WHY: It delivers practical advanced navigation without reopening the accepted
client-side search decision or adding infrastructure.
IMPACT IF DEFERRED: P6-006 cannot be scoped.

### D6-08 — Speaker labels / annotations / bookmarks
QUESTION: Are manual speaker labels, annotations, or bookmarks in Phase 6?

OPTION A — None; defer all to a separate capability/phase.
Pros: keeps Phase 6 focused on editing/navigation.
Cons: leaves speaker labeling unmet.

OPTION B — Manual speaker-label assignment only (no automatic diarization).
Pros: adds speaker value without a new model.
Cons: requires a label model and UX; interacts with edit layer.

OPTION C — Manual labels + annotations + bookmarks.
Pros: richest.
Cons: largely unproven value; scope growth.

RECOMMENDATION: Option A (defer), with manual speaker labels as the first
candidate if the HPO wants speaker value before a diarization phase.
WHY: No diarization engine exists and annotation/bookmark value is unproven;
Phase 6 should not absorb an unbounded surface.
IMPACT IF DEFERRED: No task scoped; speaker features remain future work.

### D6-09 — Waveform / timeline
QUESTION: Is waveform/timeline in Phase 6?

OPTION A — No; defer.
Pros: avoids high browser/compute cost and a new generation pipeline; keeps
Phase 6 scoped.
Cons: no visual timeline.

OPTION B — Client-side waveform for short media only.
Pros: some value without server generation.
Cons: inconsistent across media sizes; performance risk.

OPTION C — Full server-side waveform generation.
Pros: consistent visuals.
Cons: new processing surface, storage, and cost; not required for editing.

RECOMMENDATION: Option A.
WHY: It was explicitly excluded from Phase 4 (D4-06) and is not required for
editing; including it would expand scope and infrastructure substantially.
IMPACT IF DEFERRED: None for editing; remains a future candidate.

---

## Phase 7 — Production Hardening

### D7-01 — Production data store
QUESTION: Which database is canonical for production?

OPTION A — Retain SQLite.
Pros: zero migration; adequate for single-admin/low concurrency.
Cons: write-concurrency limits observed in Phase 2; scaling ceiling.

OPTION B — Migrate to PostgreSQL (or MySQL) before production.
Pros: robust concurrency; mature operations/backups.
Cons: migration effort and risk.

OPTION C — Support multiple databases.
Pros: flexibility.
Cons: highest testing/ops cost; dialect divergence risk.

RECOMMENDATION: Option B if multi-user concurrency is expected; otherwise
Option A with a documented ceiling.
WHY: The decision should follow the production concurrency target, not
convenience; Phase 2 already exposed SQLite concurrency friction.
IMPACT IF DEFERRED: P7-002/P7-007/P7-008 cannot be scoped; production readiness
gate blocked.

### D7-02 — Queue/worker supervision and Horizon
QUESTION: What queue/worker production model is canonical?

OPTION A — Redis + process supervisor (systemd/supervisord), no Horizon.
Pros: consistent with ADR-017; simpler; health checks + failed-job visibility
can be built.
Cons: less built-in queue observability/autoscaling.

OPTION B — Redis + Horizon.
Pros: queue dashboard, metrics, autoscaling, retries.
Cons: new dependency/ops surface; ADR amendment needed.

OPTION C — Remaining on the database queue.
Pros: no Redis ops.
Cons: not suited to production throughput.

RECOMMENDATION: Option A; Horizon only if observability/scale justifies it.
WHY: Horizon was intentionally excluded and is not automatically required; a
supervisor plus health/failed-job visibility meets the hardening goal with less
risk.
IMPACT IF DEFERRED: P7-003 cannot be scoped; queue recovery unproven.

### D7-03 — Storage strategy
QUESTION: Which storage backend is canonical for production?

OPTION A — Local private disk.
Pros: simple; already supports range streaming.
Cons: node-bound; backup/scale coupling.

OPTION B — S3-compatible object storage.
Pros: durable, scalable, decoupled.
Cons: range GET behavior and streaming must be re-validated; egress cost.

OPTION C — Hybrid abstraction (local default, S3 option).
Pros: flexible; preserves current behavior while enabling S3.
Cons: two code paths to test.

RECOMMENDATION: Option C, with S3 validated for range streaming.
WHY: Preserves the working local abstraction while providing a production path,
without forcing a risky migration during hardening.
IMPACT IF DEFERRED: P7-004/P7-009 cannot be scoped; streaming/seekability
unproven in production.

### D7-04 — Malware scanning decision
QUESTION: Is uploaded-media malware scanning in scope for production?

OPTION A — Yes: scan uploads (for example ClamAV) before promotion.
Pros: defends against malicious uploads.
Cons: adds dependency, latency, and capacity.

OPTION B — No: rely on type/size validation and private storage.
Pros: simpler.
Cons: residual malware risk.

OPTION C — Quarantine + asynchronous scan.
Pros: balances latency and safety.
Cons: more pipeline complexity.

RECOMMENDATION: Option B for the initial single-admin deployment, revisiting
Option A/C when multi-user upload is enabled.
WHY: The threat model for an admin-only deployment is lower; scanning can be
added when the exposure changes.
IMPACT IF DEFERRED: P7-006 scope undefined; security sign-off incomplete.

### D7-05 — Production browser support matrix
QUESTION: Which browsers are supported in production?

OPTION A — Chromium-only baseline (matches ADR-020 verification).
Pros: matches existing automation; lowest test cost.
Cons: excludes Firefox/Safari users.

OPTION B — Chromium + Firefox.
Pros: broader coverage.
Cons: more testing (media codecs differ).

OPTION C — Chromium + Firefox + Safari/WebKit.
Pros: widest coverage.
Cons: highest cost and flakiness risk.

RECOMMENDATION: Option A initially, with Option B as a near-term target.
WHY: Verification is Chromium-based today; committing to more browsers without
evidence of need expands scope.
IMPACT IF DEFERRED: P7-010 cannot be scoped; support expectations unclear.

### D7-06 — Retention / deletion policy
QUESTION: What retention/deletion policy governs media, transcripts, derived
artifacts, and staging?

OPTION A — Keep everything indefinitely (except described cleanup of staging
and ephemeral prepared audio).
Pros: simplest.
Cons: storage growth; privacy retention risk.

OPTION B — Configurable retention with user-initiated deletion and derived
artifact cleanup.
Pros: privacy/storage control.
Cons: more implementation/verification.

OPTION C — Aggressive automatic retention (fixed windows).
Pros: bounded storage.
Cons: risk of unexpected data loss; policy complexity.

RECOMMENDATION: Option B; no destructive automatic deletion without explicit
HPO acceptance and legal/privacy validation.
WHY: Deletion is a destructive/product decision and must be explicit; Option B
supports it safely.
IMPACT IF DEFERRED: P7-011 and the production gate cannot be completed.

### D7-07 — Backup/restore objectives (RPO/RTO)
QUESTION: What backup/restore and disaster-recovery objectives apply?

OPTION A — Daily backups, documented restore drill, no strict RPO/RTO.
Pros: achievable baseline.
Cons: potential data-loss window.

OPTION B — Defined RPO/RTO (for example RPO ≤ 24h, RTO ≤ 4h) with verified
restore.
Pros: measurable resilience.
Cons: more infrastructure/testing.

OPTION C — No formal backup/DR for first deployment.
Pros: none.
Cons: unacceptable production risk.

RECOMMENDATION: Option A initially, targeting Option B before scaling.
WHY: A verified restore path is mandatory; formal RPO/RTO can follow once
usage/scale justify it.
IMPACT IF DEFERRED: P7-007 cannot be scoped; gate evidence incomplete.

### D7-08 — Concurrency / performance targets
QUESTION: What production concurrency and performance targets must be met?

OPTION A — Single-admin, low concurrency; validate one job at a time.
Pros: modest infrastructure.
Cons: no headroom shown.

OPTION B — Defined targets (for example N concurrent transcriptions/streams,
RTF threshold) with load evidence.
Pros: measurable; scales decisions.
Cons: requires load tooling/runtime.

OPTION C — No explicit targets.
Pros: none.
Cons: cannot validate readiness.

RECOMMENDATION: Option B, with concrete numbers set by the HPO against the
deployment budget.
WHY: "Production ready" must be measurable; targets enable pass/fail gating.
IMPACT IF DEFERRED: P7-009/P7-012 cannot pass a defensible gate.

---

## Cross-Phase

### DC-01 — Browser verification governance across P5/P6/P7
QUESTION: Should Playwright remain the canonical browser verification platform
for Phases 5–7, and under what authorization?

OPTION A — Extend ADR-020 to cover Phase 5/6/7.
Pros: one tool, minimal governance change.
Cons: ADR-020 was deliberately scoped to Phase 4; extension could be read as
blanket authorization.

OPTION B — Create a new cross-phase browser-testing ADR that supersedes/
generalizes ADR-020, with per-phase authorization still required.
Pros: explicit scope, preserves ADR-020 history, clear per-phase gating.
Cons: one more ADR.

OPTION C — Keep ADR-020 Phase 4-only; re-authorize browser tooling separately
for each phase.
Pros: strictest governance.
Cons: repetitive; risks inconsistent harness usage.

RECOMMENDATION: Option B.
WHY: Browser behavior is material in P5 (translation UI), P6 (editing), and P7
(browser matrix), so a single explicit cross-phase ADR with per-phase
authorization is the cleanest and least ambiguous model; it preserves the
Phase 4 record without silently extending it.
IMPACT IF DEFERRED: P5/P6/P7 browser gates lack an authorized tool; Wave browser
evidence cannot be produced under existing governance.

### DC-02 — Actor-vs-owner / tenancy semantics
QUESTION: Must actor-vs-owner and tenancy semantics be decided before Phase 7
(in particular before admin-on-behalf-of or multi-user upload)?

OPTION A — Decide before Phase 7 production gate.
Pros: prevents a security/ownership design debt at production.
Cons: adds a decision cycle before hardening.

OPTION B — Defer beyond Phase 7; keep current owner-by-`user_id`.
Pros: no scope added.
Cons: production multi-user/admin-on-behalf-of remains undefined.

OPTION C — Decide during Phase 7 hardening.
Pros: fits hardening framing.
Cons: may force schema/authorization changes mid-hardening.

RECOMMENDATION: Option A if multi-user/admin-on-behalf-of is in the first
production scope; otherwise Option B documented explicitly.
WHY: Ownership semantics are a security boundary; if production introduces
behaviors that current `user_id` ownership does not model, the decision must
precede the gate.
IMPACT IF DEFERRED: P7-006/P7-012 ownership/authorization sign-off is
incomplete.

---

## Decision Index

| ID | Phase | Short title | Status |
|---|---|---|---|
| D5-01 | 5 | Canonical translation unit | OPEN / CANDIDATE |
| D5-02 | 5 | Supported target languages | OPEN / CANDIDATE |
| D5-03 | 5 | Multiple translations per transcription | OPEN / CANDIDATE |
| D5-04 | 5 | Provider strategy | OPEN / CANDIDATE |
| D5-05 | 5 | Lifecycle ownership | OPEN / CANDIDATE |
| D5-06 | 5 | Persistence shape | OPEN / CANDIDATE |
| D5-07 | 5 | Comparison UX placement | OPEN / CANDIDATE |
| D5-08 | 5 | Translated export format set | OPEN / CANDIDATE |
| D5-09 | 5 | Real translation-model gate | OPEN / CANDIDATE |
| D6-01 | 6 | Editing model / provenance | OPEN / CANDIDATE |
| D6-02 | 6 | Undo/redo + version semantics | OPEN / CANDIDATE |
| D6-03 | 6 | Timestamp editing | OPEN / CANDIDATE |
| D6-04 | 6 | Split/merge + translation invalidation | OPEN / CANDIDATE |
| D6-05 | 6 | Revision history surface | OPEN / CANDIDATE |
| D6-06 | 6 | Comparison UX | OPEN / CANDIDATE |
| D6-07 | 6 | Navigation + search/filter | OPEN / CANDIDATE |
| D6-08 | 6 | Speaker labels / annotations / bookmarks | OPEN / CANDIDATE |
| D6-09 | 6 | Waveform / timeline | OPEN / CANDIDATE |
| D7-01 | 7 | Production data store | RESOLVED — OPTION B (self-hosted PostgreSQL; `DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026) |
| D7-02 | 7 | Queue/worker supervision + Horizon | RESOLVED — OPTION A (Redis + systemd/supervisord, no Horizon; `DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026) |
| D7-03 | 7 | Storage strategy | RESOLVED — OPTION A (local private storage; object storage deferred, not rejected; `DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026) |
| D7-04 | 7 | Malware scanning | RESOLVED — OPTION A (self-hosted ClamAV; `DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026) |
| D7-05 | 7 | Browser support matrix | RESOLVED — OPTION A (Chromium-only; `DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026) |
| D7-06 | 7 | Retention / deletion policy | RESOLVED — MODIFIED OPTION A — 30-DAY RETENTION (`DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026) |
| D7-07 | 7 | Backup/restore objectives | RESOLVED — OPTION A (daily + drill, no strict RPO/RTO; `DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026) |
| D7-08 | 7 | Concurrency / performance targets | RESOLVED — OPTION A (single-admin low-concurrency; `DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026) |
| DC-01 | X | Browser verification governance | OPEN / CANDIDATE |
| DC-02 | X | Actor-vs-owner / tenancy | OPEN / CANDIDATE |

## Explicit Non-Actions

- No decision here is final. All are OPEN/CANDIDATE.
- No task is blocked solely by this register; each phase's authorization gate
  depends on the relevant decisions being RESOLVED by the HPO.
- No DECISION_QUEUE/DECISIONS entry is created by this planning package.

## HPO Resolution — D7-01..D7-08 (2026-09-26)

Historical option text above is preserved unchanged. The HPO resolved all
eight Phase 7 decisions via `DECISION-PHASE7-OWNER-DECISIONS-001` (durable
record: ADR-026 in `DECISIONS.md`):

- D7-01 = OPTION B (self-hosted PostgreSQL).
- D7-02 = OPTION A (Redis + systemd/supervisord, no Horizon).
- D7-03 = OPTION A (local private storage; object storage deferred, not
  rejected; migration not authorized).
- D7-04 = OPTION A (self-hosted ClamAV; no third-party cloud scanning).
- D7-05 = OPTION A (Chromium-only initial support).
- D7-06 = MODIFIED OPTION A — 30-day retention auto-purge (owner-policy
  portion of TD-007 resolved; implementation open).
- D7-07 = OPTION A (daily backups + executed restore drill, no strict
  RPO/RTO SLA).
- D7-08 = OPTION A (single-admin low-concurrency; P7-009 still owes a
  measurable capacity envelope).

Owner-policy only: no implementation authorization is granted by these
resolutions. Index rows above updated to RESOLVED accordingly.