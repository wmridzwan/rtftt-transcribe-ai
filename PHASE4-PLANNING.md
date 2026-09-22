# Phase 4 — Transcript Experience (baseline) — Planning / Decision Package

Date: 2026-09-19
Status: AUTHORIZED FOR TASK-CONTRACT AUTHORING — implementation NOT authorized
Authority: ADR-019 (ACCEPTED); `DECISION-PHASE4-AUTHORIZATION-001`;
`RTFTT-MASTER-ROADMAP.md`; `plan.md`; `CURRENT_STATE.md`; ADR-017; ADR-018;
`.ai/guidelines/orchestration-policy.md`
Scope: Phase 4 = Transcript Experience (baseline); Phase 5 = Translation;
Phase 6/7 remain reserved.

Update (2026-09-19): The Human Product Owner resolved D4-01 through D4-07 and
ratified ADR-019 (PROPOSED → ACCEPTED), then authorized Phase 4 for
task-contract authoring under `DECISION-PHASE4-AUTHORIZATION-001`. Task contracts
P4-001 through P4-006 were created in BACKLOG. Phase 4 implementation is NOT
authorized, and no task may be promoted to READY without a separate task/batch
implementation authorization. This package remains the planning artifact.

This package records the planning basis and the HPO decisions. ADR-019 is
ACCEPTED and Phase 4 is authorized for task-contract authoring
(DECISION-PHASE4-AUTHORIZATION-001). This package does not authorize Phase 4
implementation, does not promote anything to READY, and does not authorize any
application code, test, migration, route, controller, Livewire component,
streaming endpoint, JavaScript, or UI. Phase 4 task promotion requires a separate
Human Product Owner task/batch implementation authorization.

## A. Repository State Confirmed

Verified directly against the repository at planning time:

- Phase 1 = ACCEPTED.
- Phase 2 = COMPLETE_WITH_DEFERRED_DEBT (closed 2026-09-17); Option D (ADR-013)
  remains in force; P2-004A/P2-004A1 remain BLOCKED historical debt.
- Phase 3 = CLOSED (2026-09-19, DECISION-PHASE3-CLOSURE-001); Batch 1/2/3
  CLOSED; P3-001 through P3-008 = DONE.
- Canonical model = `large-v3` (turbo = non-default/experimental).
- Phase 4 = NOT AUTHORIZED. No `DECISION-P4-*` entry exists. No `P4-*` task file
  exists.
- `RTFTT-MASTER-ROADMAP.md`, `plan.md`, `CURRENT_STATE.md`, `AGENTS.md`, and
  `DECISION_QUEUE.md` all consistently record Phase 4 as not authorized.
- The roadmap reserves Phase 6 = Advanced Transcript UX and Phase 7 = Production
  Hardening. Phases 4 and 5 are "to be reconciled after Phase 3" (ADR-017).

Conclusion: the bootstrap's canonical state is confirmed by the repository.

## B. Why Phase 4 Requires Reconciliation

ADR-017 absorbed the earlier Phase 3/4/5 decomposition (FFmpeg-only /
faster-whisper worker / Laravel-worker integration) into the now-closed Phase 3,
and explicitly left Phase 4/5 boundaries unreconciled. The product priority order
(`PROJECT_CONTEXT.md` §24, §30) after reliable transcription is:

```text
Reliable Transcription (Phase 3, done)
  → Excellent Transcript UX   (candidate Phase 4)
  → Reliable Translation      (candidate Phase 5)
  → Advanced AI
  → Live
  → Scale / Production Hardening
```

Phase 4 is therefore the read/navigate/search/copy/export experience over the
real, persisted Phase 3 transcripts, plus authorized media playback. Phase 5 is
translation. Phase 6 keeps advanced transcript UX (editing, diarization/speaker
labels, chapters, annotations). Phase 7 keeps production hardening.

## C. Proposed Boundary (Candidate ADR-019)

| Phase | Scope |
|---|---|
| 4 | Transcript Experience baseline: authorized playback, timestamp seeking, synchronized highlighting, in-transcript search, copy, and export over completed persisted transcripts |
| 5 | Translation: original-vs-translated, Bahasa Melayu Malaysia output, persistence, UX |
| 6 (reserved) | Advanced Transcript UX: editing, diarization/speaker labels, chapters, annotations, richer collaboration-oriented UX |
| 7 (reserved) | Production Hardening: deployment, monitoring, retention/deletion, backup/restore, PostgreSQL/Redis certification, scaling |

This boundary is a proposal. It is not in force until recorded as an accepted
ADR by the Human Product Owner (D4-01).

## D. Existing Capability Baseline (Verified)

| Capability | Current state | Phase 4 gap |
|---|---|---|
| Transcription list/show | `TranscriptionController` + `transcriptions/show.blade.php` exist | none structural |
| Segment rendering | Real segments rendered with `formatted_start` (seconds only) | no ms precision, no active state |
| Export TXT/SRT/VTT/DOCX | `TranscriptionExportController` reads real persisted segments, Completed-gated, ownership-authorized | no multilingual/unicode/ms formatting test coverage; format-set decision (D4-03) |
| Media download | `GET /media/{mediaFile}/download` exists | no range/streaming playback (D4-02) |
| Media playback | absent | player + streaming |
| Timestamp seek / sync highlight | absent | interaction layer |
| In-transcript search | absent (list-level title search only) | search semantics (D4-04) |
| Copy transcript | absent | copy actions |
| Livewire transcript component | absent (only `Media\Show`, `Folders\Index`, `Settings\*`) | new Livewire component(s) |
| Retry UI | present (Phase 3) | unchanged |

## E. Candidate Task Contracts (not created; not READY)

