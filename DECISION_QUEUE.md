# Decision Queue

This queue records unresolved product, UX, architecture, security, destructive-operation, production, and phase decisions for the Human Product Owner or another explicitly authorized decision-maker.

An unresolved decision affects only the tasks listed in **Blocks**. It must never pause unrelated runnable work. READY tasks with satisfied dependencies may proceed while a decision is pending.

## Queue Statuses

- OPEN — awaiting an authorized decision.
- DECIDED — recorded here and, where durable, in DECISIONS.md.
- WITHDRAWN — no longer applicable.

## Decision Entry Template

Decision ID: DECISION-XXX

Status: DECIDED

Type: Product | UX | Architecture | Security | Destructive Operation | Production Deployment | Phase Completion | Phase Authorization

Originating Task: TASK-XXX

Raised By: Agent or Human Product Owner

Priority: LOW | MEDIUM | HIGH | BLOCKER

Question:

State the decision required in one clear question.

Options:

1. Option A
2. Option B

Recommendation:

Provide an engineering recommendation only when the decision-maker has enough context to choose deliberately.

Impact:

Describe the affected behavior, risks, and consequences.

Blocks:

- TASK-XXX

Does Not Block:

- TASK-YYY

Resolution:

To be completed by the authorized decision-maker. Link the durable ADR or task update when resolved.

## Open Decisions

### DECISION-P3-BENCHMARK-GATE-001 — Turbo vs Large-v3 benchmark gate was not recorded before P3-003 proceeded

Decision ID: DECISION-P3-BENCHMARK-GATE-001

Status: DECIDED — large-v3 selected as initial canonical Phase 3 model

Type: Phase Authorization / Architecture

Originating Task: P3-003 (raised in independent batch review,
`reviews/PHASE3-BATCH1-independent-review.md`, finding B1)

Raised By: Claude Code (independent review)

Priority: BLOCKER

Question:

`.ai/guidelines/orchestration-policy.md`'s Phase 3 Batch-Execution Exception
requires the Turbo vs Large-v3 benchmark gate to be recorded before P3-003
begins. `BENCHMARK-GATE-EVIDENCE.md` is explicitly `Status: DEFERRED —
Python not installed on current system`, with every evidence field left
`TBD`; no benchmark was ever run. P3-003 was implemented anyway, hardcoding
`turbo` as the default model. How should this be resolved?

Options:

1. Set up the Python/faster-whisper environment, run
   `scripts/benchmark/benchmark_gate.py` against representative media, and
   record real evidence in `BENCHMARK-GATE-EVIDENCE.md` before P3-003 rework
   resumes.
2. HPO explicitly waives/defers the benchmark gate and accepts `turbo` as
   the interim model default, recording that decision durably (this does
   not retroactively make the earlier silent bypass acceptable practice for
   future batches).
3. Select Large-v3 as the default without benchmarking, if HPO has other
   grounds for that choice.

Recommendation:

Option 1 if the environment can reasonably be set up; the gate exists
specifically because the spec anticipates a real accuracy/latency tradeoff
between the two models for RTFTT's multilingual (ms/en/zh/ta + code-switching)
workload, and `turbo`'s suitability for that workload is exactly what is
unproven. If environment setup is materially blocked, Option 2 is acceptable
but should be an explicit, recorded HPO decision rather than an implicit
default.

Impact:

P3-003 cannot be marked VERIFIED while this remains unresolved (see
`reviews/PHASE3-BATCH1-independent-review.md`, finding B1). This does not by
itself require re-implementing P3-003's code — only the model-selection
question needs resolving; the other required changes in the review
(H1/H2/H3/M1-M3) can be fixed independently of this decision.

Blocks:

- P3-003 VERIFIED/DONE.
- Phase 3 Batch 1 overall VERIFIED/DONE.

Does Not Block:

- P3-001/P3-002 rework on their own findings (H1 config wiring, M1-M3),
  which do not depend on the model-selection outcome.
- Batch 2/3, which remain separately unauthorized.

Resolution:

HPO chose Option 1 on 2026-09-17: Run the real Turbo vs Large-v3 benchmark.
Benchmark gate is NOT waived.

