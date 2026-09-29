# RTFTT Transcribe AI Development Plan

## Current Authorized Phase

Phase 2 — COMPLETE_WITH_DEFERRED_DEBT

Phase 3 — CLOSED (2026-09-19)

Phase 4 — CLOSED (2026-09-20, DECISION-PHASE4-CLOSURE-001); P4-001..P4-006 DONE.

Phase 5 — CLOSED (2026-09-23, DECISION-PHASE5-CLOSURE-001). All P5 tasks DONE.
Phase 6 — CLOSED (2026-09-25, `DECISION-PHASE6-CLOSURE-001`); P6-001..P6-010
DONE (terminal gate P6-009 DONE via independently VERIFIED rerun
`P6-009-RERUN-01`; `DECISION-P6-009-CLOSURE-001`); D6-08/D6-09 remain
DEFERRED. Phase 7 = NOT CLOSED — partial hardening: Waves 1/2/3A
CLOSED, P7-011 DONE (`DECISION-P7-011-CLOSURE-001`), P7-009
IN_PROGRESS Phase A only (Phase B NOT AUTHORIZED;
`DECISION-P7-009-PHASE-A-EXECUTION-AUTHORIZATION-001`); P7-007 drill
deferred; TD-008/TD-014 BACKLOG, NOT AUTHORIZED; P7-012
FINAL_GATE_ONLY, NOT AUTHORIZED. Processing Provider track = CLOSED
(PP-T1..PP-T6 DONE; authorizes no post-PP work).

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
CLOSED (2026-09-23, DECISION-PHASE5-CLOSURE-001; ADR-022). All P5 tasks DONE
(P5-001, P5-001A, P5-002, P5-002A, P5-002B, P5-003, P5-004, P5-004B, P5-004C,
P5-005, P5-006, P5-007, P5-008). P5-008 was independently VERIFIED
(`reviews/P5-008-independent-review.md`; no BLOCKER/HIGH/MEDIUM) and closed DONE
(`DECISION-P5-008-CLOSURE-001`); ADR-024 adopted the canonical runtime
(`transformers==5.17.0`/`torch==2.14.0`/`sentencepiece==0.2.2`), clean NLLB
cache, committed real-gate harness, and browser-to-real-model proof. LOW/INFO
debt carried non-blocking (`DECISION-PHASE5-DEBT-CARRYFORWARD-001`). Final
report: `PHASE5-CLOSURE-REPORT.md`. Controlled-parallel execution applies
(ADR-023).

