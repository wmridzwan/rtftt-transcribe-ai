# Phase 3 Governance Reconciliation (ADR-017) — Independent Review

Date: 2026-09-17
Reviewer: Claude Code (independent reviewer role, per `.ai/guidelines/orchestration-policy.md`)
Reviewed commit: `f15292e` (reconcile Phase 3 planning under ADR-017)
Baseline: `ad9f3d0`
Scope: Planning/governance review only. No implementation code, tests, or migrations were reviewed as artifacts of change because none were changed.

## A. Independent Verdict

**CHANGES_REQUESTED**

One HIGH finding (stale, unreconciled AGENTS.md phase-boundary content) and several MEDIUM/LOW traceability gaps prevent a clean VERIFIED. None of the findings indicate unauthorized implementation, scope creep, or a broken Phase 2 baseline — the reconciliation commit is narrowly scoped and internally consistent everywhere except AGENTS.md. All findings are documentation-only corrections.

## B. Commit/Diff Verification

Confirmed independently via `git diff --stat ad9f3d0 f15292e` and `git show --stat f15292e`:

- 14 files changed, 1127 insertions(+), 127 deletions(-).
- Files: `CURRENT_STATE.md`, `DECISIONS.md`, `RTFTT-MASTER-ROADMAP.md`, `architecture.md`, `plan-phase3-media-processing.md` (new), `plan.md`, `tasks/P3-001` through `tasks/P3-008` (new).
- No `app/`, `database/`, `tests/`, `config/`, `routes/`, or `resources/` paths touched.
- No `reviews/` artifact touched (confirmed via `git diff ad9f3d0 f15292e -- reviews/` — empty).
- `git status` at review time: clean working tree, branch `setup/ai-development-os`, nothing to commit.
- No unrelated files included.

**AGENTS.md was NOT part of this commit** — this is itself a finding (see D/F/O below).

## C. ADR-017 Review

ADR-017 exists in `DECISIONS.md`, is sequentially numbered (follows ADR-016), and is marked `Status: ACCEPTED`. All ten owner decisions are present in substance:

| Decision | Present in ADR-017 prose | Present in `plan-phase3-media-processing.md` OD registry |
|---|---|---|
| Phase boundary | Yes (unlabeled) | OD-05 |
| Provider (faster-whisper canonical) | Yes (unlabeled) | — (implied by OD-02/scope) |
| Segment language (one per segment) | Yes, labeled OD-01 | OD-01 |
| Language identifiers (BCP 47, `und`) | Yes (unlabeled) | OD-04 |
| Model gate (turbo vs large-v3) | Yes, labeled OD-02 | OD-02 |
| Worker transport (private HTTP) | Yes, labeled OD-03 | OD-03 |
| Queue (Redis, no Horizon) | Yes, labeled OD-06 | OD-06 |
| Media access (shared private fs) | Yes, labeled OD-07 | OD-07 |
| Prepared audio retention (ephemeral default) | Yes, labeled OD-08 | OD-08 |
| Worker auth (private net + bearer) | Yes, labeled OD-09 | OD-09 |
| Accuracy policy (no mandatory WER) | Yes (unlabeled) | — |
| Batch review cadence | Yes (unlabeled) | OD-10 |

Finding F-05 (LOW): ADR-017's own prose inline-labels only 7 of the 10 OD numbers; OD-04, OD-05, and OD-10 are traceable only via the separate OD registry in `plan-phase3-media-processing.md`, not within the ADR text itself. No contradiction — a traceability gap only.

No missing, duplicated, or contradictory decisions found. Wording is unambiguous everywhere it is present.

## D. Phase Boundary Review

`RTFTT-MASTER-ROADMAP.md`, `plan.md`, `CURRENT_STATE.md`, `architecture.md`, and `DECISIONS.md` (ADR-017) are fully reconciled and mutually consistent: Phase 3 = complete Real Transcription Engine; the old FFmpeg-only/Phase-4/Phase-5 decomposition is explicitly marked superseded in each of these files, with supersession language present in every changed file (confirmed via full diff inspection, not just the new lines).

**Unresolved current-vs-historical ambiguity: `AGENTS.md`** (not touched by this commit). It still states, under "Scope Boundaries":

```
Do NOT implement:
- FFmpeg/FFprobe processing (Phase 3)
- faster-whisper transcription (Phase 4)
- Queue worker infrastructure
```