| Task | Scope | Depends on |
|---|---|---|
| P4-001 | Transcript Experience contract: read model for completed transcripts, timestamp precision, completed-only gating, export-format contract, media-delivery decision record, search semantics | D4-01..D4-07 resolved |
| P4-002 | Authorized private media playback: range/streaming endpoint (or approved alternative), ownership policy, correct `Content-Type`/`Accept-Ranges`/`206`, no path leakage, audio+video | P4-001 |
| P4-003 | Segment navigation + synchronized highlighting: click-to-seek, active-segment highlight, ms-accurate, preserves `und`/code-switch display | P4-002 |
| P4-004 | Transcript search + copy: search per D4-04, match highlight, copy full/segment, unicode-safe (ms/en/zh/ta) | P4-001 |
| P4-005 | Export hardening over persisted segments: verify/harden TXT/SRT/VTT (+DOCX per D4-03), ms formatting, UTF-8 multilingual round-trip, completed gating | P4-001 |
| P4-006 | Phase 4 integration verification: real completed transcription → open → playback → seek → search → export; independent gate | P4-002..P4-005 |

Task contracts P4-001 through P4-006 were created in BACKLOG under
`DECISION-PHASE4-AUTHORIZATION-001`. They are not READY and implementation is
not authorized.

## F. Owner Decisions (Resolved 2026-09-19)

| ID | Decision | Outcome |
|---|---|---|
| D4-01 | Phase 4/5/6/7 boundary | ACCEPTED as proposed; ADR-019 ACCEPTED |
| D4-02 | Media delivery model | Authorized range-stream endpoint behind existing ownership policy; no signed-URL baseline |
| D4-03 | Export format set | Keep TXT/SRT/VTT/DOCX; harden + verify, do not rebuild |
| D4-04 | Search | Client-side over loaded segments; no server-side/index |
| D4-05 | Transcript editing | Excluded from Phase 4; deferred to Phase 6 |
| D4-06 | Player scope | Audio+video, play/pause, volume, seek, click-timestamp seek, ms target, synchronized highlight |
| D4-07 | Authorization/review model | Per-task review; no batch exception; P4-006 final gate |

All decisions are DECIDED. Durable record: ADR-019 and `DECISION_QUEUE.md`
(D4-01 through D4-07; DECISION-PHASE4-AUTHORIZATION-001).

## G. Dependency / Authorization Order

```text
Phase 4 planning (this package)
→ HPO ratifies boundary (ADR-019) and resolves D4-01..D4-07
→ HPO phase-authorization decision
→ P4 task contracts authored
→ HPO batch authorization
→ READY → IN_PROGRESS → REVIEW → independent review
→ VERIFIED → HPO closure → DONE
```

Per `.ai/guidelines/orchestration-policy.md`, completing a phase never
authorizes the next phase, and `VERIFIED` never equals `DONE` until HPO closure.

## H. Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Phase 4 scope creep into editing/AI/translation | Explicit non-scope list (§J); boundary ratified by D4-01 |
| Streaming endpoint leaks private media | Reuse `MediaFilePolicy`; opaque references only; cross-user denial tests |
| Large media loading into PHP memory | Stream with range requests; never read whole file into memory |
| Timestamp precision regression | Assert ms accuracy for seek and SRT/VTT formatting |
| Multilingual/unicode export corruption | UTF-8 round-trip tests for ms/en/zh/ta and `und` |
| Search over unicode (zh/ta) misbehaves | Define semantics in P4-001; test with multilingual fixtures |
| Phase 3 contracts regressed | Phase 4 is read-only over persisted data; run full regression in P4-006 |
| Phase 4 treated as authorized by this package | Explicit non-actions (§L); all records keep Phase 4 = NOT AUTHORIZED |

## I. Migration / Compatibility

- Phase 4 is expected to require **no schema change**: it reads existing
  `transcriptions`, `transcription_segments`, and `media_files`.
- Any server-side search may need an additive index only; this is a P4-001
  decision, not assumed here.
- No historical migration may be modified.
- Phase 3 queue/retry/failure-taxonomy/language/no-speech/atomic-persistence/
  media contracts and completed-transcription protection remain authoritative
  and unchanged.

## J. Explicit Non-Scope

- translation (Phase 5)
- transcript editing, diarization/speaker labels, chapters, annotations
  (Phase 6)
- AI/summary/chat, live/realtime transcription or translation
- Horizon, automatic domain retry, provider-abstraction redesign
- retranscription/reprocessing of completed transcriptions
- production deployment, monitoring, retention/deletion, backup/restore,
  PostgreSQL/Redis certification (Phase 7)
- actor-vs-owner, admin-on-behalf-of, multi-tenancy semantics

## K. Files Created or Updated

- `PHASE4-PLANNING.md` — this package.
- `DECISIONS.md` — ADR-019 (ACCEPTED) with decided outcomes.
- `DECISION_QUEUE.md` — D4-01 through D4-07 DECIDED;
  DECISION-PHASE4-AUTHORIZATION-001 DECIDED.
- `tasks/P4-001..P4-006` — implementation-ready contracts created in BACKLOG.
- `CURRENT_STATE.md`, `plan.md`, `RTFTT-MASTER-ROADMAP.md`, `AGENTS.md` —
  governance records updated to Phase 4 = authorized for task-contract authoring.

No application code, test, migration, route, controller, Livewire component,
streaming endpoint, JavaScript, or UI is created. No P4 task is READY.

## L. Explicit Non-Actions

- Phase 4 implementation is NOT authorized; a separate HPO task/batch
  implementation authorization is required before any task is READY.
- No task is promoted to READY; no implementation batch is authorized.
- No Phase 3 VERIFIED/DONE contract is reopened or changed.
- No reviewer artifact is modified.
- Option D (ADR-013) is not lifted.