Update (2026-09-17, HPO corpus decision): Representative media resolved
using the public Google FLEURS corpus. Required configurations:
ms_my (Bahasa Melayu), en_us (English), cmn_hans_cn (Mandarin Chinese),
ta_in (Tamil). Synthetic concatenated multilingual samples approved as
functional mixed-language transition evidence for this initial model-selection
gate, explicitly labeled synthetic. Natural real-world code-switching remains
a later integration-quality verification requirement. FLEURS is used only as
benchmark input; downloaded audio files must not be committed to the repository.

Update (2026-09-17, Python retry): Python 3.13.14 installed, faster-whisper
1.2.1 working, 25 Python tests pass. Python-installation blocker resolved.
Benchmark media now sourced from FLEURS corpus.

**Initial gate (2026-09-17, cycle 3):** Real benchmark executed on 16 samples
(12 FLEURS + 4 synthetic mixed). Turbo mean RTF: 4.17. Large-v3 mean RTF: 6.08.
Turbo is 1.46x faster. Initial gate decision: turbo as default model.

**REOPENED (2026-09-17, HPO escalation):** Cycle-3 independent review exposed
undisclosed quality evidence: turbo cross-script corruption on Tamil sample
ta_in_1, limited Tamil sample size (only 3 real samples), large-v3 synthetic
hallucination/repetition. HPO directed expanded Tamil benchmark (≥15 samples),
expanded mixed-language benchmark (8+ additional transition samples), and
script-corruption analysis. Model selection decision remains OPEN pending
expanded evidence. Earlier turbo selection history preserved but not final.
See `reviews/PHASE3-BATCH1-cycle3-independent-review.md`.

**Expanded evidence (2026-09-18):** 36-sample corpus (15 real Tamil, 9
non-Tamil FLEURS, 12 synthetic mixed) run through both models. Performance:
turbo mean RTF 4.6269, large-v3 mean RTF 7.7306 (turbo 1.67x faster).

**Post-escalation review (2026-09-18):** Independent review found BLOCKER-1:
the script-corruption detector used to produce the initial "15/15 Tamil
clean" claim was defective — it checked only CJK presence and Tamil ratio and
auto-passed empty transcripts. Corrected re-analysis of the preserved raw
transcripts found turbo produced multi-script hallucinated corruption on
3/15 real Tamil samples (ta_in_1, ta_in_11, ta_in_13; Hebrew/Cyrillic/Korean/
Arabic), and large-v3 produced one wrong-script Tamil output (ta_in_15,
Gurmukhi) plus one empty mixed output (MIX-09). See
`reviews/PHASE3-BATCH1-post-escalation-independent-review.md`.

**DECIDED (2026-09-18, HPO final model decision):** HPO selected **large-v3**
as the initial canonical Phase 3 transcription model. Turbo was faster but
showed repeated material cross-script corruption on real Tamil benchmark
samples (~20%, 3/15). Turbo remains available only as a non-default
optional/experimental fast model profile (via `RTFTT_WHISPER_MODEL`), with no
new UX/model-selection scope. Full decision history preserved above; earlier
provisional turbo selection is recorded but superseded. See
`BENCHMARK-GATE-EVIDENCE.md` for corrected evidence.

### DECISION-P3-ESCALATION-001 — Three-cycle escalation resolution

Decision ID: DECISION-P3-ESCALATION-001

Status: DECIDED

Type: Escalation Resolution / HPO Exception

Originating Task: P3-001, P3-002, P3-003 (three consecutive CHANGES_REQUESTED
cycles triggered escalation per `.ai/guidelines/orchestration-policy.md`)

Raised By: Human Product Owner (escalation resolution)

Priority: BLOCKER

Question:

Three consecutive CHANGES_REQUESTED cycles (Cycle 1, Cycle 2, Cycle 3)
triggered the canonical escalation rule. Per policy, P3-001/P3-002/P3-003
became BLOCKED. How should the escalation be resolved?

Canonical options (exact, from `.ai/guidelines/orchestration-policy.md` lines 75-79):

1. Reassign to a different approach
2. Defer the task
3. Accept with known limitations
4. Close as unneeded

Resolution:

None of the four canonical options explicitly supports "keep BLOCKED while
permitting narrowly scoped HPO-directed remediation and subsequent independent
re-review." The HPO action that occurred does not map cleanly to any single
canonical option.

**HPO Exception (2026-09-18):**

Batch 1 remained BLOCKED. HPO explicitly authorized only H6 benchmark
remediation (expanded Tamil corpus, expanded mixed-language corpus,
script-corruption analysis, model-gate reassessment). This did not reopen
autonomous implementation. This did not authorize a normal Cycle 4/5.
H5 was already confirmed fixed; regression checks were authorized but no
new H5 architecture work.

The earlier "Option 2 — Defer the task" recording was incorrect and is
reconciled here. "Defer the task" does not accurately describe the HPO
action, which included explicit authorization of specific remediation work
while maintaining BLOCKED status. This reconciliation preserves the
history of the incorrect record rather than erasing it.

Post-remediation: fresh independent post-escalation review requested.
Batch 1 remains BLOCKED pending that review.

**Escalation outcome for the unresolved model-quality issue (2026-09-18):**
HPO chooses the canonical escalation outcome equivalent to **Option 3 —
Accept with known limitations**, for Batch 1 using large-v3.

Known limitations accepted:

- Slower CPU inference (large-v3 mean RTF ~7.73 vs turbo ~4.63).
- Occasional wrong-script/hallucination behavior still possible (large-v3
  rendered one real Tamil sample in Gurmukhi and produced one empty mixed
  output).
- Synthetic mixed-language tests do not prove natural conversational
  code-switch accuracy; P3-008 must later perform real integration-quality
  verification.
- Phase 3 does not establish a WER SLA.
- Large-v3 is not represented as perfect.

Impact:

- P3-001/P3-002/P3-003 remain BLOCKED during and after remediation
- H6 expanded benchmark is the only authorized implementation work
- Batch 2/3 remain NOT AUTHORIZED
- No autonomous Cycle 5; next review is "Final HPO-Decision Verification"

Blocks:

- P3-001, P3-002, P3-003 (BLOCKED pending fresh independent review)

Does Not Block:

- P3-004 through P3-008 (remain BACKLOG / NOT AUTHORIZED)
- H6 expanded benchmark remediation (completed under explicit HPO exception)

### DECISION-P3-BATCH1-001 — Authorize Phase 3 Batch 1

Decision ID: DECISION-P3-BATCH1-001

Status: DECIDED

Type: Phase Authorization

Originating Scope: Phase 3 — Real Transcription Engine (ADR-017)

Raised By: Phase 3 planning reconciliation

Priority: HIGH

Question:

Should Phase 3 Batch 1 be authorized for implementation?

Options:

1. Authorize Batch 1: P3-001 (Transcription Domain Contract),
   P3-002 (Provider + Worker Transport Contract),
   Turbo vs Large-v3 Benchmark Gate,
   P3-003 (Python / FFmpeg / faster-whisper Provider).
   Promote P3-001/P3-002/P3-003 to READY.
2. Authorize a subset of Batch 1 (e.g., P3-001 only).
3. Do not authorize Batch 1 yet; remain in planning state.

Recommendation:

Option 1. Phase 3 planning is reconciled under ADR-017. All
prerequisites are satisfied. Batch 1 tasks are defined and ready
for promotion.

Impact:

Authorizing Batch 1 allows Phase 3 implementation to begin.
P3-001/P3-002/P3-003 become READY. OpenCode executes sequentially.
Claude performs batch review at batch handoff. No task becomes
independently VERIFIED merely because OpenCode completed implementation.

Blocks:

- Phase 3 implementation cannot begin until Batch 1 is authorized.

Does Not Block:

- Batch 2/3 remain separately unauthorized.
- Phase 2 remains COMPLETE_WITH_DEFERRED_DEBT.
- Option D remains in force.

Resolution:

HPO-AUTHORIZED on 2026-09-17. Batch 1 authorized for execution.
P3-001, P3-002, P3-003 promoted to READY. Batch 2/3 remain unauthorized.

## DECIDED — DECISION-P2-CONCURRENCY-002

### DECISION-P2-CONCURRENCY-002 — Adopt the Option B claim CAS protocol for P2-004A/P2-004A1

