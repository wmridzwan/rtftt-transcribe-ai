# Phase 3 Governance Reconciliation (ADR-017) — Targeted Re-Review

Date: 2026-09-17
Reviewer: Claude Code (independent reviewer role, per `.ai/guidelines/orchestration-policy.md`)
Reviewed correction commit: `5b17f8f` (governance correction cycle: resolve F-01 through F-06)
Parent: `f15292e` (original reconciliation, previously reviewed VERIFIED... CHANGES_REQUESTED — see `reviews/PHASE3-GOVERNANCE-RECONCILIATION-ADR017-review.md`)
Scope: Targeted re-review of the six corrections only. Not a full re-audit of Phase 3 planning.

## A. Independent Re-Review Verdict

**CHANGES_REQUESTED**

Five of six findings (F-02 through F-06) are substantively and well resolved — the correction cycle is thorough and high quality. F-01 is *mostly* resolved but reintroduces a narrower, still-live scope-authorization ambiguity in the exact sentence this re-review was asked to scrutinize (the Redis/queue "until Batch 1 authorized" wording). Combined with a residual cross-reference gap left by F-03's fix (the new canonical spec file is not referenced by any P3 task file, and the old phantom citation was not removed), this falls short of a clean VERIFIED. Nothing here requires redesign — both are narrow wording fixes.

## B. Correction Commit Verification

`git diff --stat f15292e 5b17f8f`: exactly the five reported files changed —

