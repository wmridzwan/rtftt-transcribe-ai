# RTFTT Transcribe AI Development Plan

## Current Authorized Phase

Phase 2 — COMPLETE_WITH_DEFERRED_DEBT

Phase 3 — CLOSED (2026-09-19)

Phase 4 — CLOSED (2026-09-20, DECISION-PHASE4-CLOSURE-001); P4-001..P4-006 DONE.

Phase 5 — AUTHORIZED FOR IMPLEMENTATION (2026-09-21,
DECISION-PHASE5-AUTHORIZATION-001; ADR-022 freezes D5-01..D5-09; ADR-021
authorizes cross-phase Playwright). P5-001/P5-002 = VERIFIED (HPO closure
pending); P5-003..P5-008 = BACKLOG. Phase 6/7 = not generally authorized;
controlled-parallel allowlists only (ADR-023).

Status:
Phase 2 closed as COMPLETE_WITH_DEFERRED_DEBT by the Human Product Owner
on 2026-09-17. P2-003, P2-005, P2-004A2, and P2-007 are DONE. P2-004A and
P2-004A1 remain BLOCKED and are deferred out of the current Phase 2 completion
scope under ADR-013. Option D under ADR-013 remains in force. Phase 3
planning reconciled under ADR-017 (Real Transcription Engine). Phase 3 Batch 1
(P3-001/P3-002/P3-003 + benchmark gate) was independently VERIFIED and closed
as DONE on 2026-09-18 (canonical model large-v3). Phase 3 Batch 2
(P3-004/P3-005/P3-006) was independently VERIFIED and closed as DONE on
2026-09-19. Phase 3 Batch 3 (P3-007/P3-008) was authorized by the HPO on
2026-09-19 (DECISION-P3-BATCH3-001) after owner decisions B3-01 through B3-07
were resolved (ADR-018); P3-007 is DONE (HPO closure 2026-09-19) and P3-008 is
DONE (HPO closure 2026-09-19; independent review VERIFIED, live Redis and real
faster-whisper `large-v3` gates passed). Phase 3 Batch 3 = CLOSED
(DECISION-P3-BATCH3-CLOSURE-001). Phase 3 as a whole was then closed by the HPO
on 2026-09-19 (DECISION-PHASE3-CLOSURE-001). Phase 3 = CLOSED. Phase 4 is
reconciled by ADR-019; P4-001..P4-006 contracts were authored and audited, and
P4-001 was authorized and promoted BACKLOG → READY
(DECISION-P4-001-AUTHORIZATION-001), independently VERIFIED, and closed as DONE
(DECISION-P4-001-CLOSURE-001). Wave 1 (P4-002, P4-004, P4-005) was then
authorized (DECISION-P4-WAVE1-AUTHORIZATION-001), implemented, independently
VERIFIED, and closed as DONE (DECISION-P4-002/004/005-CLOSURE-001); Phase 4
Wave 1 = CLOSED. The browser-verification strategy
(DECISION-P4-BROWSER-VERIFICATION-001) and the P4-003 authorization
(DECISION-P4-003-AUTHORIZATION-001) are recorded; P4-003 was independently
VERIFIED and closed DONE (DECISION-P4-003-CLOSURE-001); P4-006 was executed and
found a P4-004 defect (DECISION-P4-006-FINDING-001). P4-004 was corrected,
independently re-verified, and re-closed DONE
(DECISION-P4-004-CORRECTIVE-CLOSURE-001); the finding is CLOSED, P4-006 was
re-executed and the fresh final-gate rerun PASSED. P4-006 was closed DONE
(DECISION-P4-006-CLOSURE-001) and Phase 4 was closed
(DECISION-PHASE4-CLOSURE-001, 2026-09-20).

## Phase 1 Breakdown

### Phase 1A — Database Foundation
- Enums
- Migrations
- Models
- Relationships
- Factories
- Seeders

### Phase 1B — Application Shell
- App layout
- Sidebar
- Navigation
- Responsive behavior
- Breadcrumbs where useful
- Reusable UI components

### Phase 1C — Clickable Prototype
- Dashboard
- Transcriptions
- Prototype upload
- Transcription details
- Media library
- Media details
- Processing jobs
- Processing job details
- Settings

### Phase 1D — Roles & Authorization
- Admin
- User
- Ownership isolation
- Admin-only system pages

### Phase 1E — Demo Data
- Realistic seed data
- Multiple statuses
- Multiple languages
- Audio/video examples

### Phase 1F — Testing & Verification
- Authentication
- Authorization
- Ownership
- Relationships
- Pages
- Seeded rendering
- Migration verification
- Frontend build verification

## Future Phases

### Phase 2 — Real File Upload & Media Library
COMPLETE_WITH_DEFERRED_DEBT (closed 2026-09-17)

### Phase 3 — Real Transcription Engine (ADR-017)
CLOSED — Batch 1 CLOSED, Batch 2 CLOSED, Batch 3 CLOSED (P3-007 DONE; P3-008 DONE)

Phase 3 encompasses the complete real transcription pipeline:
transcription domain, provider abstraction, internal Python worker,
FFmpeg preparation, self-hosted faster-whisper, Redis queue, transcript
and segment persistence, multilingual/code-switching support, retry/
recovery, and real end-to-end integration verification.