and "Current Phase: Phase 2 checkpoint (P2-003, P2-005 DONE; P2-004A, P2-004A1 BLOCKED)" — both stale relative to ADR-017 and the now-closed Phase 2. `AGENTS.md` is not a historical/archival record; it is OpenCode's live operational quick-reference and sits above `CURRENT_STATE.md`/`plan.md` in the source-of-truth order (CLAUDE.md item 2 vs items 4–5). This is not marked superseded and directly conflicts with the reconciled boundary. See Finding F-01.

## E. Batch Governance / State-to-Action Review

Classification: **ADR / PHASE-SPECIFIC EXCEPTION REQUIRED**

The substantive exception already exists — ADR-017 (OD-10) is an HPO-accepted decision authorizing "up to three sequential implementation tasks per authorized batch followed by one independent Claude review." That satisfies the *decision* requirement. However, `.ai/guidelines/orchestration-policy.md` (which self-declares itself "the one canonical State-to-Action Contract" that other artifacts "must cross-reference... instead of duplicating or redefining") was **not amended or cross-referenced** to record this exception. The commit message's claim that the batch model was "confirmed compatible with existing State-to-Action Contract" overstates this: the Contract's table and prose contain no batch concept at all, so compatibility was asserted, not demonstrated in the canonical file.

Answers to the seven questions:

1. **Can P3-001 remain IN_PROGRESS while P3-002 enters IN_PROGRESS?** Not addressed either way by `orchestration-policy.md`. Not explicitly prohibited (no rule limits an owner to one concurrent IN_PROGRESS task), but never previously exercised this way, and not something the Contract table anticipates.
2. **Does the policy require a task to reach REVIEW before its dependent may start?** No explicit requirement exists in `orchestration-policy.md`. Silent.
3. **Does dependency wording in the P3 task artifacts conflict with batch execution?** No. P3-002/P3-003 depend on predecessors described as "complete" (P3-003's Prerequisites: "P3-001 — complete", "P3-002 — complete"), not "DONE" or "VERIFIED" — consistent with the batch model's intent that implementation-complete-but-unreviewed output may feed the next task.
4. **Does each task individually transition to REVIEW, or is there a separate batch-level state?** Each task file's own "Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE" implies every task still passes through REVIEW individually — there is no new lifecycle state (`orchestration-policy.md`'s enum is unchanged: BACKLOG/READY/IN_PROGRESS/REVIEW/CHANGES_REQUESTED/VERIFIED/DONE/BLOCKED). The workable reading is that all three Batch 1 tasks reach REVIEW together and Claude reviews them in one pass/artifact — but this is inferred, not stated anywhere.
5. **Can Claude mark individual tasks VERIFIED while returning a batch verdict?** Not prohibited; not explicitly authorized either. Reasonable but undocumented.
6. **Does HPO need to authorize each task's READY transition individually, or can one Batch 1 authorization promote all three?** Under the existing Contract, `READY`'s required action is simply "Human Product Owner: Assign to OpenCode." Nothing bars one HPO action naming three tasks at once — this part is compatible with current policy as written.
7. **Is a policy amendment, ADR exception, or explicit batch authorization rule required?** Yes — ADR-017/OD-10 supplies the exception in substance, but it has not been reflected back into `orchestration-policy.md`, and the mechanical question in (1)/(4) above (simultaneous IN_PROGRESS tasks; how/when the batch collectively reaches REVIEW) remains unspecified. This should be closed with either a short cross-reference amendment to `orchestration-policy.md` or an explicit operational addendum in `plan-phase3-media-processing.md` before Batch 1 begins. See Finding F-02.

This is a genuine, currently-unresolved mechanical gap, but it is not a contradiction that invalidates the reconciliation — it is an incompleteness that should be closed before Batch 1 execution starts (not necessarily before Batch 1 *authorization*, since HPO authorization is exactly the point at which this could be resolved explicitly).

## F. P3 Task Matrix Review

All eight tasks reviewed (`tasks/P3-001` through `tasks/P3-008`). Each has: Status `BACKLOG`; Ownership `UNASSIGNED`/`UNASSIGNED`; Objective; Scope; Out of Scope (present on 001–003, folded into Dependencies-adjacent scope text on 004–008); Dependencies; Acceptance Criteria; Required flow with explicit "Implementation owner must not mark their own work VERIFIED." Review Status is `PENDING` on all eight, Review File `None yet` on all eight. No task is READY. No task shows any implementation authorization state beyond BACKLOG.

## G. Dependency Review

Confirmed chains:

```
P3-001 → P3-002 → Benchmark Gate → P3-003        (Batch 1)
P3-004 → P3-005 → P3-006                          (Batch 2, depends on P3-003)
P3-007 → P3-008                                   (Batch 3, depends on P3-001–P3-006 "all prior batches")
```

