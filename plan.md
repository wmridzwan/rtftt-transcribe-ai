# RTFTT Transcribe AI Development Plan

## Current Authorized Phase

Phase 2 — COMPLETE_WITH_DEFERRED_DEBT

Phase 3 — PLANNING RECONCILED / IMPLEMENTATION NOT AUTHORIZED

Status:
Phase 2 closed as COMPLETE_WITH_DEFERRED_DEBT by the Human Product Owner
on 2026-09-17. P2-003, P2-005, P2-004A2, and P2-007 are DONE. P2-004A and
P2-004A1 remain BLOCKED and are deferred out of the current Phase 2 completion
scope under ADR-013. Option D under ADR-013 remains in force. Phase 3
planning reconciled under ADR-017 (Real Transcription Engine). Phase 3
implementation remains not authorized — separate HPO decision required for
Batch 1.

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
PLANNING RECONCILED / IMPLEMENTATION NOT AUTHORIZED

Phase 3 encompasses the complete real transcription pipeline:
transcription domain, provider abstraction, internal Python worker,
FFmpeg preparation, self-hosted faster-whisper, Redis queue, transcript
and segment persistence, multilingual/code-switching support, retry/
recovery, and real end-to-end integration verification.

The earlier Phase 3/4/5 decomposition (FFmpeg-only, faster-whisper
worker, Laravel-worker integration) is superseded by ADR-017.

Canonical tasks: P3-001 through P3-008 in `tasks/`.

Batch model:
- Batch 1: P3-001 → P3-002 → Benchmark Gate → P3-003
- Batch 2: P3-004 → P3-005 → P3-006
- Batch 3: P3-007 → P3-008

### Phase 4+ — Future (not yet reconciled)
Phases 4–7 boundaries remain to be reconciled after Phase 3 approval.
The earlier Phase 4/5/6/7 decomposition is historical and will be
reconciled separately.

## Roadmap Reconciliation

The repository engineering phase numbers are canonical and must not be
renumbered by an external product roadmap:

1. Application Foundation + Full Clickable Prototype — ACCEPTED
2. Real File Upload & Media Library — COMPLETE_WITH_DEFERRED_DEBT
3. Real Transcription Engine (ADR-017) — PLANNING RECONCILED
4. Future (to be reconciled after Phase 3)
5. Future (to be reconciled after Phase 3)
6. Advanced Transcript UX
7. Production Hardening

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
reconciled under ADR-017; Phase 3 implementation remains not authorized.
Phase 2 is closed as COMPLETE_WITH_DEFERRED_DEBT.
