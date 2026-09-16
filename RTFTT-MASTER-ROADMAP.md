# RTFTT Master Roadmap

## Purpose and Authority

This is the repository-aligned engineering sequencing reference. It does not
authorize implementation by itself. `AGENTS.md`,
`.ai/guidelines/orchestration-policy.md`, accepted ADRs, `plan.md`,
`CURRENT_STATE.md`, and task acceptance contracts retain their established
authority. The State-to-Action Contract is defined only in
`.ai/guidelines/orchestration-policy.md`.

Voxora is the long-term product and brand. RTFTT is the current
engineering/repository identity. The product vision is strategic direction,
not implementation authorization. V2–V5 product evolution remains future
direction until separately mapped and authorized through the repository phase
process.

## Canonical Engineering Phases

The following phase numbers are established and must not be renumbered by a
future product roadmap:

| Phase | Scope | Status |
|---|---|---|
| 1 | Application Foundation + Full Clickable Prototype | Accepted |
| 2 | Real File Upload & Media Library | COMPLETE_WITH_DEFERRED_DEBT (closed 2026-09-17) |
| 3 | Real Transcription Engine (ADR-017) | PLANNING RECONCILED; implementation not authorized |
| 4 | Future (to be reconciled after Phase 3) | Not yet reconciled |
| 5 | Future (to be reconciled after Phase 3) | Not yet reconciled |
| 6 | Advanced Transcript UX | Future; not authorized |
| 7 | Production Hardening | Future; not authorized |

Phase 3 boundary amended by ADR-017 (2026-09-17): Phase 3 is the complete
Real Transcription Engine encompassing transcription domain, provider
abstraction, internal Python worker, FFmpeg preparation, self-hosted
faster-whisper, Redis queue, transcript/segment persistence, multilingual/
code-switching support, retry/recovery, and real end-to-end integration
verification. The earlier Phase 3/4/5 decomposition (FFmpeg-only, faster-
whisper worker, Laravel-worker integration) is superseded by ADR-017.

`plan.md` is the execution roadmap and `CURRENT_STATE.md` is the operational
status record. Completion of one phase never authorizes the next phase.

## Phase Boundaries

Phase 2 remains bounded by ADR-008 and ADR-009. It does not introduce
FFmpeg/FFprobe, queues, Redis, Horizon, faster-whisper, transcription
generation, or future SaaS schema. The accepted application upload boundary is
one file with an exact 500 MiB (`524,288,000`-byte) limit, the accepted media
matrix, no duration limit, duplicate uploads allowed, and private storage.

Phase 3 (ADR-017) is the complete Real Transcription Engine. Before Batch 1
may be promoted to READY, separate Human Product Owner authorization is
required. The Turbo vs Large-v3 benchmark gate must be completed before
P3-003 is finalized.

Phases 4 and 5 as previously defined are absorbed into Phase 3 by ADR-017.
Their future boundaries will be reconciled separately after Phase 3 approval.

ADR-002 establishes the high-level direction for an independently deployable
transcription worker. The worker operational contract is now defined within
Phase 3 scope by ADR-017.

The transcription-engine boundary remains thin and replaceable. A
multi-provider registry, routing, fallback engine, complex capability
registry, and broad provider-error taxonomy are future capabilities requiring
evidence from a second-provider requirement.

## Future Product Stages

The following are intentionally unnumbered future roadmap candidates. They do
not steal or redefine the canonical phase identifiers:

- transcript workspace evolution;
- file and workspace management;
- richer export;
- translation;
- search and discovery;
- advanced AI and conversation intelligence;
- realtime capabilities;
- collaboration and team ownership;
- SaaS/commercial capabilities; and
- organizational knowledge intelligence.

Before collaboration, admin-on-behalf-of, team ownership, or commercial
multi-tenancy, actor-versus-owner and tenancy semantics must be explicitly
decided. Current `user_id` ownership is not treated as a complete future
multi-tenancy model.

## Production Gate

Before first production use, the repository must have approved decisions and
evidence for deployment procedure, migration safety, rollback, backup and
restore verification, monitoring and failed-job visibility, retention and
deletion, derived-artifact deletion, log/privacy behavior, and required
legal/privacy validation. These are future gates, not Phase 2 or Phase 3 scope.