- `.ai/guidelines/orchestration-policy.md` (+115)
- `AGENTS.md` (+24/−7... net +24/-7 lines shown as 24 changed)
- `DECISIONS.md` (+4/−0, actually 2 insertions/... minor addition)
- `DECISION_QUEUE.md` (+55)
- `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (new, 361 lines)

Confirmed independently:
- No `app/`, `database/`, `tests/`, `config/`, `routes/`, `resources/` paths touched.
- No `tasks/*.md` touched — all 8 P3 task files byte-identical to `f15292e`; all Status fields verified `BACKLOG`.
- No `reviews/` file touched by `5b17f8f`.
- `git status`: working tree matches OpenCode's report exactly — one untracked file, `reviews/PHASE3-GOVERNANCE-RECONCILIATION-ADR017-review.md` (the prior independent review), otherwise clean. OpenCode did not stage, commit, or modify this file. No reviewer-owned artifact was overwritten.
- No Batch 1 authorization issued, no P3 task promoted, no migration/Redis/Python/faster-whisper artifact introduced.

## C. F-01 — AGENTS.md

Substantively resolved: `AGENTS.md` now states Phase 2 = COMPLETE_WITH_DEFERRED_DEBT (closed), Phase 3 = Real Transcription Engine (ADR-017), planning reconciled, implementation NOT AUTHORIZED, Batch 1 NOT AUTHORIZED, no P3 task READY, and lists the full ADR-017 boundary. The old "FFmpeg/FFprobe (Phase 3)" / "faster-whisper (Phase 4)" / "Phase 2 checkpoint" stale lines are fully removed, not merely re-marked historical.

**Residual finding (F-01R, HIGH)** — the corrected "Do NOT implement" list reads:

> Queue infrastructure (Redis, workers) until Phase 3 Batch 1 authorized

This is the exact sentence flagged for scrutiny, and the concern is confirmed: read literally, "until Phase 3 Batch 1 authorized" attaches the release condition for Redis/queue implementation to Batch 1 authorization. But Redis queue orchestration is P3-006, canonically assigned to **Batch 2**, not Batch 1 (Batch 1 = P3-001, P3-002, Benchmark Gate, P3-003 — none of which include queue infrastructure). As written, an implementer could reasonably read this line as "once HPO authorizes Batch 1, Redis/queue implementation is permitted" — which is false and would allow Batch 2 scope (P3-006) to start under a Batch 1 authorization. This is precisely the scope/authorization ambiguity the review brief asked to test for, and it is present verbatim in the corrected file.

Required correction (narrow wording only, not a redesign) — replace with something equivalent to the review brief's own suggested boundary:

```text
Do not implement any Phase 3 task unless that specific batch has been
explicitly authorized. Redis queue orchestration belongs to P3-006 /
Batch 2 and is not authorized by Batch 1 authorization.
```

This is a documentation-only fix, but it sits in a live, agent-read operational file, so it is rated HIGH per Section 12's rule that a material scope/authorization ambiguity blocks VERIFIED.

## D. F-02 — Batch Exception

**`PHASE-SPECIFIC BATCH EXCEPTION NOW SUFFICIENT`**

The new "Phase 3 Batch-Execution Exception (ADR-017)" section in `.ai/guidelines/orchestration-policy.md` is thorough and closes the mechanical gap identified previously:

- Explicitly headed and cross-referenced to ADR-017; states the exception "does not apply to any other phase unless separately authorized."
- Preserves the canonical lifecycle enum unchanged (BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE); adds an exception layer rather than redefining states.
- **3.1 Authorization**: "One explicit HPO Batch authorization may authorize the set of tasks belonging to that batch. Phase 3 planning acceptance does not itself authorize any batch." — matches requirement exactly.
- **3.2 READY**: "all implementation tasks in that batch may be promoted to READY at authorization time. Dependency ordering still controls when implementation may begin." This is precisely `READY = authorized to execute when dependencies permit`, not `prerequisites already satisfied` — and the worked example explicitly shows P3-003 becoming READY at batch authorization while only *beginning* after the benchmark gate is recorded, directly resolving the P3-003-prerequisite-not-yet-satisfied concern.
- **3.3 Sequential implementation**: explicitly permits "P3-001 implementation-complete (may remain IN_PROGRESS) → P3-002 begins implementation," gated on five concrete conditions (implementation-complete, tests pass, no known blocking defect, task inside authorized batch, dependency contract permits implementation-complete predecessor) — objective enough to prevent arbitrary progression.
- **3.4 Review handoff**: "At the end of the batch, all implementation tasks in the batch move to REVIEW" — confirmed, resolves the "does each task individually reach REVIEW" question from the prior review.
- **3.5 Independent review**: "Claude records task-specific findings and one overall batch verdict... OpenCode must never self-assign VERIFIED." — matches.
- **3.6 DONE**: "VERIFIED → DONE remains subject to the existing HPO/governance closure rule." — matches.
- **3.7 Stop rule**: explicit "STOP progression... Surface the blocker to HPO. Do not continue to the next task in the batch." — matches, and is reinforced by the canonical spec's benchmark decision rule (Section F below), which invokes this exact stop rule if the benchmark rejects turbo.

No remaining material ambiguity found in this section.

## E. F-03 — Canonical Specification

`PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` exists at repository root, is committed in `5b17f8f` (confirmed in the diffstat, not merely present on disk), and is tracked (`git show 5b17f8f -- PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` returns content). It:

- Is referenced by `DECISIONS.md` (ADR-017's Reference section now lists it first).
- States `Authority: ADR-017 in DECISIONS.md` — a normal bidirectional cross-reference, not a circular-authority problem (neither document claims to supersede the other; ADR-017 remains the durable decision record, the spec is its detailed technical elaboration).
- Contains the current ADR-017 architecture (Redis, Python worker, FFmpeg, faster-whisper, multilingual, provider-neutral domain) — no trace of the old FFmpeg-only/Phase-4/Phase-5 boundary.
- Its own Completion Gate item 1 requires "Phase 3 implementation was explicitly authorized" as a precondition for Phase 3 completion — so the document's "APPROVED BY HPO — governance baseline" status line does **not** itself authorize implementation; it correctly treats implementation authorization as a still-open, separate gate.

**Residual finding (F-03R, MEDIUM)** — none of the 8 P3 task files (`tasks/P3-001` through `P3-008`) were updated to reference the new spec file; every one of them still cites only the old phantom title `` `Phase 3 — Real Transcription Engine Technical Specification` (approved) `` in its Context section, which still does not exist as a file anywhere in the repository (confirmed via repo-wide search — the string "Technical Specification" now matches only citation text in `DECISIONS.md`, `plan-phase3-media-processing.md`, the 8 task files, and this review's own prior findings, never an actual document). `DECISIONS.md` compounds this: it now lists **both** the new correct reference (`` `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` ``) **and** the old phantom one (`` `Phase 3 — Real Transcription Engine Technical Specification` ``) side by side, with no indication they are the same document (their titles differ: "Canonical Specification" vs. "Technical Specification"). This is the letter of F-03 satisfied (a real, committed, correctly-authored spec now exists) but the substance — every task file's own citation trail — still points at nothing. Required correction: update the Context section of each P3 task file to cite `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md`, and remove the phantom "Technical Specification" line from `DECISIONS.md` and `plan-phase3-media-processing.md`.

**Additional observation (F-03R2, LOW)** — the spec's own header lacks an explicit "Phase 3 implementation NOT AUTHORIZED / Batch 1 NOT AUTHORIZED / No P3 task READY" statement, unlike every other reconciled artifact (`CURRENT_STATE.md`, `plan.md`, `RTFTT-MASTER-ROADMAP.md`, corrected `AGENTS.md`). Its "Status: APPROVED BY HPO — governance baseline" line, read in isolation, could be misread as broader approval than intended. Recommend adding one explicit non-authorization line near the top, consistent with the rest of the governance suite.

## F. F-04 — Benchmark Gate

**Sufficiently implementation-ready.** The spec's Benchmark Gate section covers every required element:

- Workload: "BM, English, Chinese, Tamil, mixed-language/code-switching samples" — matches exactly.
- Reproducibility/environment: sample ID/description, duration, hardware (CPU/GPU, RAM/VRAM), model/device/compute_type, faster-whisper version, model identifier, inference configuration, FFmpeg profile, benchmark date/environment — all present.
- Results: processing duration, real-time factor, qualitative transcript observation, per-language/code-switching observations, operational/resource notes — all present.
- Privacy: "representative user media must not be committed unless explicitly approved" — present.
- Decision rule: "turbo preferred unless evidence shows materially unacceptable degradation... If not, STOP P3-003 finalization and surface evidence to HPO." — this directly answers the review brief's requirement that OpenCode cannot silently choose large-v3 contrary to the gate, and that finalization must stop and escalate to HPO if turbo is rejected. Ties correctly into the F-02 batch exception's Section 3.7 stop rule.
- No mandatory WER threshold anywhere (OD-11 in the spec explicitly: "No mandatory WER threshold").

No material gap remains here.

## G. F-05 — Decision Traceability

`DECISIONS.md`'s ADR-017 now correctly says "OD-01 through OD-12 (see canonical specification for full definitions)" — the stale "OD-01 through OD-10" phrase is gone from `DECISIONS.md`. The spec's OD-01 through OD-12 registry has no duplicate IDs and no conflicting descriptions against ADR-017's own prose or the task files' acceptance criteria.

**Residual finding (F-05R, LOW/MEDIUM)** — `plan-phase3-media-processing.md` (untouched by `5b17f8f`) still lists only "OD-01" through "OD-10: Review cadence (batch-level review)" and has no entries for OD-11 (Accuracy Policy) or OD-12 (Configuration Boundary). This is now a second inconsistency between two documents both claiming to enumerate the owner decisions. No conflicting *description* exists (OD-01 through OD-10 match between the two documents), so this is semantic-consistency-intact per the review brief's own LOW/INFO carve-out, but it should still be corrected before it is relied upon as a complete registry. Recommend updating `plan-phase3-media-processing.md`'s OD list to OD-01 through OD-12, or removing its OD list in favor of a pointer to the new canonical spec.

## H. F-06 — Decision Queue

Fully resolved. `DECISION_QUEUE.md` now contains `DECISION-P3-BATCH1-001`, Status `OPEN`, Type `Phase Authorization`, scoped explicitly to `P3-001, P3-002, Turbo vs Large-v3 Benchmark Gate, P3-003`, Resolution `PENDING HPO DECISION. NOT YET AUTHORIZED.` It explicitly states under "Does Not Block": "Batch 2/3 remain separately unauthorized," and does not reference P3-004/005/006 anywhere in its scope. No overreach found.

## I. Review Artifact Durability

**`REVIEW ARTIFACT EXISTS BUT IS UNCOMMITTED`**

1. Exists? Yes — `reviews/PHASE3-GOVERNANCE-RECONCILIATION-ADR017-review.md` is present on disk.
2. Tracked by git? No — `git status` lists it under "Untracked files."
3. Committed? No.
4. Authentic content? Yes — verified unchanged from what this reviewer originally wrote; `5b17f8f`'s diffstat does not include this path, and OpenCode's own completion report correctly disclosed it as untracked rather than silently committing or altering it. Chain of custody is intact.
5. Does governance require commitment for durability? Governance text does not contain a literal sentence mandating a git commit for review artifacts, but the repository's foundational model treats **Git history** as one of the named durable handoff mechanisms (alongside task files, `CURRENT_STATE.md`, `DECISIONS.md`), and explicitly states "important project information must not exist only in chat output." An uncommitted working-tree file does not yet participate in that shared, cross-session, cross-clone durable record — it is invisible to any other agent, clone, or CI process until committed. This reviewer does not commit changes unless the user explicitly requests it (per this session's own operating constraints), so the file remains uncommitted pending an explicit instruction. Recommend the Human Product Owner (or an explicit instruction to either agent) commit both this file and the present re-review file before treating the governance record as durable, and certainly before Batch 1 execution begins.

## J. Authorization Boundary

Confirmed unchanged after `5b17f8f`:

```text
PHASE 3 PLANNING RECONCILED
PHASE 3 IMPLEMENTATION NOT AUTHORIZED
BATCH 1 NOT AUTHORIZED
NO P3 TASK READY
```

All 8 P3 task files verified `Status: BACKLOG`. Benchmark gate remains an unstarted prerequisite (no evidence file exists anywhere in the repository).

**Batch 1 is not yet technically eligible for HPO authorization.** The batch-execution mechanics (F-02) and benchmark contract (F-04) are now implementation-ready and no longer block eligibility. What remains outstanding is narrower than the prior cycle: (a) F-01R's Redis/queue wording in `AGENTS.md` should be tightened before Batch 1 begins, to prevent a plausible misreading that Batch 1 authorization extends to P3-006/Batch 2 scope, and (b) the F-03R citation gap (task files still pointing at a phantom document) should be closed so implementers actually consult the real canonical spec. Both are narrow, low-risk documentation corrections, not planning or architecture changes. This reviewer does not authorize Batch 1; that remains the Human Product Owner's decision.

## K. Phase 2 Preservation

Confirmed unchanged (file untouched by `5b17f8f`, re-verified directly in `CURRENT_STATE.md`):

```text
Phase 2 = COMPLETE_WITH_DEFERRED_DEBT
P2-004A = BLOCKED
P2-004A1 = BLOCKED
P2-004A2 = DONE
P2-007 = DONE
Option D = IN FORCE
```

## L. Remaining Findings

**F-01R — HIGH** — `AGENTS.md`'s corrected "Do NOT implement" line ("Queue infrastructure (Redis, workers) until Phase 3 Batch 1 authorized") can reasonably be read as implying Batch 1 authorization releases Redis/queue (P3-006/Batch 2) implementation.
Artifact: `AGENTS.md`, Scope Boundaries section.
Evidence: Section C above; verbatim quote confirmed via `git diff f15292e 5b17f8f -- AGENTS.md`.
Impact: Could permit an implementer to begin Batch-2-scoped Redis/queue work under a Batch 1-only authorization.
Required correction: Reword to tie the release condition to *that task's own batch* being authorized, not to Batch 1 specifically (see suggested wording in Section C).

**F-03R — MEDIUM** — New canonical spec file is uncited by any of the 8 P3 task files; the old phantom "Technical Specification" citation was not removed and now sits alongside the new correct one in `DECISIONS.md`.
Artifact: `tasks/P3-001` through `P3-008` (Context sections), `DECISIONS.md` (ADR-017 Reference section), `plan-phase3-media-processing.md`.
Evidence: Section E above; repo-wide grep confirms the phantom title matches no file.
Impact: Implementers following task-file citations still cannot locate an authoritative spec document.
Required correction: Point task-file Context sections at `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md`; remove the phantom line from `DECISIONS.md` and `plan-phase3-media-processing.md`.

**F-03R2 — LOW** — Canonical spec lacks its own explicit non-authorization statement.
Artifact: `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` header.
Required correction: Add "Phase 3 implementation NOT AUTHORIZED. Batch 1 NOT AUTHORIZED. No P3 task READY." near the top, consistent with every other reconciled artifact.

**F-05R — LOW/MEDIUM** — `plan-phase3-media-processing.md`'s OD registry still lists only OD-01 through OD-10; not updated to OD-01 through OD-12.
Artifact: `plan-phase3-media-processing.md`.
Impact: Two documents both claiming to be an OD registry now disagree on completeness (not on substance/description — no conflicting content found).
Required correction: Update the list to OD-01 through OD-12 or replace it with a pointer to the canonical spec's registry.

**INFO** — F-02, F-04, and F-06 corrections are each independently sufficient and require no further action.

## M. Files Changed (this re-review)

- Added: `reviews/PHASE3-GOVERNANCE-RECONCILIATION-ADR017-re-review.md` (this file).
- No other repository file was modified. The prior review artifact (`reviews/PHASE3-GOVERNANCE-RECONCILIATION-ADR017-review.md`) was read but not altered.

## N. Explicit Non-Actions

Confirmed:
- No production code changed.
- No tests changed.
- No migrations changed.
- No Redis installed/configured.
- No Python worker implemented.
- No faster-whisper installed.
- No P3 task promoted (all 8 remain BACKLOG).
- Batch 1 not authorized.
- Option D (ADR-013) unchanged and confirmed still in force.