No task's Dependencies field names a predecessor as required to be `DONE`. Batch 2's P3-004 depends on "P3-003 (real provider producing normalized results)" — no state qualifier; Batch 3's P3-007/P3-008 depend on "P3-001 through P3-006 (all prior batches)" — again no state qualifier. This is consistent with the batch model (Batch 2 requires Batch 1 to have passed its batch review before starting, per the stated Batch model and OD-10, but the task files themselves don't encode "VERIFIED" as a literal gate, which is correct — it avoids hard-coding a stricter contract than ADR-017 intends). No task incorrectly hard-codes a `DONE` dependency.

## H. Architecture Review

`architecture.md`'s Future Architecture diagram and Key Principles are fully rewritten and match ADR-017 exactly (provider-neutral domain → Redis queue → Laravel queue worker → TranscriptionProvider/internal HTTP → Python service → shared private filesystem → FFmpeg → faster-whisper → normalized result → persistence). The old "Queue (Redis/Horizon) → Independent Transcription Worker → faster-whisper → Database/Result Callback" diagram and the old "Phase 3 = FFmpeg, Phase 4 = worker, Phase 5 = integration" paragraph are fully removed and replaced with a "Phase Boundary (ADR-017)" section. No stale statement survives in this file.

## I. Multilingual / Code-Switching Review

Verified across P3-001 (AC 5–8: detected_language = dominant; per-segment language; `und` valid; mixed-language segments supported), P3-003 (AC 12–15: dominant language returned; segment languages returned where defensible; `und` where unsupported; **"Segment languages are not fabricated from transcript-level language"** — explicitly present, satisfying the "must not simply copy transcript dominant language" requirement), and P3-008 (mixed-language verification block: dominant language exists, different segment languages coexist, BM/English/Chinese/Tamil supported, `und` accepted, "Whole transcript not forced to one segment language"). No task assumes one-recording-one-language. No task assumes faster-whisper natively exposes `segment.language` — P3-003's Scope explicitly separates "Segment-language normalization" as Laravel/worker-owned derivation, not a pass-through of a library field.

## J. Security / Worker / Filesystem Review

- **Filesystem**: P3-003 Scope explicitly requires "reject absolute paths, reject traversal"; AC 5 "Media reference cannot escape configured storage root"; AC 6 "Original media remains unchanged." Architecture.md: "Laravel must not load the full 500 MiB media object into PHP memory." P3-002 AC 4: "Media passed by storage-backed reference, not binary string." Consistent across ADR/architecture/P3-002/P3-003.
- **Worker security**: Private/internal network + bearer token from env (ADR-017 OD-09, P3-002 AC 7, P3-003 AC 3–4). Timeout present (P3-003 AC 17). Invalid/malformed response rejection present (P3-002 AC 8, P3-003 AC 19). Secrets-not-logged requirement present in P3-008's security verification list. No task explicitly states that auth/configuration failures must be treated as non-retryable — this is deferred to P3-007's failure taxonomy (WORKER_AUTH_FAILED, CONFIGURATION_ERROR are listed as distinct categories with a "retryable?" property to be defined at implementation), which is appropriate for this planning granularity, not a defect.
- No public-worker-by-default statement is contradicted anywhere.

## K. Persistence / Queue / Idempotency Review

P3-004 → P3-005 ordering matches the required `normalized result → transcript persistence → segment persistence → atomic completion` sequence; P3-005 AC 9–10 explicitly forbid partial-segment completion and require atomic transcript+segment commit. Requested vs. detected language kept distinct (P3-004 AC 3). Unicode/mixed-script preservation explicit (P3-004 AC 6, P3-005 AC 11). Segment uniqueness via `(transcription_id, segment_index)` unique constraint (P3-005 Scope/AC 7). Retry idempotency addressed (P3-004 AC 9, P3-005 AC 8). Only "minimum additive migration if strictly required" is authorized (P3-004/P3-005 Out of Scope) — no migration exists yet (confirmed via diff). Redis: small payload (`transcription_id` only, P3-006 Scope/AC 2), job reloads authoritative DB state (AC 3), duplicate-delivery and completed-state protection explicit (AC 7–9), no Horizon (AC 12), Redis outage surfaced safely (AC 11) — no silent reversion to sync/DB-queue/Horizon/full-media serialization anywhere.

## L. Benchmark Gate Review