The earlier Phase 3/4/5 decomposition (FFmpeg-only, faster-whisper
worker, Laravel-worker integration) is superseded by ADR-017.

Canonical tasks: P3-001 through P3-008 in `tasks/`.

Batch model:
- Batch 1: P3-001 → P3-002 → Benchmark Gate → P3-003 — CLOSED (2026-09-18)
- Batch 2: P3-004 → P3-005 → P3-006 — CLOSED (2026-09-19)
- Batch 3: P3-007 → P3-008 — CLOSED (2026-09-19, DECISION-P3-BATCH3-CLOSURE-001); P3-007 DONE, P3-008 DONE

### Phase 4 — Transcript Experience baseline (ADR-019) — CLOSED (2026-09-20)
Task contracts P4-001 through P4-006 authored. All six tasks = DONE. Wave 1
(P4-002/P4-004/P4-005) CLOSED; P4-004 completed a post-closure corrective cycle
(DECISION-P4-004-CORRECTIVE-CLOSURE-001); P4-006 final integration verification
was independently VERIFIED and closed DONE (DECISION-P4-006-CLOSURE-001). Phase 4
= CLOSED (DECISION-PHASE4-CLOSURE-001, 2026-09-20). Residual LOW/INFO debt is
retained (`reviews/PHASE4-final-closure.md`).
Review model: per-task implementation and independent review (D4-07).

### Phase 5 — Translation
AUTHORIZED FOR IMPLEMENTATION (2026-09-21, DECISION-PHASE5-AUTHORIZATION-001;
ADR-022). DONE (HPO closure 2026-09-22): P5-004B, P5-006. VERIFIED (closure
pending): P5-001, P5-001A, P5-002, P5-002A, P5-002B, P5-003, P5-004, P5-004C,
P5-005, P5-007. P5-008 = BACKLOG and gated (DECISION-P5-008-GATE-001).
Controlled-parallel execution applies (ADR-023).

### Phase 6 — Advanced Transcript UX
NOT GENERALLY AUTHORIZED; early-start allowlist only (ADR-023;
`PHASE5-7-EXECUTION-CLASSIFICATION.md`).

### Phase 7 — Production Hardening
NOT GENERALLY AUTHORIZED; early-hardening allowlist only (ADR-023;
`PHASE5-7-EXECUTION-CLASSIFICATION.md`).

The earlier Phase 4/5/6/7 decomposition is historical and was reconciled by
ADR-019.

## Roadmap Reconciliation

The repository engineering phase numbers are canonical and must not be
renumbered by an external product roadmap:

1. Application Foundation + Full Clickable Prototype — ACCEPTED
2. Real File Upload & Media Library — COMPLETE_WITH_DEFERRED_DEBT
3. Real Transcription Engine (ADR-017) — CLOSED (2026-09-19; Batch 1/Batch 2/Batch 3 closed)
4. Transcript Experience baseline (ADR-019) — CLOSED (2026-09-20, DECISION-PHASE4-CLOSURE-001); P4-001..P4-006 DONE
5. Translation — future; not authorized
6. Advanced Transcript UX — future; not authorized
7. Production Hardening — future; not authorized

Voxora is the long-term product and brand; RTFTT remains the current
engineering/repository identity. Product evolution beyond Phase 7 may include
transcript workspace evolution, file/workspace management, richer export,
translation, search/discovery, advanced AI, realtime, collaboration, SaaS, and
organizational knowledge capabilities. These are unnumbered future roadmap
stages unless separately approved and mapped to repository phases. They do not
authorize implementation or redefine established phase identifiers.

Phase 2 remains bounded by ADR-008, ADR-009, and ADR-012. P2-005 alone may use
the minimum FFprobe/FFmpeg dependency for metadata probing; it does not
introduce queues, Redis, Horizon, faster-whisper, or transcription generation.

The canonical P2-003 contract is recorded at
`tasks/P2-003-implement-real-upload-ingestion-workflow.md`; its current status
is DONE after the cycle-2 independent VERIFIED regression re-review.
P2-005 is DONE after the cycle-2 independent VERIFIED re-review. P2-004A and
P2-004A1 are BLOCKED and deferred from the current Phase 2 gate under ADR-013;
future cleanup requires separate authorization and genuine independent-
concurrency verification.

The P2-006 failure/retry planning candidate is closed as already covered by
the accepted P2-002B contract and the independently VERIFIED P2-003 workflow.
No P2-006 implementation was required. P2-007 is DONE. Phase 3 planning
reconciled under ADR-017; Phase 3 Batch 3 closed on 2026-09-19
(DECISION-P3-BATCH3-CLOSURE-001) with P3-007 DONE and P3-008 DONE. Phase 3 =
CLOSED (2026-09-19, DECISION-PHASE3-CLOSURE-001). Phase 4 is authorized for
task-contract authoring only (DECISION-PHASE4-AUTHORIZATION-001); implementation
is NOT authorized.
Phase 2 is closed as COMPLETE_WITH_DEFERRED_DEBT.