Decision ID: DECISION-P2-CONCURRENCY-002

Status: DECIDED

Type: Architecture

Originating Tasks: P2-004A, P2-004A1

Raised By: Claude Code, at the Human Product Owner's request for a concrete
solution to the blocked concurrency contract

Priority: MEDIUM (P2-004A/P2-004A1 remain BLOCKED and Phase 2 is already
ACCEPTED under ADR-014; this does not block other runnable work)

Question:

Should the repository adopt the concrete SQLite-safe compare-and-set claim
protocol in `reviews/P2-004A-P2-004A1-option-b-protocol-proposal.md` as the
selected Option B mechanism, and authorize a new, narrowly-scoped
implementation task for it?

Options:

1. Approve the proposed CAS protocol (add `held_by`/`cleanup_claimed_at` to
   `staging_claims`; replace `lockForUpdate()` in `CleanupStaging` and the
   claim upsert in `MediaIngestionService` with single guarded
   `UPDATE`/`INSERT ... OR IGNORE` statements; move file deletion outside the
   transaction) and authorize a new implementation task scoped to exactly
   that.
2. Request changes to the proposed protocol before approving.
3. Keep Option D (defer) in force indefinitely; do not schedule this work.
4. Select a different resolution class from the original decision package
   (A or C) instead of B.

Recommendation:

Option 1. The proposal keeps the existing engine (SQLite) and schema shape,
requires only an additive migration, and replaces the exact mechanism the
independent review identified as unproven (`lockForUpdate()`) with a
single-statement CAS pattern that is atomic on SQLite without relying on row
locks. It also directly specifies the genuine-independent-process test
approach the verification gate requires, which was the missing piece in the
prior three CHANGES_REQUESTED cycles.

Impact:

Approving this unblocks a path to close P2-004A/P2-004A1, but does not by
itself mark them VERIFIED — the future implementation task must still pass
the verification gate in
`reviews/P2-004A-P2-004A1-sqlite-concurrency-decision-package.md` and
independent review. This does not reopen or change the already-DONE
P2-002B/P2-003 contracts; the proposal preserves their behavior.

Blocks:

- Authorization of a new P2-004A/P2-004A1 implementation task (nothing may
  be implemented against this design until it is approved).

Does Not Block:

- P2-007 or any other Phase 2 runnable work.
- Phase 3 authorization, which is a separate, unrelated gate.

Resolution:

The Human Product Owner approved Option 1 on 2026-09-15. A new implementation
task, P2-004A2, is authorized and READY:
`tasks/P2-004A2-staging-claim-cas-protocol.md`. Durable record: ADR-016 in
`DECISIONS.md`. Option D (ADR-013) remains in force until P2-004A2 is
independently VERIFIED and closed as DONE.

## DECIDED — DECISION-P2-CONCURRENCY-001

Decision ID: DECISION-P2-CONCURRENCY-001

Status: DECIDED
Type: Architecture
Originating Tasks: P2-004A, P2-004A1
Raised By: Codex orchestration after independent remediation-cycle-2 review
Priority: HIGH

Question:

Which approved concurrency contract should govern cleanup versus ingestion for
the repository's actual SQLite deployment: an explicitly SQLite-designed
mechanism, a redesigned SQLite claim protocol, a production engine change, or
continued deferral until cleanup is operationally required?

Options:

1. Retain SQLite and explicitly design around SQLite-supported transaction and
   locking semantics.
2. Retain SQLite but redesign the cleanup/claim protocol so correctness does
   not depend on unsupported row-level `FOR UPDATE` locking.
3. Change the production database/concurrency contract to an engine providing
   the locking semantics assumed by the current design.
4. Defer cleanup/claim execution until an operational need exists.

Recommendation:

Approve deferral for the current gate; if cleanup becomes necessary, prefer a
SQLite-specific claim protocol with genuine independent-connection/process
verification. This is an engineering recommendation only.

Impact:

P2-004A and P2-004A1 remain BLOCKED. No autonomous fourth repair cycle may
start. The exact invariant, contention outcome, transaction semantics, and
verification gate are documented in
`reviews/P2-004A-P2-004A1-sqlite-concurrency-decision-package.md`.

Blocks:

- P2-004A.
- P2-004A1.
- P2-007 and Phase 3 remain separately unauthorized while the Phase 2 gate is
  unresolved.

Does Not Block:

- P2-003 and P2-005 closure as DONE.
- Historical completed tasks and their preserved review evidence.

Resolution:

The Human Product Owner approved Option D on 2026-09-13. Automated
abandoned-staging cleanup is deferred out of the current Phase 2 completion
scope. P2-004A and P2-004A1 remain BLOCKED because the canonical lifecycle has
no DEFERRED state; they are not VERIFIED or DONE. The current SQLite
`lockForUpdate()` implementation is not accepted as a row-lock guarantee.
Existing P2-002B/P2-003 synchronous compensation, retry, ownership, and
same-attempt idempotency contracts remain authoritative. Option B is the
preferred future resolution class, but it is not authorized now. Durable
record: ADR-013 in `DECISIONS.md`.

## DECIDED — DECISION-P2-PHASE2-ACCEPTANCE-001

Decision ID: DECISION-P2-PHASE2-ACCEPTANCE-001
Status: DECIDED
Type: Phase Completion
Originating Scope: Current bounded Phase 2 scope
Raised By: Human Product Owner
Priority: HIGH

Resolution:

The Human Product Owner accepted the current bounded Phase 2 scope on
2026-09-13. Phase 2 is recorded as ACCEPTED under ADR-014. P2-003 and P2-005
remain DONE. P2-004A and P2-004A1 remain BLOCKED and deferred from the current
gate; they are not VERIFIED or DONE. Automated abandoned-staging cleanup
remains unauthorized.

This acceptance does not create or promote P2-007, make it eligible, or
authorize it. Phase 3 remains not eligible and not authorized. Durable record:
ADR-014 in `DECISIONS.md`.

## DECIDED — DECISION-P2-REMEDIATION-001

The Human Product Owner resolved the authorization question on 2026-09-13 by
explicitly authorizing remediation of P2-004A, P2-004A1, P2-005, and the affected
P2-003 service surface. The earlier missing authorization record remains a
governance/audit-trail failure and the independent finding is preserved. The
minimum FFprobe/FFmpeg dependency is approved for P2-005 in Phase 2 only; no
transcription, worker, queue, P2-007, or Phase 3 work is authorized.

Durable resolution: ADR-012 in `DECISIONS.md`. Affected tasks remain REVIEW /
AWAITING INDEPENDENT RE-REVIEW and cannot be marked VERIFIED or DONE by the
implementation owner.

## Decision History

### DECISION-P1-001 — Prototype creation title contract

Decision ID: DECISION-P1-001
Status: DECIDED
Type: Product
Originating Task: TASK-P1-CREATE-001
Raised By: Codex orchestration
Priority: MEDIUM

Question:

For Phase 1 demo transcription creation, should Title be required, or should a blank Title create a transcription with an explicitly chosen demo default title?

Options:

1. Require Title. Preserve current server validation; remove the optional label and filename-fallback promise from the form. No actual filename is available in Phase 1.
2. Allow blank Title with a Product Owner-approved demo default. Define that exact default and update server validation, form copy and tests. This is still demo-only and does not authorize file upload.

Recommendation:

Option 1, because it preserves the existing validated contract and avoids inventing a filename fallback when no file is uploaded. This is a recommendation, not a recorded decision.

Impact:

The current UI and server disagree. A user following the optional-title instruction receives validation errors instead of the promised fallback. Choosing between required input and an automatic title changes product behavior, so the agent does not select one silently.

Blocks:

- TASK-P1-CREATE-001.
- Full Phase 1 prototype acceptance readiness until the selected contract is implemented and reviewed.

Does Not Block:

- TASK-004C.
- TASK-P1-STATIC-001, TASK-P1-STATIC-002, TASK-P1-STATIC-003, TASK-P1-STATIC-004.
- Other unrelated authorized runnable Phase 1 work.

Resolution:

Human Product Owner decided on 2026-09-11: Title remains REQUIRED. UI copy was aligned to existing server validation; no filename fallback or upload behavior was added. Durable record: ADR-006 in DECISIONS.md. Phase 2 remains unauthorized.
