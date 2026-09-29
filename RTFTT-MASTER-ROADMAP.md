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
| 3 | Real Transcription Engine (ADR-017) | CLOSED (2026-09-19) — Batch 1 closed (2026-09-18); Batch 2 closed (2026-09-19); Batch 3 closed (2026-09-19, P3-007 DONE, P3-008 DONE) |
| 4 | Transcript Experience baseline (ADR-019) | CLOSED (2026-09-20, DECISION-PHASE4-CLOSURE-001); P4-001..P4-006 DONE |
| 5 | Translation | CLOSED (2026-09-23, DECISION-PHASE5-CLOSURE-001); P5-001..P5-008 DONE |
| 6 | Advanced Transcript UX | CLOSED (2026-09-25, DECISION-PHASE6-CLOSURE-001); P6-001..P6-010 DONE; D6-08/D6-09 DEFERRED |
| 7 | Production Hardening | NOT CLOSED — partial hardening: Waves 1/2/3A CLOSED, P7-011 DONE, P7-009 IN_PROGRESS Phase A only (Phase B NOT AUTHORIZED); P7-012 FINAL_GATE_ONLY, NOT AUTHORIZED |
| PP | Processing Provider (ADR-027) | CLOSED; PP-T1..PP-T6 DONE (final gate passed; authorizes no post-PP work) |

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

Phase 3 (ADR-017) is the complete Real Transcription Engine. Batch 1
(P3-001/P3-002/P3-003 + the Turbo vs Large-v3 benchmark gate) was closed as
DONE on 2026-09-18 with canonical model large-v3. Batch 2 (P3-004/P3-005/
P3-006) was independently VERIFIED and closed as DONE on 2026-09-19. Batch 3
(P3-007/P3-008) was authorized by the Human Product Owner on 2026-09-19
(DECISION-P3-BATCH3-001) after owner decisions B3-01 through B3-07 were resolved
(ADR-018); P3-007 is DONE (HPO closure 2026-09-19) and P3-008 is DONE (HPO
closure 2026-09-19; independent review VERIFIED, live Redis and real
faster-whisper `large-v3` gates passed). Phase 3 Batch 3 = CLOSED
(DECISION-P3-BATCH3-CLOSURE-001). Phase 3 as a whole was then closed by the HPO
on 2026-09-19 (DECISION-PHASE3-CLOSURE-001). Phase 3 = CLOSED.

Phase 4 is reconciled by ADR-019 (ACCEPTED 2026-09-19): Phase 4 = Transcript
Experience baseline (authorized playback, timestamp seeking, synchronized
highlighting, in-transcript search, copy, export hardening over completed
persisted transcripts). Task contracts P4-001..P4-006 were authored and audited;
P4-001 is DONE. Wave 1 (P4-002, P4-004, P4-005) was authorized
(DECISION-P4-WAVE1-AUTHORIZATION-001), implemented, independently VERIFIED, and
closed as DONE; Wave 1 = CLOSED. P4-003 was implemented, independently VERIFIED,
and closed as DONE (DECISION-P4-003-CLOSURE-001). P4-006 executed and found a
P4-004 defect; P4-004 was corrected and re-closed DONE
(DECISION-P4-004-CORRECTIVE-CLOSURE-001), the finding is CLOSED, P4-006 was
re-executed (fresh final-gate rerun PASSED), independently VERIFIED, and closed
DONE (DECISION-P4-006-CLOSURE-001). Phase 4 = CLOSED (2026-09-20,
DECISION-PHASE4-CLOSURE-001). Phase 5 = Translation: CLOSED (2026-09-23,
DECISION-PHASE5-CLOSURE-001); P5-001..P5-008 DONE. Phase 6 = Advanced
Transcript UX: CLOSED (2026-09-25, DECISION-PHASE6-CLOSURE-001);
P6-001..P6-010 DONE (P6-009 via accepted `P6-009-RERUN-01` PASS;
D6-08/D6-09 DEFERRED). Phase 7 = Production Hardening: NOT CLOSED —
partial hardening only (Waves 1/2/3A CLOSED; P7-011 DONE; P7-009
IN_PROGRESS Phase A only, Phase B NOT AUTHORIZED; P7-007 drill
deferred; TD-008/TD-014 BACKLOG, NOT AUTHORIZED; P7-012
FINAL_GATE_ONLY, NOT AUTHORIZED). The Processing Provider track
(ADR-027) is CLOSED (PP-T1..PP-T6 DONE). The
earlier Phase 4/5 decomposition was absorbed into Phase 3 by ADR-017.

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