Correctly represented as a mandatory prerequisite/gate embedded in the task matrix (a "Gate" row between P3-002 and P3-003), not as an independently implemented/reviewed product task — consistent with the instruction to avoid an unnecessary standalone task. No mandatory WER threshold is introduced anywhere (P3-008 Out of Scope explicitly excludes "Minimum WER threshold"; ADR-017 "Accuracy policy" confirms no mandatory WER). P3-003 correctly cannot finalize model selection without the gate (Prerequisites: "Turbo vs Large-v3 Benchmark Gate — evidence recorded"; AC 2: "Selected model matches benchmark outcome").

**Gap (Finding F-04, MEDIUM)**: no artifact anywhere specifies the *required evidence content* for the benchmark gate (representative BM/English/Chinese/Tamil/mixed-language workload, hardware, duration, compute configuration, processing time, qualitative language/code-switching behavior, resource observations) that this review's brief describes as expected. Only the gate's existence and its "before P3-003 finalization" placement are recorded; the evidentiary bar is undefined, which risks a thin or non-representative benchmark satisfying "evidence recorded" literally.

## M. P3-008 Completion Gate Review

P3-008's full path diagram exactly matches the required real-media → Phase 2 MediaFile → Redis → Laravel worker → Python worker → shared filesystem → FFmpeg → faster-whisper → normalized result → transcript/segment persistence → completed chain, and explicitly states "Mock-only tests insufficient; real path evidence required" (AC 16). It separately covers code-switching, `und`, duplicate delivery, retry, retry exhaustion, persistence atomicity, cross-user ownership isolation, worker security (auth, no public exposure, path rejection, secrets-not-logged), temp cleanup (via "Cleanup obeys configured retention"), and Phase 1/2 regression (explicit regression-verification block naming P2-004A2 CAS behavior and "no new unexplained skips"). Out of Scope explicitly excludes "Full transcript UI redesign" — confirms no UI redesign is accidentally required.

## N. Phase 2 Preservation

Verified via full diff of `CURRENT_STATE.md`/`plan.md`: Phase 2 = `COMPLETE_WITH_DEFERRED_DEBT` unchanged; P2-004A = BLOCKED unchanged; P2-004A1 = BLOCKED unchanged; P2-004A2 = DONE unchanged; P2-007 = DONE unchanged; Option D (ADR-013) explicitly restated as remaining in force in every touched file. ADR-017 itself states: "Phase 3 consumes durable private MediaFile objects. It does not depend on Phase 2 staging cleanup. Option D (ADR-013) remains in force." No P2 status line was altered by this commit — every P2-related diff hunk only adds Phase-3-related sentences around unchanged P2 facts.

## O. Stale Reference Findings

| Statement | Location found | Classification |
|---|---|---|
| "Phase 3 = FFmpeg/FFprobe media processing," "faster-whisper transcription (Phase 4)," "Current Phase: Phase 2 checkpoint" | `AGENTS.md` (untouched) | **Blocking contradiction** (current, not marked historical — Finding F-01) |
| Old Phase 3/4/5 decomposition text | `RTFTT-MASTER-ROADMAP.md`, `plan.md`, `architecture.md`, `DECISIONS.md` (ADR-011, historical ADR) | Historical/superseded, explicitly annotated — non-blocking |
| "Phase 3 — Real Transcription Engine Technical Specification (approved)" citation | ADR-017, all 8 P3 task files | Dangling reference, no corresponding file in repo — **stale but non-blocking, traceability defect** (Finding F-03) |
| Compressed-input-bytes×constant WAV limit / auto-retry-smaller-chunk / universal `setrlimit` | searched repo-wide | **Not found anywhere** — confirms these speculative statements were not carried forward; no finding |
| P3-004 = configuration-only, P3-006 = standalone observability, old P3-007 numbering | searched task files | Not found — current P3-004/006/007 content matches the approved scope; no finding |
| Redis synchronous/DB-queue/Horizon reversion | searched task files/architecture | Not found; no finding |
| Unsupported parallel lifecycle vocabulary (`pending`, `processing` as status values) | searched task files | Not found as status values; "processing" appears only inside existing enum member names (`ProcessingStatus`, `ProcessingJob`) and failure-code names (`PROCESSING_FAILED`), which are pre-existing/legitimate, not new parallel vocabulary; no finding |

## P. Findings by Severity