### Phase 6 — Advanced Transcript UX
CLOSED (2026-09-25, `DECISION-PHASE6-CLOSURE-001`); P6-001..P6-010 DONE
(see completion record at the end of this section; the AUTHORIZED FOR
CONTRACT AUTHORING + IMPLEMENTATION status below is the HISTORICAL
2026-09-23 entry posture, preserved).
AUTHORIZED FOR CONTRACT AUTHORING + IMPLEMENTATION (2026-09-23,
`DECISION-PHASE6-AUTHORIZATION-001`; D6-01..D6-09 + DC-01 adopted via
`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025). No blanket READY. P6-001 = DONE
(contract foundation), P6-006 = DONE (early-start), P6-002 = DONE (revision
persistence; corrective strict-ancestor undo fix independently re-verified;
`DECISION-P6-002-CLOSURE-001`). The P6-001/P6-002 foundation is frozen downstream
input. P6-003 = DONE (text editing/undo-redo; independently VERIFIED with no
remaining BLOCKER/HIGH/MEDIUM; `DECISION-P6-003-CLOSURE-001`). P6-007
source/translation comparison (presentation-only under
`DECISION-P6-007-SCOPE-001`) was implemented, reviewed CHANGES_REQUESTED for one
MEDIUM presentation-truthfulness finding, corrected, confirmed closed by a fresh
independent corrective re-review (VERIFIED; no remaining
BLOCKER/HIGH/MEDIUM/LOW/INFO), and closed **DONE**
(`DECISION-P6-007-CLOSURE-001`). P6-004 = DONE (contract authored; promoted READY
`DECISION-P6-004-READY-001`; timing-only append-only revision edits;
`EditKind::Timing` → `TimingChanged` with no staleness persistence; machine timing
immutable; playback/navigation on active-revision timing; independently reviewed
VERIFIED with no remaining BLOCKER/HIGH/MEDIUM; `DECISION-P6-004-CLOSURE-001`).
P6-005 was unblocked and reclassified `CONTRACT_REQUIRED / READY-ELIGIBLE AFTER
CONTRACT` (`DECISION-P6-005-ELIGIBILITY-001`); its five owner decisions are
DECIDED, its canonical contract is reconciled to incorporate them, and it is
promoted **READY** and authorized for implementation (`DECISION-P6-005-READY-001`).
P6-005 was implemented (structural split/merge, revision-segment language
provenance, additive translation-staleness schema, atomic invalidation, required
UI, tests, real-browser DC-01 evidence), independently reviewed VERIFIED with no
BLOCKER/MAJOR finding (all 14 acceptance criteria PASS), and closed **DONE**
(`DECISION-P6-005-CLOSURE-001`, 2026-09-25).
P6-008 implemented, independently reviewed VERIFIED (all AC1–AC10 PASS, no
BLOCKER/MAJOR/MINOR), and closed **DONE** (`DECISION-P6-008-CLOSURE-001`,
2026-09-25); P6-009 EXECUTED 2026-09-25, verdict FAIL
(F-001 MAJOR: AC6 — exports render machine source, not the active revision;
AC1–AC5 and AC7–AC11 PASS; evidence
`verification/p6-009/P6-009-FINAL-GATE-EVIDENCE.md`; F-001 routed for separate
HPO authorization, re-run required). HPO decided REMEDIATE (HPO-F001-A,
`DECISION-HPO-F001-A`, 2026-09-25); P6-010 remediation contract approved and
promoted `DRAFT` → READY (`DECISION-P6-010-READY-001`, HPO-P6-010-A APPROVED;
DONE `DECISION-P6-010-CLOSURE-001`, 2026-09-25 — independently VERIFIED, all
AC1–AC11 PASS, no findings; F-001 remediation RESOLVED, historical P6-009
FAIL preserved). Full rerun `P6-009-RERUN-01` executed 2026-09-25 under
direct HPO rerun authorization (late-persisted
`DECISION-P6-009-RERUN-01-AUTHORIZATION-001`): verdict PASS (AC1–AC11 fresh);
independently reviewed VERIFIED with one MAJOR procedural finding
(authorization-record gap) reconciled in
`reviews/P6-009-RERUN-01-GOVERNANCE-RECONCILIATION.md` and HPO-accepted for
closure (`DECISION-P6-009-CLOSURE-001`, 2026-09-25). P6-009 is DONE
(rerun PASS independently VERIFIED; historical FAIL preserved). Phase 6
terminal gate completed; Phase 6 is CLOSED
(`DECISION-PHASE6-CLOSURE-001`, 2026-09-25; P6-001..P6-010 DONE; closure
review `reviews/PHASE6-CLOSURE-REVIEW.md`). D6-08/D6-09 remain DEFERRED.
Phase 7 entry requires its own HPO entry review and authorization.
Eligibility:
`PHASE6-7-ELIGIBILITY-MATRIX.md` §T.

### Phase 7 — Production Hardening
NOT CLOSED — partial hardening (reconciled 2026-09-29; prior
"NOT GENERALLY AUTHORIZED" language below is historical). Waves 1,
2, 3A CLOSED; P7-011 DONE; P7-009 IN_PROGRESS Phase A only (Phase B
NOT AUTHORIZED); P7-007 drill deferred; TD-008/TD-014 BACKLOG, NOT
AUTHORIZED; P7-012 FINAL_GATE_ONLY, NOT AUTHORIZED. Historical
progression preserved below; tails marked superseded where
overwritten by later decisions. P7-005 (Observability Foundation)
was authorized early and is DONE (`DECISION-P7-005-CLOSURE-001`); Wave 1
(P7-003, P7-008, P7-010) was subsequently execution-authorized, implemented,
independently VERIFIED, and closed DONE (Wave 1 CLOSED; P7-008 AC2 carried
forward, NOT PASS). [The following two sentences are HISTORICAL,
superseded by Wave 2 authorization and closure below:] Wave 2 (P7-001,
P7-006, P7-007) is READY, execution NOT AUTHORIZED. All later P7 work
remains unauthorized unless separately approved.
Cannot close before Phase 6; P7-012 remains FINAL_GATE_ONLY.

Phase 7 entry progress (2026-09-26; no execution authorization): D7-01..D7-08
RESOLVED (ADR-026, `DECISION-PHASE7-OWNER-DECISIONS-001`); scope contract
ADOPTED (`PHASE7-SCOPE-CONTRACT.md`, `DECISION-PHASE7-SCOPE-ADOPTION-001`);
Wave 1 contracts authored and promoted READY — P7-003, P7-008, P7-010
(`DECISION-PHASE7-WAVE1-READY-PROMOTION-001`). READY != EXECUTION
AUTHORIZATION; no implementation may begin before a separate explicit HPO
Wave 1 execution authorization. [HISTORICAL intermediate — superseded:
Wave 1 execution authorized and Wave 1 closed DONE; see below.]

Wave 1 execution authorized 2026-09-26
(`DECISION-PHASE7-WAVE1-EXECUTION-AUTHORIZATION-001`; readiness confirmed,
no blocking finding): P7-003/P7-008/P7-010 may move READY → IN_PROGRESS
when work begins, strictly within adopted contracts. Final state:
`PHASE 7 WAVE 1 — AUTHORIZED FOR EXECUTION`. No later wave authorized.
[HISTORICAL intermediate — superseded: Wave 1 closed DONE; Waves 2/3A,
P7-011, and P7-009 Phase A later authorized; see below.]

Wave 1 closed 2026-09-26 (`reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md`:
P7-003/P7-010 VERIFIED; P7-008 VERIFIED with environmental AC2 gap, no
defect; closures `DECISION-P7-003-CLOSURE-001`,
`DECISION-P7-010-CLOSURE-001`, `DECISION-P7-008-CLOSURE-001` under Option A
exception `DECISION-P7-008-AC2-DISPOSITION-001`; AC2 NOT PASS, carried
forward to P7-001 certification pre-P7-012). Final state:
`PHASE 7 WAVE 1 = CLOSED.` Wave 2 execution not authorized; Wave 2 contracts
promoted READY (see Wave 2 READY paragraph below). [HISTORICAL
intermediate — superseded: Wave 2 execution authorized and Wave 2
closed DONE; see below.]

Wave 2 READY promotion 2026-09-26
(`DECISION-PHASE7-WAVE2-READY-PROMOTION-001`): P7-001 reconciled
against final Wave 1 DONE interfaces (incl. binding AC2/AC8 real-host
carry-forward pre-P7-012); P7-006 reconfirmed, no semantic
reconciliation; P7-007 hooks verified verbatim, foundation-only, drill
deferred to Wave 3. P7-001/P7-006/P7-007 = READY. Final state:
`PHASE 7 WAVE 2 TASKS READY — EXECUTION NOT AUTHORIZED.` No
implementation authorized. [HISTORICAL intermediate — superseded: Wave
2 execution authorized and Wave 2 closed DONE; see below.]

Wave 2 execution authorized 2026-09-26
(`DECISION-PHASE7-WAVE2-EXECUTION-AUTHORIZATION-001`; readiness confirmed,
no BLOCKER/HIGH): P7-001/P7-006/P7-007 may move READY → IN_PROGRESS
when work begins, strictly within adopted contracts. Final state:
`PHASE 7 WAVE 2 — AUTHORIZED FOR EXECUTION`. No later wave authorized.
[HISTORICAL intermediate — superseded: Wave 2 closed DONE; Wave 3A,
P7-011, and P7-009 Phase A later authorized; see below.]

Wave 2 closed 2026-09-26 (`reviews/PHASE7-WAVE2-INDEPENDENT-REVIEW.md`:
all three VERIFIED, no BLOCKER/HIGH; closures `DECISION-P7-001-CLOSURE-001`
(under Option A exception `DECISION-P7-001-AC8-DISPOSITION-001`; AC8 NOT
PASS, carried forward pre-P7-012), `DECISION-P7-006-CLOSURE-001`,
`DECISION-P7-007-CLOSURE-001`; TD-008 reprioritized
`DECISION-TD-008-REPRIORITIZATION-001`). Final state:
`PHASE 7 WAVE 2 = CLOSED.` Wave 3 not authorized. [HISTORICAL
intermediate — superseded: Wave 3A promoted, authorized, and closed
DONE; see below.]

Wave 3A closed 2026-09-26 (`reviews/PHASE7-WAVE3A-INDEPENDENT-REVIEW.md`:
P7-002/P7-004 VERIFIED, no BLOCKER/HIGH; closures
`DECISION-P7-002-CLOSURE-001` (under Option A exception
`DECISION-P7-002-PG-ENV-DISPOSITION-001`; AC1/AC2/AC5/AC6 pg-halves NOT
PASS, carried forward pre-P7-012) and `DECISION-P7-004-CLOSURE-001`;
TD-008 stays OPEN/MEDIUM/pre-P7-012). Final state:
`PHASE 7 WAVE 3A = CLOSED.` P7-009 READY (execution NOT AUTHORIZED;
`DECISION-P7-009-READY-PROMOTION-001`); P7-011 READY with execution
AUTHORIZED (`DECISION-P7-011-EXECUTION-AUTHORIZATION-001`); P7-007 drill
deferred; P7-012 FINAL_GATE_ONLY. [HISTORICAL intermediate —
superseded: P7-009 Phase A execution-authorized and executed
(IN_PROGRESS; Phase B NOT AUTHORIZED); P7-011 closed DONE
(`DECISION-P7-011-CLOSURE-001`, 2026-09-26; TD-007 OPEN, TD-014 OPEN).]
P7-011 subsequently closed DONE
(`DECISION-P7-011-CLOSURE-001`, 2026-09-26; TD-007 OPEN, TD-014 OPEN).

The earlier Phase 4/5/6/7 decomposition is historical and was reconciled by
ADR-019.

## Roadmap Reconciliation

The repository engineering phase numbers are canonical and must not be
renumbered by an external product roadmap:

1. Application Foundation + Full Clickable Prototype — ACCEPTED
2. Real File Upload & Media Library — COMPLETE_WITH_DEFERRED_DEBT
3. Real Transcription Engine (ADR-017) — CLOSED (2026-09-19; Batch 1/Batch 2/Batch 3 closed)
4. Transcript Experience baseline (ADR-019) — CLOSED (2026-09-20, DECISION-PHASE4-CLOSURE-001); P4-001..P4-006 DONE
5. Translation — CLOSED (2026-09-23, DECISION-PHASE5-CLOSURE-001); P5-001..P5-008 DONE
6. Advanced Transcript UX — CLOSED (2026-09-25, DECISION-PHASE6-CLOSURE-001); P6-001..P6-010 DONE (P6-009 DONE via independently VERIFIED rerun `P6-009-RERUN-01`); D6-08/D6-09 DEFERRED; P6-001/P6-002 foundation frozen downstream input
7. Production Hardening — NOT CLOSED, partial hardening: Waves 1/2/3A CLOSED; P7-011 DONE; P7-009 IN_PROGRESS Phase A only (Phase B NOT AUTHORIZED); P7-007 drill deferred; TD-008/TD-014 BACKLOG, NOT AUTHORIZED; P7-012 FINAL_GATE_ONLY, NOT AUTHORIZED
8. Processing Provider (ADR-027) — CLOSED; PP-T1..PP-T6 DONE (final gate passed; authorizes no post-PP work)

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
CLOSED (2026-09-19, DECISION-PHASE3-CLOSURE-001). Phase 4 is CLOSED
(DECISION-PHASE4-CLOSURE-001, 2026-09-20); Phase 5 is CLOSED
(DECISION-PHASE5-CLOSURE-001, 2026-09-23). Phase 6 = CLOSED
(2026-09-25, `DECISION-PHASE6-CLOSURE-001`); P6-001..P6-010 DONE. Phase 7
= NOT CLOSED, partial hardening (Waves 1/2/3A CLOSED; P7-011 DONE;
P7-009 IN_PROGRESS Phase A only, Phase B NOT AUTHORIZED).
Phase 2 is closed as COMPLETE_WITH_DEFERRED_DEBT.
