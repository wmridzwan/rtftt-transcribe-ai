# RTFTT Transcribe AI Development Plan

## Current Authorized Phase

Phase 2 — Accepted bounded Phase 2 scope

Status:
Product Owner acceptance recorded in ADR-014 on 2026-09-13. The independent
cycle-2 re-review is reconciled: P2-003 and P2-005 are DONE; P2-004A and
P2-004A1 remain BLOCKED and are deferred out of the current Phase 2 completion
scope under ADR-013. Phase 2 is ACCEPTED. P2-007 is DONE (closed
2026-09-17). Phase 3 remains unauthorized.

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

## Future Phases (NOT Authorized)

### Phase 2 — Real File Upload & Media Library
- Binary file uploads
- Large file upload handling
- Multipart uploads
- Chunked uploads
- Storage optimization

### Phase 3 — FFmpeg / FFprobe Media Processing
- Audio extraction
- Video probing
- Media conversion
- Waveform generation

### Phase 4 — Independent faster-whisper Worker
- Python transcription worker
- faster-whisper integration
- GPU support / CUDA
- Model downloading
- Language detection

### Phase 5 — Laravel ↔ Transcription Worker Integration
- Queue infrastructure
- Redis / Horizon
- Worker orchestration
- Result callbacks

### Phase 6 — Advanced Transcript UX
- Translation
- Summarization
- Speaker diarization
- AI analysis
- Chat with transcript

### Phase 7 — Production Hardening
- Production PostgreSQL migration
- S3 / Cloudflare R2 / MinIO
- Production deployment
- Billing / subscriptions
- Public API

The ADR-012 continuation authorizes only remediation of the named P2-003,
P2-004A, P2-004A1, and P2-005 surfaces. No P2-007, transcription, processing,
or broader Phase 3 behavior is authorized.

## Roadmap Reconciliation

The repository engineering phase numbers above are canonical and must not be
renumbered by an external product roadmap:

1. Application Foundation + Full Clickable Prototype
2. Real File Upload & Media Library
3. FFmpeg / FFprobe Media Processing
4. Independent faster-whisper Worker
5. Laravel ↔ Transcription Worker Integration
6. Advanced Transcript UX
7. Production Hardening

Voxora is the long-term product and brand; RTFTT remains the current
engineering/repository identity. Product evolution beyond Phase 7 may include
transcript workspace evolution, file/workspace management, richer export,
translation, search/discovery, advanced AI, realtime, collaboration, SaaS, and
organizational knowledge capabilities. These are unnumbered future roadmap
stages unless separately approved and mapped to repository phases. They do not
authorize implementation or redefine the established Phase 3–7 identifiers.

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
No P2-006 implementation was required. P2-007 is DONE; Phase 3 remains unauthorized;
this closure does not mark Phase 2 complete.