**F-01 — HIGH** — `AGENTS.md` not reconciled; still states superseded Phase 3/4/5 boundary and stale "Phase 2 checkpoint" current-phase line.
Affected artifact: `AGENTS.md` (Scope Boundaries section, lines ~64–73).
Evidence: commit `f15292e`'s file list does not include `AGENTS.md`; current file content still reads "Do NOT implement: FFmpeg/FFprobe processing (Phase 3)... faster-whisper transcription (Phase 4)... Queue worker infrastructure" and "Current Phase: Phase 2 checkpoint."
Impact: `AGENTS.md` is OpenCode's live operational quick-reference and ranks above `CURRENT_STATE.md`/`plan.md` in the repository's own source-of-truth order; an implementation agent reading it could reasonably believe the old phase boundary and Phase 2 checkpoint status are still current, directly contradicting ADR-017/CURRENT_STATE.md/plan.md.
Required correction: Update `AGENTS.md`'s Scope Boundaries and Current-Phase lines to reflect ADR-017 (Phase 3 = Real Transcription Engine, planning reconciled, not authorized), or explicitly mark the old bullets as historical/superseded with a pointer to ADR-017.

**F-02 — MEDIUM** — Batch review cadence (ADR-017/OD-10) not cross-referenced into the canonical State-to-Action Contract (`orchestration-policy.md`); mechanical questions (concurrent IN_PROGRESS tasks across a dependency chain; how/when a batch collectively reaches REVIEW) remain unspecified.
Affected artifact: `.ai/guidelines/orchestration-policy.md` (no batch concept); `DECISIONS.md` ADR-017 (OD-10).
Evidence: Section E above.
Impact: Could produce inconsistent execution behavior once Batch 1 is authorized (e.g., disagreement over whether P3-002 may start before P3-001 is reviewed).
Required correction: Add a short cross-reference/amendment in `orchestration-policy.md` acknowledging the ADR-017 batch exception, or an explicit operational addendum in `plan-phase3-media-processing.md` describing exact per-task Status mechanics during a batch, before Batch 1 execution begins.

**F-03 — MEDIUM** — Dangling citation to an "approved" `Phase 3 — Real Transcription Engine Technical Specification` that does not exist in the repository.
Affected artifacts: `DECISIONS.md` (ADR-017 Reference section), all of `tasks/P3-001` through `P3-008`.
Evidence: repo-wide glob/grep found no such file; only citing occurrences.
Impact: Violates the repository's own principle (ADR-011) that external documents are not authoritative until reconciled into repository-native artifacts; an unverifiable "approved" reference undermines the repo-as-source-of-truth model.
Required correction: Either commit the actual specification document, or replace the citation with references to ADR-017/`plan-phase3-media-processing.md` only.

**F-04 — MEDIUM** — Benchmark gate's required evidence content is undefined.
Affected artifacts: `tasks/P3-003-...md` (Prerequisites), `plan.md`, `plan-phase3-media-processing.md`.
Evidence: Section L above; grep for "benchmark" across the repo surfaces no evidence-content specification.
Impact: "Evidence recorded" could be satisfied nominally without covering the representative multilingual/code-switching workload this review's brief expects, weakening the gate's purpose.
Required correction: Add a short evidence-content checklist (workload composition, hardware, timings, qualitative observations) to the Gate row or to P3-003 before Batch 1 begins.

**F-05 — LOW** — Incomplete inline OD-number labeling within ADR-017's own prose (OD-04, OD-05, OD-10 untagged in `DECISIONS.md`, only resolvable via `plan-phase3-media-processing.md`'s registry).
No functional impact; traceability polish only.

**F-06 — LOW** — `DECISION_QUEUE.md` has no OPEN entry for the pending "separate HPO decision required for Batch 1," despite this being repeatedly referenced as an outstanding decision across `CURRENT_STATE.md`/`plan.md`/`RTFTT-MASTER-ROADMAP.md`.
Required correction (optional, non-blocking): log a `DECISION-P3-BATCH1-AUTHORIZATION` OPEN entry for completeness.

## Q. Authorization Boundary

Batch 1 is **not yet** technically eligible for HPO authorization in the fullest sense: the planning content itself (task contracts, dependency chain, multilingual/security/persistence/queue/benchmark design) is sound and internally consistent, but F-01 (stale AGENTS.md) must be corrected first since it is a direct, current-tense contradiction in a load-bearing operational file, and F-02/F-03/F-04 should be closed to avoid ambiguity once execution starts. None of these require redesign — all are narrow documentation corrections. This reviewer does not authorize Batch 1; that remains the Human Product Owner's decision once these findings are addressed or explicitly accepted with known limitations.

## R. Files Changed (this review)

- Added: `reviews/PHASE3-GOVERNANCE-RECONCILIATION-ADR017-review.md` (this file only).
- No other repository file was modified by this review.

## S. Explicit Non-Actions

Confirmed:
- No production code modified.
- No tests modified.
- No migration created.
- No Redis installed.
- No Python worker implemented.
- No P3 task promoted (all remain BACKLOG).
- No Batch 1 authorization issued.
- Option D (ADR-013) unchanged and confirmed still in force.
