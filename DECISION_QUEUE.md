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

### DECISION-PHASE4-AUTHORIZATION-001 — Authorize Phase 4 boundary and task-contract authoring

Decision ID: DECISION-PHASE4-AUTHORIZATION-001

Status: DECIDED — HPO 2026-09-19

Type: Phase Authorization

Originating Scope: Phase 4 — Transcript Experience baseline (ADR-019)

Raised By: Human Product Owner (Phase 4 planning session, 2026-09-19)

Priority: HIGH

Question:

Should the Phase 4 boundary be ratified and Phase 4 be authorized for
task-contract authoring?

Options:

1. Ratify ADR-019 and authorize Phase 4 for task-contract authoring only
   (recommended).
2. Authorize Phase 4 for full implementation immediately.
3. Do not authorize Phase 4 yet.

Resolution:

HPO-AUTHORIZED on 2026-09-19. D4-01 through D4-07 resolved; ADR-019
PROPOSED → ACCEPTED. Phase 4 = AUTHORIZED FOR TASK-CONTRACT AUTHORING.

This authorization permits: authoring and refining implementation-ready P4 task
contracts; resolving detailed acceptance criteria; dependency modeling; test
plans; and preparation for a later task/batch implementation authorization.

This authorization does NOT authorize implementation. No P4 task may be promoted
to READY, and no application code, test, migration, route, controller, Livewire
component, streaming endpoint, JavaScript behavior, or UI may be created. No
Phase 5/6/7 work is authorized. A separate Human Product Owner task/batch
implementation authorization is required before any P4 task becomes READY.

Task contracts authorized (all created in BACKLOG): P4-001, P4-002, P4-003,
P4-004, P4-005, P4-006. Review model = per-task implementation and independent
review (D4-07); P4-006 is the final independent Phase 4 integration gate.

Durable record: ADR-019 in `DECISIONS.md`.

Blocks:

- Phase 4 READY promotion (separate authorization required).

Does Not Block:

- Closed Phase 1/2/3 records; Option D debt; unrelated runnable work.

### DECISION-P4-001-AUTHORIZATION-001 — Authorize P4-001 implementation

Decision ID: DECISION-P4-001-AUTHORIZATION-001

Status: DECIDED — HPO 2026-09-19

Type: Phase Authorization / Task Authorization

Originating Task: P4-001 (Transcript Experience Contract)

Raised By: Human Product Owner (Phase 4 planning session, 2026-09-19)

Priority: HIGH

Question:

Is the reconciled P4-001 contract internally consistent and implementation-ready
such that P4-001 may be authorized for implementation?

Options:

1. Authorize P4-001 implementation (BACKLOG → READY).
2. Return for further contract reconciliation.
3. Do not authorize.

Resolution:

HPO-AUTHORIZED on 2026-09-19. The timestamp-precision inconsistency was
reconciled: persisted segment timestamps remain `decimal(12,3)` seconds (at most
millisecond precision) and are the source of truth, while the formatter may
defensively normalize higher-precision numeric input by rounding to the nearest
millisecond with carry; such input is not claimed to originate from persisted
Phase 3 rows. P4-001 transitioned BACKLOG → READY.

Only P4-001 is authorized. P4-002 through P4-006 remain BACKLOG. P4-002, P4-004,
and P4-005 become eligible for separate implementation authorization only after
P4-001 is independently VERIFIED and closed DONE. Browser-verification tooling
does not block P4-001 and must be resolved before browser-dependent tasks
(P4-003, and ultimately P4-006).

This decision authorizes P4-001 implementation only; it does not authorize any
application code outside the P4-001 contract, any other P4 task, or any Phase
5/6/7 work, and it does not install or authorize a browser-testing tool.

Blocks:

- P4-001 implementation.

Does Not Block:

- P4-002..P4-006 (remain BACKLOG); closed Phase 1/2/3 records; Option D debt.

### DECISION-P4-001-CLOSURE-001 — Close P4-001 (Transcript Experience Contract)

Decision ID: DECISION-P4-001-CLOSURE-001

Status: DECIDED — HPO 2026-09-19

Type: Task Closure

Originating Task: P4-001

Raised By: Human Product Owner

Priority: HIGH

Question:

Is the independently VERIFIED P4-001 eligible for closure as DONE under the
State-to-Action Contract?

Options:

1. Close P4-001: transition VERIFIED → DONE.
2. Do not close yet (return for further work).
3. Close only part of the task.

Resolution:

HPO-CLOSED on 2026-09-19. Closure is based on the completed independent review
(`reviews/P4-001-independent-review.md`):

- P4-001 = VERIFIED
- No BLOCKER, no HIGH, no MEDIUM
- LOW-1 (redundant `segments()->exists()` query when `for()`/`isNoSpeech()` are
  both called) and LOW-2 (no direct unit test for the resolver's missing-key
  exception branch) are non-blocking historical observations
- Focused suite 26 passed / 113 assertions; full suite 392 / 391 passed /
  1 skipped / 1265 assertions / 2 warnings; PHPStan 0 errors; Pint clean — all
  independently reproduced

Canonical transition applied: VERIFIED → (HPO closure decision) → DONE. History
preserved, not rewritten.

This closure is governance/state reconciliation only; it authorizes no
implementation, test, or migration change. It does not authorize P4-002, P4-004,
or P4-005; those become eligible for separate implementation authorization only.
P4-003 remains dependency-gated on P4-002 DONE; P4-006 remains dependency-gated
on all feature tasks DONE. The browser-verification tooling decision remains
pending and is non-blocking for P4-001.

Blocks:

- None.

Does Not Block:

- P4-002..P4-006 (remain BACKLOG); closed Phase 1/2/3 records; Option D debt.

### DECISION-P4-WAVE1-AUTHORIZATION-001 — Authorize Phase 4 Wave 1 implementation

Decision ID: DECISION-P4-WAVE1-AUTHORIZATION-001

Status: DECIDED — HPO 2026-09-19

Type: Phase Authorization / Task Authorization

Originating Scope: Phase 4 Wave 1 — P4-002, P4-004, P4-005

Raised By: Human Product Owner (Phase 4 wave planning, 2026-09-19)

Priority: HIGH

Question:

Are P4-002, P4-004, and P4-005 eligible for implementation authorization now
that P4-001 is DONE, and should they be promoted to READY?

Options:

1. Authorize Wave 1 (P4-002, P4-004, P4-005) and promote them to READY
   (recommended).
2. Authorize a subset.
3. Do not authorize yet.

Resolution:

HPO-AUTHORIZED on 2026-09-19. Eligibility confirmed: P4-001 = DONE with no
unresolved BLOCKER/HIGH/MEDIUM finding, so its direct dependents P4-002, P4-004,
and P4-005 are eligible for separate implementation authorization.

Transition applied:

- P4-002: BACKLOG → READY
- P4-004: BACKLOG → READY
- P4-005: BACKLOG → READY

Not authorized: P4-003 (remains dependency-gated on P4-002 DONE) and P4-006
(remains dependency-gated on all feature tasks DONE). This decision authorizes
implementation of exactly P4-002, P4-004, and P4-005 only. It does not authorize
any other Phase 4 task, translation, or Phase 5/6/7 work. The browser-verification
tooling decision remains pending and does not block these three tasks.

Review model: per-task (D4-07). Each task moves independently to REVIEW after
implementation; no task may be marked VERIFIED or DONE by the implementer.

Blocks:

- None.

Does Not Block:

- P4-003, P4-006 (remain BACKLOG); closed Phase 1/2/3 records; Option D debt.

### DECISION-P4-002-CLOSURE-001 — Close P4-002 (Authorized Private Media Playback)

Decision ID: DECISION-P4-002-CLOSURE-001

Status: DECIDED — HPO 2026-09-20

Type: Task Closure

Originating Task: P4-002

Resolution:

HPO-CLOSED on 2026-09-20 based on `reviews/P4-002-independent-review.md`:
P4-002 = VERIFIED; no BLOCKER/HIGH/MEDIUM. Non-blocking LOW findings (retained):
LOW-1 missing direct bounded-stream regression assertion; LOW-2 several
range-edge branches code-reviewed rather than directly tested; LOW-3 latent
zero-byte-file `Content-Length`/body mismatch for a hypothetical unreachable
input. All thresholds were satisfied, so `CHANGES_REQUESTED` was not required.
Canonical transition: VERIFIED → (HPO closure decision) → DONE. No
implementation/test change authorized.

### DECISION-P4-004-CLOSURE-001 — Close P4-004 (Transcript Search + Copy)

Decision ID: DECISION-P4-004-CLOSURE-001

Status: DECIDED — HPO 2026-09-20

Type: Task Closure

Originating Task: P4-004

Resolution:

HPO-CLOSED on 2026-09-20 based on `reviews/P4-004-independent-review.md`:
P4-004 = VERIFIED; no BLOCKER/HIGH/MEDIUM. Non-blocking LOW findings (retained):
LOW-1 whitespace-only query UI inconsistency; LOW-2 the P4-001
`WorkspaceAvailability`/`WorkspaceState` read-model primitives are not consumed
(ad hoc `segments->isEmpty()` gating). Preserved limitation: real DOM/clipboard
browser behavior is not proven by browser automation; browser evidence is
deferred to the browser-verification strategy. Canonical transition:
VERIFIED → (HPO closure decision) → DONE. No implementation/test change
authorized.

### DECISION-P4-005-CLOSURE-001 — Close P4-005 (Export Hardening)

Decision ID: DECISION-P4-005-CLOSURE-001

Status: DECIDED — HPO 2026-09-20

Type: Task Closure

Originating Task: P4-005

Resolution:

HPO-CLOSED on 2026-09-20 based on `reviews/P4-005-independent-review.md`:
P4-005 = VERIFIED; no BLOCKER/HIGH/MEDIUM. Non-blocking LOW finding (retained):
LOW-1 the no-speech DOCX test asserts only `strlen($docx) > 0`; implementation
behavior was independently verified by source inspection. Canonical transition:
VERIFIED → (HPO closure decision) → DONE. No implementation/test change
authorized.

Wave 1 state recorded: P4-002 = DONE, P4-004 = DONE, P4-005 = DONE →
Phase 4 Wave 1 = CLOSED. This does not close Phase 4.

### DECISION-P4-BROWSER-VERIFICATION-001 — Phase 4 browser evidence strategy

Decision ID: DECISION-P4-BROWSER-VERIFICATION-001

Status: DECIDED — HPO 2026-09-20

Type: Architecture / Verification

Originating Scope: Phase 4 (P4-003, P4-006)

Resolution:

HPO-DECIDED on 2026-09-20 — For the Phase 4 baseline, browser-dependent
behavior is verified with documented, reproducible, environment-dependent
browser verification and retained evidence. No browser-automation platform
(Playwright, Laravel Dusk, Selenium, Cypress, or equivalent) is introduced at
this stage.

Required evidence for browser-dependent tasks: exact verification steps;
browser/environment metadata; media fixture identity/type; observed media
`currentTime`; expected vs observed seek value; screenshots and/or screen
recording where useful; console/error state; and separate audio and video
evidence where required. Numeric seek-precision claims must record the observed
value directly, not rely on screenshots alone. Backend tests must never be
represented as proving browser behavior.

For P4-003 the evidence must prove: real `HTMLMediaElement` loaded; stream URL
consumed; timestamp click changes `currentTime`; seek target matches persisted
milliseconds; active segment changes correctly; gap yields no active segment;
manual seek updates the active segment; audio behavior; video behavior;
auto-scroll behavior; and a keyboard-operable timestamp control.

Tooling escalation rule: browser automation may be reconsidered later only if
reproducible browser evidence proves unreliable, P4-006 independent verification
cannot be repeated confidently, or a later phase requires ongoing browser
regression automation — each requiring separate explicit authorization. No
tooling is installed by this decision.

### DECISION-P4-003-AUTHORIZATION-001 — Authorize P4-003 implementation

Decision ID: DECISION-P4-003-AUTHORIZATION-001

Status: DECIDED — HPO 2026-09-20

Type: Phase Authorization / Task Authorization

Originating Task: P4-003 (Segment Navigation + Synchronized Highlighting)

Resolution:

HPO-AUTHORIZED on 2026-09-20. Prerequisites satisfied: P4-002 = DONE (closed
via DECISION-P4-002-CLOSURE-001), and the browser-verification strategy is
resolved (DECISION-P4-BROWSER-VERIFICATION-001).

Transition applied: P4-003: BACKLOG → READY.

Scope confirmed (unchanged, per its contract): native audio/video media element
binding; P4-002 authorized stream URL; timestamp click-to-seek;
millisecond-accurate seek target; P4-001 active-segment resolver semantics;
synchronized active highlight; gap/boundary behavior; baseline auto-scroll;
language-label preservation; accessibility baseline; read-only transcript
behavior. Out of scope: search/copy changes, export changes, waveform/timeline
editing, transcript editing, diarization, chapters, annotations, translation,
schema changes.

P4-006 is NOT authorized by this decision and remains dependency-gated on
P4-003 (and all feature tasks) being VERIFIED and closed DONE. This decision
authorizes implementation of P4-003 only.

### DECISION-P4-BROWSER-VERIFICATION-002 — Authorize Playwright verification tooling for Phase 4

Decision ID: DECISION-P4-BROWSER-VERIFICATION-002

Status: DECIDED — HPO 2026-09-20

Type: Architecture / Verification

Originating Scope: Phase 4 (P4-003, P4-006)

Amends: DECISION-P4-BROWSER-VERIFICATION-001 (which is preserved, not erased or
rewritten).

Resolution:

HPO-DECIDED on 2026-09-20. The initial strategy
(DECISION-P4-BROWSER-VERIFICATION-001: documented environment-dependent manual
browser verification, no automation) proved not reliably executable by the CLI
implementation agent, which has no GUI browser. This satisfies the escalation
condition recorded in that decision for reconsidering tooling.

Playwright is authorized as verification tooling for Phase 4 only:

- P4-003 browser verification;
- P4-006 final Phase 4 integration verification;
- directly related Phase 4 browser regression evidence where required.

Constraints: dev/test-only; not a production or product-runtime dependency; not
a frontend architecture; does not replace Pest/PHP tests; Chromium-only unless a
contract explicitly requires otherwise; minimal configuration; no unrelated
JavaScript tooling; no general Phase 5/6/7 authorization. Any use beyond Phase 4
verification requires separate explicit authorization.

History preserved:

```text
initial strategy: manual/environment-dependent browser evidence
observed limitation: CLI agent cannot execute GUI verification
reconciliation: Playwright authorized as Phase 4 verification tooling
```

P4-003 remains REVIEW (its browser evidence is completed under this decision);
P4-006 remains BACKLOG and still requires separate HPO authorization.

### DECISION-P4-003-CLOSURE-001 — Close P4-003 (Segment Navigation + Synchronized Highlighting)

Decision ID: DECISION-P4-003-CLOSURE-001

Status: DECIDED — HPO 2026-09-20

Type: Task Closure

Originating Task: P4-003

Resolution:

HPO-CLOSED on 2026-09-20 based on `reviews/P4-003-independent-review.md`:
P4-003 = VERIFIED; no BLOCKER/HIGH/MEDIUM; all 13 acceptance criteria PASS; all
automated and browser evidence independently reproduced.

Non-blocking findings retained:

- LOW-1: video-fixture interactive browser evidence gap (mitigated by
  tag-agnostic, non-branching binding code; recommend closing in P4-006).
- LOW-2: one isolated cold-start Playwright timing flake that did not recur.
- INFO-1: pre-existing `ReferenceError: showRenameModal is not defined`,
  confirmed via git history to predate Phase 3/4 and unrelated to P4-003.

Canonical transition: VERIFIED → (HPO closure decision) → DONE. History
preserved, not rewritten (including both `DECISION-P4-BROWSER-VERIFICATION-001`
and `-002` and ADR-020). No implementation or test change is authorized by this
closure.

### DECISION-P4-006-AUTHORIZATION-001 — Authorize P4-006 final integration verification

Decision ID: DECISION-P4-006-AUTHORIZATION-001

Status: DECIDED — HPO 2026-09-20

Type: Phase Authorization / Task Authorization

Originating Task: P4-006 (Phase 4 Integration Verification)

Resolution:

HPO-AUTHORIZED on 2026-09-20. Dependency gate satisfied: P4-001, P4-002, P4-003,
P4-004, and P4-005 are each DONE (HPO-closed), not merely implementation-complete.

The P4-006 contract remains the final Phase 4 integration verification gate with
the V4-01 through V4-25 matrix unchanged. It is verification, not feature
development, and must independently execute the final integration evidence over
a real completed persisted transcription; it may reuse the authorized Playwright
tooling, fixture infrastructure, auth setup, and harness primitives (ADR-020 /
`DECISION-P4-BROWSER-VERIFICATION-002`), but must not substitute P4-003's prior
observed results for its own execution.

Feature behavior freeze: P4-001..P4-005 feature behavior is frozen for final
verification. P4-006 may execute harnesses, create evidence, exercise real
application behavior, and record failures. It must not silently fix feature
defects; a defect must be recorded, attributed to its owning task, and returned
to governance for correction/re-review.

Transition applied: P4-006: BACKLOG → READY.

P4-006 is not executed by this decision. Phase 4 remains IN PROGRESS and is not
closed. No Phase 5/6/7 work is authorized.

### DECISION-P4-006-FINDING-001 — P4-006 final gate found a P4-004 defect

Decision ID: DECISION-P4-006-FINDING-001

Status: CLOSED — P4-004 corrective cycle independently VERIFIED
(`reviews/P4-004-corrective-independent-re-review.md`) and HPO re-closed DONE
(DECISION-P4-004-CORRECTIVE-CLOSURE-001)

Type: Architecture / Task Correction

Originating Task: P4-006 (final integration verification); finding owned by P4-004

Raised By: OpenCode verification executor (2026-09-20)

Priority: HIGH

Question:

P4-006 final integration verification found a real-browser defect in the frozen
P4-004 `transcriptSearch` component: methods that query the DOM via `this.$el`
fail when invoked from child-element event handlers (Alpine `$el` resolves to
the event target, not the component root). This breaks per-segment copy (V4-18)
and search next/previous current-match navigation (V4-14). How should this be
handled?

Options:

1. Reopen P4-004 for a narrow correction cycle (fix `this.$el` scoping by
   capturing the component root once; add regression tests for segment copy and
   current-match navigation), then re-run P4-006. (recommended)
2. Accept with known limitations: record the defect and defer the fix.
3. Other HPO-directed resolution.

Recommendation:

Option 1. Both are Phase 4 baseline features (per-segment copy; match
navigation), and the fix is narrow and low-risk.

Impact:

The P4-006 final integration gate cannot pass and Phase 4 cannot close until this
is resolved. No security impact.

Blocks:

- P4-006 REVIEW / VERIFIED / DONE.
- Phase 4 closure.

Does Not Block:

- P4-001, P4-002, P4-003, P4-005 (DONE); unrelated runnable work.

Resolution:

CLOSED on 2026-09-20. The P4-004 corrective cycle was independently re-verified
(`reviews/P4-004-corrective-independent-re-review.md`: P4-004 corrective cycle =
VERIFIED; no BLOCKER/HIGH/MEDIUM; V4-14 and V4-18 now PASS in a real browser;
P4-004 Playwright 3 passed; P4-006 browser suite 14 passed; full PHP 433/432/1;
Pint clean; PHPStan 0 errors) and the HPO re-closed P4-004 as DONE
(DECISION-P4-004-CORRECTIVE-CLOSURE-001). P4-006 is released BLOCKED to READY
under the existing `DECISION-P4-006-AUTHORIZATION-001` (no new authorization
required). Original failure evidence is preserved. Evidence: `P4-006-INTEGRATION-VERIFICATION-EVIDENCE.md` §E;
`verification/artifacts/p4-006-browser-results.json`.

### DECISION-P4-004-REOPEN-001 — Corrective reopen of P4-004

Decision ID: DECISION-P4-004-REOPEN-001

Status: DECIDED — HPO 2026-09-20

Type: Task Correction / Architecture

Originating Task: P4-004 (finding from P4-006)

Resolution:

HPO-AUTHORIZED on 2026-09-20. Basis: P4-006 final integration verification
(`DECISION-P4-006-FINDING-001`) found a MEDIUM real-browser defect owned by
P4-004: the `transcriptSearch` component queries the DOM via `this.$el`, which
Alpine resolves to the event-target element (the button) inside child-element
event handlers, not the component root. This breaks:

- V4-18 segment copy (click → "Nothing to copy"; clipboard empty);
- V4-14 search next/previous current-match navigation (highlight does not move).

Scope: corrective reopen limited to V4-14 and V4-18 plus directly necessary
regression coverage. No unrelated P4-004 design change.

Historical preservation: the original P4-004 sequence
(BACKLOG → READY → implementation → REVIEW → independent VERIFIED → HPO closure
→ DONE) and its independent review artifact remain valid at the time and are not
rewritten. This is a post-closure corrective reopen based on later real-browser
evidence.

State: the State-to-Action Contract defines no dedicated "reopened" state, so the
closest policy-compliant active correction state is used: P4-004 moves to
REVIEW after the corrective implementation (awaiting independent re-review). It
must not be marked VERIFIED or DONE by the implementer.

Consequence: P4-006 remains BLOCKED until the P4-004 correction is independently
re-verified and HPO re-closed. This decision does not close the P4-006 finding.

### DECISION-P4-004-CORRECTIVE-CLOSURE-001 — Close P4-004 corrective cycle

Decision ID: DECISION-P4-004-CORRECTIVE-CLOSURE-001

Status: DECIDED — HPO 2026-09-20

Type: Task Closure (corrective cycle)

Originating Task: P4-004

Resolution:

HPO-CLOSED on 2026-09-20 based on
`reviews/P4-004-corrective-independent-re-review.md`:

- P4-004 corrective cycle = VERIFIED
- No BLOCKER, no HIGH, no MEDIUM; INFO-1 only (dead-code `?? this.$root` fallback)
- V4-14 = PASS; V4-18 = PASS (independently re-executed in a real browser)
- P4-004 Playwright regression 3 passed; full P4-006 browser suite 14 passed
- Focused PHP 7 passed / 19 assertions; full PHP 433 total / 432 passed /
  1 skipped / 1453 assertions / 2 warnings; Pint clean; PHPStan 0 errors

Canonical transition: VERIFIED → (HPO closure decision) → DONE.

Historical preservation: the original P4-004 cycle
(BACKLOG → READY → implementation → REVIEW → independent VERIFIED → HPO closure
→ DONE, `DECISION-P4-004-CLOSURE-001`) and `reviews/P4-004-independent-review.md`
remain valid and unchanged. The corrective sequence
(P4-006 finding → `DECISION-P4-004-REOPEN-001` → corrective implementation →
REVIEW → corrective independent re-review → VERIFIED → HPO corrective re-closure
→ DONE) is recorded alongside it. Original LOW-1/LOW-2 findings are retained as
historical, non-blocking observations.

No implementation or test change is authorized by this closure.

### DECISION-P4-006-CLOSURE-001 — Close P4-006 (Phase 4 Integration Verification)

Decision ID: DECISION-P4-006-CLOSURE-001

Status: DECIDED — HPO 2026-09-20

Type: Task Closure

Originating Task: P4-006

Resolution:

HPO-CLOSED on 2026-09-20 based on `reviews/P4-006-independent-review.md`:

- P4-006 = VERIFIED; no BLOCKER/HIGH/MEDIUM
- All 25 mandatory V4-01..V4-25 items independently reproduced as PASS
- LOW-1: intermittent V4-08 Playwright playback timing-margin flake (approx.
  1/6 executions, cold and repeat), reaffirming P4-003 LOW-2; non-blocking
  verification-timing debt, not a demonstrated product playback defect
- INFO-1: pre-existing `ReferenceError: showRenameModal is not defined`
  (predates Phase 3/4; unrelated)
- Full PHP 433/432/1/1453 assertions/2 warnings; Pint clean; PHPStan 0 errors;
  Playwright 14/14 cold, with one 13/14 repeat sample (V4-08)

Canonical transition: VERIFIED → (HPO closure decision) → DONE.

History preserved (not a straight-line success): the first failed final-gate
execution (V4-14/V4-18 FAIL) and its evidence file remain unchanged; the P4-004
corrective reopen/review/re-closure and `DECISION-P4-006-FINDING-001` resolution
are retained; the fresh rerun evidence file is retained. No implementation or
test change is authorized by this closure.

### DECISION-PHASE4-CLOSURE-001 — Close Phase 4 (Transcript Experience baseline)

Decision ID: DECISION-PHASE4-CLOSURE-001

Status: DECIDED — HPO 2026-09-20

Type: Phase Completion

Originating Scope: Phase 4 — Transcript Experience baseline (ADR-019)

Resolution:

HPO-CLOSED on 2026-09-20. Phase 4 completion gate record:

```text
P4-001 = DONE
P4-002 = DONE
P4-003 = DONE
P4-004 = DONE
P4-005 = DONE
P4-006 = DONE

Final Phase 4 integration verification = VERIFIED
(reviews/P4-006-independent-review.md)

No unresolved BLOCKER/HIGH/MEDIUM
```

Phase 4 = CLOSED. The delivered baseline: completed/processing/failed/no-speech
workspace lifecycle; authorized private audio/video playback with byte ranges and
ownership enforcement; synchronized transcript (click-to-seek at millisecond
precision, active segment, gaps, audio/video parity, baseline auto-scroll,
keyboard baseline); client-side Latin/Chinese/Tamil search with current-match
navigation; full and per-segment copy with Unicode preservation; TXT/SRT/VTT/DOCX
export hardening; real-browser final integration verification with Phase 3 and
full quality regression.

Residual non-blocking LOW/INFO debt is retained (see `reviews/PHASE4-final-closure.md`
and the individual review artifacts); Phase 4 is not represented as defect-free.

Phase 4 closure grants eligibility for future Phase 5 planning/authorization
only. It does not authorize Phase 5. Translation, transcript editing,
diarization, chapters, annotations, waveform editing, and advanced production
hardening remain future-phase concerns. No implementation change is authorized
by this closure.

### DECISION-P4-01 — Ratify the Phase 4/5 boundary (Transcript Experience baseline / Translation)

Decision ID: DECISION-P4-01

Status: DECIDED — HPO 2026-09-19

Type: Architecture / Phase Authorization

Originating Scope: Phase 4 planning (`PHASE4-PLANNING.md`)

Raised By: OpenCode planning agent (2026-09-19)

Priority: HIGH

Question:

Should the proposed boundary — Phase 4 = Transcript Experience baseline and
Phase 5 = Translation, with Phase 6 (Advanced Transcript UX) and Phase 7
(Production Hardening) unchanged — be ratified as ADR-019?

Options:

1. Ratify as proposed (recommended).
2. Ratify with a modified Phase 4/Phase 6 split (specify the split).
3. Do not ratify; keep the Phase 4/5 boundary unreconciled.

Recommendation:

Option 1. It reconciles the boundary ADR-017 left open and matches the product
priority sequence (transcript experience, then translation).

Impact:

Ratifying the boundary is required before Phase 4 task contracts are authored.
It does not authorize Phase 4 and does not promote any task to READY.

Blocks:

- Phase 4 task authoring.
- DECISION-P4-07.

Does Not Block:

- Closed Phase 1/2/3 records; Option D debt; unrelated runnable work.

Resolution:

HPO-DECIDED on 2026-09-19 — ACCEPTED as proposed. Phase 4 = Transcript
Experience baseline; Phase 5 = Translation; Phase 6 = Advanced Transcript UX
(reserved); Phase 7 = Production Hardening (reserved). Durable record: ADR-019
in `DECISIONS.md` (PROPOSED → ACCEPTED).

### DECISION-P4-02 — Media delivery model for playback

Decision ID: DECISION-P4-02

Status: DECIDED — HPO 2026-09-19

Type: Architecture / Security

Originating Scope: candidate task P4-002

Raised By: OpenCode planning agent (2026-09-19)

Priority: HIGH

Question:

How should private media be delivered for in-browser playback?

Options:

1. Authorized range/streaming endpoint behind `MediaFilePolicy`, opaque
   references only, no path leakage (recommended).
2. Signed temporary URL.
3. No playback; keep download-only.

Recommendation:

Option 1. It reuses the existing ownership/authorization boundary and avoids
loading whole media into PHP memory.

Impact:

Determines the security boundary, range/206 behavior, and whether any new
storage/URL signing mechanism is introduced.

Blocks:

- P4-002 task authoring.

Does Not Block:

- P4-004 (search) and P4-005 (export), which read persisted text only.

Resolution:

HPO-DECIDED on 2026-09-19 — Authorized application range-stream endpoint behind
the existing media/transcription ownership policy. Must support HTTP byte ranges
and `206 Partial Content`, correct `Content-Type`, `Accept-Ranges`, audio and
video, no private filesystem path exposure, no full-object PHP memory load, and
must preserve the Phase 2 private-storage contracts. Signed temporary storage
URLs are not the Phase 4 baseline; a future deployment architecture (Phase 7)
may revisit delivery strategy. Durable record: ADR-019.

### DECISION-P4-03 — Export format set in Phase 4

Decision ID: DECISION-P4-03

Status: DECIDED — HPO 2026-09-19

Type: Product

Originating Scope: candidate task P4-005

Raised By: OpenCode planning agent (2026-09-19)

Priority: MEDIUM

Question:

Which export formats remain in Phase 4 scope?

Options:

1. Keep TXT/SRT/VTT and DOCX; harden and test all four (recommended).
2. TXT/SRT/VTT only; drop DOCX.
3. TXT only.

Recommendation:

Option 1. All four already exist in `TranscriptionExportController`; the Phase 4
work is correctness hardening and multilingual/unicode coverage, not new build.

Impact:

Determines the P4-005 test matrix only; no schema impact.

Blocks:

- P4-005 scope detail.

Does Not Block:

- P4-002/P4-003/P4-004.

Resolution:

HPO-DECIDED on 2026-09-19 — Keep all currently implemented export formats: TXT,
SRT, VTT, DOCX. Phase 4 must harden and verify the existing export
implementation rather than rebuild it, covering completed-transcription gating,
authorization, persisted real segments, UTF-8/multilingual content (Bahasa
Melayu, English, Chinese, Tamil, `und`), millisecond timestamp correctness for
timed formats, deterministic ordering, and no DOCX regression. DOCX is not
removed. Durable record: ADR-019.

### DECISION-P4-04 — In-transcript search implementation and scope

Decision ID: DECISION-P4-04

Status: DECIDED — HPO 2026-09-19

Type: Product / Architecture

Originating Scope: candidate tasks P4-001 and P4-004

Raised By: OpenCode planning agent (2026-09-19)

Priority: MEDIUM

Question:

How should in-transcript search be implemented and scoped?

Options:

1. Client-side over the loaded segments of one transcript (recommended for the
   baseline).
2. Server-side full-text search (may require an additive index).
3. No search in Phase 4.

Recommendation:

Option 1. It is sufficient for the baseline and requires no schema change.

Impact:

Option 2 would introduce a search index and broader scope; Option 1 is confined
to a single transcript.

Blocks:

- P4-001 search semantics; P4-004 scope.

Does Not Block:

- P4-002, P4-003, P4-005.

Resolution:

HPO-DECIDED on 2026-09-19 — Client-side search over the loaded persisted
transcript segments for the Phase 4 baseline. No server-side full-text search
infrastructure and no database search index in Phase 4. Search must be
Unicode-safe (Latin, Chinese, Tamil), identify/highlight matches, allow
navigation between matches where the approved UX contract requires it, and remain
read-only. Server-side/indexed search may be reconsidered later only if actual
transcript sizes demonstrate a performance problem. Durable record: ADR-019.

### DECISION-P4-05 — Transcript editing in Phase 4

Decision ID: DECISION-P4-05

Status: DECIDED — HPO 2026-09-19

Type: Product

Originating Scope: Phase 4 boundary

Raised By: OpenCode planning agent (2026-09-19)

Priority: HIGH

Question:

Does Phase 4 include transcript editing, or is editing deferred to Phase 6?

Options:

1. No editing in Phase 4; defer to Phase 6 (recommended).
2. Allow transcript/segment editing in Phase 4.

Recommendation:

Option 1. Editing introduces mutation, versioning, and audit scope and interacts
with Phase 3 completed-transcription protection; it belongs to Advanced
Transcript UX.

Impact:

Option 2 materially expands Phase 4 scope and requires a completed-transcript
mutation decision.

Blocks:

- Phase 4 boundary finalization.

Does Not Block:

- P4-002/P4-003/P4-004/P4-005 read-only work.

Resolution:

HPO-DECIDED on 2026-09-19 — Transcript editing is excluded from Phase 4 and
remains in Phase 6 (Advanced Transcript UX). Phase 4 transcript content is
read-only. No text editing, segment editing, timestamp editing, merge/split
operations, autosave, or revision history in Phase 4. Durable record: ADR-019.

### DECISION-P4-06 — Player scope

Decision ID: DECISION-P4-06

Status: DECIDED — HPO 2026-09-19

Type: Product / UX

Originating Scope: candidate tasks P4-002 and P4-003

Raised By: OpenCode planning agent (2026-09-19)

Priority: LOW

Question:

What is the Phase 4 player scope?

Options:

1. Audio and video, play/pause, millisecond-accurate seek, volume, playback
   speed; no editing (recommended).
2. Minimal play/pause and seek only.

Recommendation:

Option 1. It covers the baseline transcript-navigation experience without
expanding into editing.

Impact:

Determines player UI surface and P4-003 seek/highlight precision.

Blocks:

- P4-002/P4-003 detail only.

Does Not Block:

- P4-004/P4-005.

Resolution:

HPO-DECIDED on 2026-09-19 — Phase 4 baseline player supports audio and video,
play/pause, standard volume behavior, normal seek controls, click transcript
timestamp → media seek, millisecond-accurate seek target, and synchronized
active-segment highlighting. Playback-speed control may be included only if it is
a small baseline control consistent with the task contract and does not expand
into advanced editing/UX. No waveform editor, frame-accurate editing, clipping,
timeline editing, annotations, or chapter editing. The key contract is playback +
transcript synchronization, not media editing. Durable record: ADR-019.

### DECISION-P4-07 — Phase 4 authorization and review model

Decision ID: DECISION-P4-07

Status: DECIDED — HPO 2026-09-19

Type: Phase Authorization

Originating Scope: Phase 4 (`PHASE4-PLANNING.md`)

Raised By: OpenCode planning agent (2026-09-19)

Priority: HIGH

Question:

Should Phase 4 be authorized, and under which review model?

Options:

1. Authorize Phase 4 with the normal per-task review cadence.
2. Authorize Phase 4 with a phase-specific batch-execution exception (the
   Phase 3 exception is Phase-3-only and must be recorded separately for
   Phase 4 if adopted).
3. Do not authorize Phase 4 yet.

Recommendation:

Option 1 or a separately recorded Option 2 after DECISION-P4-01 is ratified and
D4-02/D4-05 are resolved.

Impact:

Authorization is required before any P4 task is promoted to READY. This decision
does not itself mark anything VERIFIED or DONE.

Blocks:

- Phase 4 READY promotion.

Does Not Block:

- Closed Phase 1/2/3 records; Option D debt; unrelated runnable work.

Resolution:

HPO-DECIDED on 2026-09-19 — Per-task implementation and independent review. No
Phase-3-style batch-review exception is created for Phase 4. Canonical pattern:
P4-001 → implementation → REVIEW → independent review → VERIFIED → HPO closure →
DONE, then P4-002, and so on through P4-006, respecting dependencies. P4-006 is
the final Phase 4 integration verification gate. Implementation completion alone
must not authorize the next dependent task where the contract requires the
dependency to be VERIFIED/DONE. This decision does not promote any task to READY;
a separate task/batch implementation authorization is required
(DECISION-PHASE4-AUTHORIZATION-001). Durable record: ADR-019.

### DECISION-P3-BATCH3-001 — Authorize Phase 3 Batch 3 (P3-007 + P3-008)

Decision ID: DECISION-P3-BATCH3-001

Status: DECIDED — HPO authorized 2026-09-19

Type: Phase Authorization

Originating Scope: Phase 3 — Real Transcription Engine (ADR-017), Batch 3

Raised By: Batch 3 planning (OpenCode planning agent, 2026-09-19)

Priority: HIGH

Question:

Should Phase 3 Batch 3 be authorized for implementation and verification, and
may P3-007 and P3-008 be promoted to READY?

Options:

1. Authorize Batch 3: promote P3-007 and P3-008 to READY. P3-008 execution
   remains gated on P3-007 being implementation-complete and independently
   VERIFIED.
2. Authorize P3-007 only; keep P3-008 BACKLOG pending a later authorization.
3. Do not authorize Batch 3 yet.

Recommendation:

Option 1 once the OPEN decisions B3-01 through B3-07 below are resolved.
P3-007 is the retry/recovery implementation; P3-008 is its phase-gate
verification. Authorizing both is the batch model used for Batch 1/Batch 2.

Impact:

Authorizing Batch 3 allows implementation to begin. P3-007/P3-008 become READY.
VERIFIED → DONE and Phase 3 closure remain separate HPO decisions. This
authorization does not itself close Phase 3.

Blocks:

- P3-007 and P3-008 promotion to READY.

Does Not Block:

- Phase 2 deferred debt; Batch 1/Batch 2 closures; unrelated runnable work.

Resolution:

HPO-AUTHORIZED on 2026-09-19. Batch 3 authorized for P3-007 and P3-008.
P3-007 and P3-008 promoted to READY. P3-008 execution remains dependency-gated
on P3-007 being implementation-complete and independently VERIFIED. Batch 3
authorization does not close Phase 3. This authorization does not itself promote
any task beyond READY, mark anything VERIFIED/DONE, or close Phase 3. Durable
record: ADR-018 in `DECISIONS.md`.

### DECISION-P3-BATCH3-01 — Retry re-open semantics for a failed transcription

Decision ID: DECISION-P3-BATCH3-01

Status: DECIDED — HPO 2026-09-19 (Option 1)

Type: Architecture / Product

Originating Task: P3-007

Raised By: Batch 3 planning (OpenCode planning agent, 2026-09-19)

Priority: BLOCKER

Question:

`TranscriptionLifecycle` makes `failed` fully terminal, and both
`TranscriptionOrchestrator::request()` and `TranscriptionResultWriter::persist()`
reject/early-return on `failed`. P3-007 requires retry of the "same logical
transcription" with a new attempt identity. How should retry be represented?

Options:

1. Add `failed → queued` to the canonical lifecycle; retry re-opens the same
   transcription and creates a new `ProcessingJob` attempt (recommended).
2. Keep `failed` terminal; each retry creates a new `Transcription` row for the
   same MediaFile (new logical transcription).
3. Keep `failed` terminal; P3-007 implements no retry of failed transcriptions.

Recommendation:

Option 1. It is the only option consistent with the already-VERIFIED
`ProcessingAttemptIdentity` contract and P3-007's logical-identity requirement.

Impact:

Option 1 modifies a Batch 1/2 VERIFIED lifecycle contract and requires adjusting
the writer/orchestrator terminal guards, with full Batch 2 regression.

Blocks:

- P3-007 implementation.

Does Not Block:

- P3-008 preparation (fixtures/plan) only.

Resolution:

HPO-DECIDED on 2026-09-19 — Option 1. Same-transcription retry with a new
processing attempt is approved. Canonical lifecycle extension:
`failed → queued`, reachable only through an explicit authorized retry action.
Each retry creates a new `ProcessingJob`; the previous failed attempt remains
immutable historical evidence and is never reused or reset. Transcription
identity is unchanged. Durable record: ADR-018 in `DECISIONS.md`.

### DECISION-P3-BATCH3-02 — Retry trigger model (manual vs automatic vs mixed)

Decision ID: DECISION-P3-BATCH3-02

Status: DECIDED — HPO 2026-09-19 (manual-only retry)

Type: Product

Originating Task: P3-007

Raised By: Batch 3 planning (OpenCode planning agent, 2026-09-19)

Priority: HIGH

Question:

Should P3-007 retry retryable failures automatically, only manually, or both?

Options:

1. Manual-only retry.
2. Bounded automatic retry for retryable categories, plus manual retry after
   exhaustion (recommended).
3. Unlimited automatic retry with backoff for retryable categories.

Recommendation:

Option 2, subject to DECISION-P3-BATCH3-03 for the bound/backoff. The P3-007
contract requires bounded attempts, explicit backoff, and safe worker-outage
retry.

Impact:

Determines whether a retry action/UI is required and whether retry scheduling
exists. Option 3 is not acceptable (violates "bounded attempts").

Blocks:

- P3-007 implementation.

Does Not Block:

- P3-008 preparation.

Resolution:

HPO-DECIDED on 2026-09-19 — Manual domain retry only for Phase 3 (Option 1).
Automatic domain-level retry is not implemented in Phase 3. A retry occurs only
through the canonical explicit retry action at the product/application boundary.
Laravel transport retry remains distinct from domain transcription retry and
stays effectively single-attempt per the established queue contract. No
automatic domain retry scheduler is introduced; automatic retry may be
considered in a future phase. Durable record: ADR-018 in `DECISIONS.md`.

### DECISION-P3-BATCH3-03 — Retry bound and backoff profile

Decision ID: DECISION-P3-BATCH3-03

Status: DECIDED — HPO 2026-09-19 (no automatic schedule)

Type: Product

Originating Task: P3-007

Raised By: Batch 3 planning (OpenCode planning agent, 2026-09-19)

Priority: HIGH

Question:

What concrete retry bound and backoff should P3-007 use?

Options:

1. 3 attempts total (1 initial + 2 retries); backoff 30s then 120s
   (recommended).
2. Time bound: retry while elapsed < 15 min, max 5 attempts; backoff
   30s/60s/120s.
3. Single automatic retry only; backoff 60s.

Recommendation:

Option 1. Balanced, bounded, and matches the task wording.

Impact:

Sets the number of `ProcessingJob` attempts created and the scheduling delays;
directly affects exhaustion semantics.

Blocks:

- P3-007 implementation (if B3-02 selects automatic or mixed retry).

Does Not Block:

- P3-008 preparation.

Resolution:

HPO-DECIDED on 2026-09-19 — No automatic retry count or backoff schedule is
introduced in Phase 3 (the proposed automatic options are moot because B3-02
selected manual-only). Each explicit valid manual retry request may create one
new attempt. Concurrency/idempotency protection ensures repeated or concurrent
submissions of the same retry action cannot create multiple simultaneously
active attempts. No arbitrary lifetime retry cap is imposed. Historical attempts
remain observable/auditable. Durable record: ADR-018 in `DECISIONS.md`.

### DECISION-P3-BATCH3-04 — Retranscription of completed transcripts

Decision ID: DECISION-P3-BATCH3-04

Status: DECIDED — HPO 2026-09-19 (completed protected)

Type: Product

Originating Task: P3-007

Raised By: Batch 3 planning (OpenCode planning agent, 2026-09-19)

Priority: MEDIUM

Question:

May an already-completed transcript be reprocessed/replaced in P3-007?

Options:

1. Completed is protected; retry/reprocess forbidden; retranscription is a
   separate future feature (recommended).
2. Completed may be intentionally replaced by an explicit re-run action.
3. Completed protected; a future new-transcription-from-same-media is allowed
   but outside P3-007.

Recommendation:

Option 1. The spec places retranscription outside Phase 3 unless separately
decided, and Batch 2 already enforces completion protection.

Impact:

Determines whether completed results can ever be overwritten and whether
version history is needed.

Blocks:

- P3-007 completed-state acceptance criteria only.

Does Not Block:

- P3-007 retry/recovery implementation for non-completed transcriptions.

Resolution:

HPO-DECIDED on 2026-09-19 — Option 1. Completed transcriptions are protected
and cannot be retried or retranscribed under P3-007. For `status = completed`,
the retry action must reject or safely no-op per the task contract. Transcript
text, detected language, segments, completion metadata, and successful attempt
history must not be overwritten. Retranscription/reprocessing of completed
media is a separate future product feature outside Phase 3. Durable record:
ADR-018 in `DECISIONS.md`.

### DECISION-P3-BATCH3-05 — Abandoned running-attempt recovery

Decision ID: DECISION-P3-BATCH3-05

Status: DECIDED — HPO 2026-09-19 (recoverable failure, no auto-rerun)

Type: Architecture / Product

Originating Task: P3-007

Raised By: Batch 3 planning (OpenCode planning agent, 2026-09-19)

Priority: HIGH

Question:

How should a `running` processing attempt abandoned by a worker crash be
recovered?

Options:

1. Timeout/lease-based recovery: a configurable stale threshold transitions a
   stale `running` attempt to `failed` (retryable), then normal retry applies
   (recommended).
2. Manual admin recovery command only.
3. No recovery in P3-007; defer to Phase 7 production hardening.

Recommendation:

Option 1, with the threshold set above the 300s provider timeout, reusing the
P2-004A2 guarded-CAS crash-recovery precedent.

Impact:

Determines whether P3-007 handles the "worker crash / attempt running
indefinitely" scenarios listed in its own contract.

Blocks:

- P3-007 recovery acceptance criteria.

Does Not Block:

- P3-007 core retry/recovery for returned failures.

Resolution:

HPO-DECIDED on 2026-09-19 — Option 1 with a manual-retry boundary. Phase 3 must
support recovery of abandoned `running` attempts, but recovery must not
automatically start a new inference attempt. Canonical semantics: a demonstrably
stale/abandoned `running` attempt is moved to a terminal recoverable failure
state, after which the transcription becomes eligible for explicit manual retry.
The stale threshold is derived conservatively from the actual current provider
execution timeout contract/configuration (~300s), not an arbitrary hard-coded
value. A stale-authority guard must prevent an old worker that later resumes
from completing or overwriting newer authoritative state. Use the minimum
additive schema needed to prove staleness safely; no heartbeat infrastructure
unless strictly necessary. Durable record: ADR-018 in `DECISIONS.md`.

### DECISION-P3-BATCH3-06 — Live Redis evidence for the Phase 3 gate

Decision ID: DECISION-P3-BATCH3-06

Status: DECIDED — HPO 2026-09-19 (live Redis mandatory)

Type: Phase Completion / Architecture

Originating Task: P3-008

Raised By: Batch 3 planning (OpenCode planning agent, 2026-09-19)

Priority: HIGH

Question:

Must P3-008 prove live Redis queue execution before it can be VERIFIED, given
Batch 2 explicitly did not?

Options:

1. Mandatory live Redis execution before P3-008 VERIFIED (recommended).
2. Accept a documented environment gap; verify the abstraction + outage path and
   record live Redis as a pre-production residual requirement.
3. Require live Redis only in CI.

Recommendation:

Option 1, per P3-008 AC2 and the spec Completion Gate item 8. Option 2 is
acceptable only as an explicitly recorded HPO limitation, never as a silent
claim of verification.

Impact:

Determines whether Phase 3 can complete in the current environment and what
evidence must be retained.

Blocks:

- P3-008 VERIFIED (and Phase 3 completion).

Does Not Block:

- P3-007 implementation.

Resolution:

HPO-DECIDED on 2026-09-19 — Option 1. Live Redis integration evidence is
mandatory before P3-008 can be VERIFIED. P3-008 must exercise an actual Redis
instance and retain evidence of: dispatch through the configured Redis
connection; the `transcription` queue; real serialization/deserialization;
worker consumption of the Redis job; authoritative DB state reload; completion
through the established pipeline; absence of media path/binary in the serialized
job; and valid duplicate/stale protections where applicable. If Redis is
unavailable, P3-008 remains unverified. Database-queue evidence is not a
substitute for live Redis evidence. Durable record: ADR-018 in `DECISIONS.md`.

### DECISION-P3-BATCH3-07 — Real faster-whisper + FFmpeg execution evidence

Decision ID: DECISION-P3-BATCH3-07

Status: DECIDED — HPO 2026-09-19 (real worker mandatory)

Type: Phase Completion

Originating Task: P3-008

Raised By: Batch 3 planning (OpenCode planning agent, 2026-09-19)

Priority: HIGH

Question:

What level of real worker/FFmpeg/faster-whisper execution evidence is required
for P3-008 completion?

Options:

1. Mandatory real end-to-end run (real worker, real FFmpeg, real faster-whisper
   `large-v3`, representative fixture) with retained evidence (recommended).
2. Real run required but permitted as a manually executed, environment-dependent
   integration test with full metadata and reproducible commands.
3. Mock-provider verification only.

Recommendation:

Option 1, per P3-008 AC3/4/5/16. Option 2 is an acceptable fallback only with
the limitation explicitly recorded. Option 3 violates the contract.

Impact:

Determines whether Phase 3 completion depends on a real model/worker run and
what evidence is retained.

Blocks:

- P3-008 VERIFIED (and Phase 3 completion).

Does Not Block:

- P3-007 implementation.

Resolution:

HPO-DECIDED on 2026-09-19 — Option 1. A real self-hosted FFmpeg +
faster-whisper end-to-end execution is mandatory before P3-008 can be VERIFIED.
Mocks remain valid for unit/feature coverage but cannot satisfy the final
integration requirement. Use a small controlled representative fixture. Evidence
must demonstrate the real canonical path (private MediaFile → opaque media
resolution → FFmpeg extraction → faster-whisper → normalized provider-neutral
result → Laravel persistence → completed transcription), and must verify actual
FFmpeg invocation, actual faster-whisper worker execution, accurate recording of
the canonical model/config, provider-boundary crossing, text/segment
persistence, ephemeral extracted-audio cleanup, and a path/binary-free queue
payload. For code-switching coverage, use a suitable real fixture or supplement
the real-worker smoke run with deterministic normalized-result fixtures; the
real-worker requirement does not require every scenario to invoke the large
model. No false claim may be made if model/runtime execution is unavailable.
Durable record: ADR-018 in `DECISIONS.md`.

### DECISION-P3-007-CLOSURE-001 — Close P3-007 (Failure / Retry / Recovery Hardening)

Decision ID: DECISION-P3-007-CLOSURE-001

Status: DECIDED

Type: Task Closure / Phase Completion

Originating Task: P3-007

Raised By: Human Product Owner

Priority: HIGH

Question:

Is the independently VERIFIED P3-007 eligible for closure as DONE under the
State-to-Action Contract?

Options:

1. Close P3-007: transition VERIFIED → DONE.
2. Do not close yet (return for further work).
3. Close only part of the task.

Recommendation:

Option 1. The independent review returned VERIFIED with no BLOCKER/HIGH/MEDIUM,
and the sole MEDIUM-1 finding was reconciled to the non-blocking LOW-1.

Resolution:

HPO-CLOSED on 2026-09-19. Closure is based on the completed independent
verification recorded in `reviews/P3-007-independent-review.md`:

- P3-007 = VERIFIED
- No BLOCKER, no HIGH, no MEDIUM
- MEDIUM-1 reconciled to LOW-1 (review artifact §20)
- LOW-1, INFO-1, INFO-2, INFO-3 = non-blocking

Canonical transition applied: VERIFIED → (HPO closure decision) → DONE. History
preserved, not rewritten: implementation → REVIEW → independent review VERIFIED
with MEDIUM-1 → review reconciliation (MEDIUM-1 downgraded to LOW-1) → final
VERIFIED → HPO closure → DONE. Non-blocking findings remain historical and are
not promoted into scope.

This closure is governance/state reconciliation only. It does not authorize any
implementation change, does not execute or authorize P3-008, and does not close
Phase 3. P3-008 remains READY and dependency-gated.

### DECISION-P3-008-CLOSURE-001 — Close P3-008 (Real Phase Integration Verification)

Decision ID: DECISION-P3-008-CLOSURE-001

Status: DECIDED

Type: Task Closure / Phase Gate

Originating Task: P3-008

Raised By: Human Product Owner

Priority: HIGH

Question:

Is the independently VERIFIED P3-008 eligible for closure as DONE under the
State-to-Action Contract?

Options:

1. Close P3-008: transition VERIFIED → DONE.
2. Do not close yet (return for further work).
3. Close only part of the task.

Recommendation:

Option 1. The independent Phase 3 integration review returned VERIFIED with no
BLOCKER/HIGH/MEDIUM, accepted both mandatory gates (B3-06 live Redis; B3-07
real FFmpeg + faster-whisper `large-v3`), and audited the full I-01–I-22 matrix.

Resolution:

HPO-CLOSED on 2026-09-19. Closure is based on the completed independent Phase 3
integration review (`reviews/P3-008-independent-review.md`):

- P3-008 = VERIFIED
- No BLOCKER, no HIGH, no MEDIUM
- B3-06 live Redis gate = PASS (accepted)
- B3-07 real FFmpeg + faster-whisper `large-v3` gate = PASS (accepted)
- I-01 through I-22 audited; no mandatory scenario unresolved or BLOCKED
- Non-blocking: INFO-1, INFO-2, INFO-3, LOW-1 (carried forward)

Canonical transition applied: VERIFIED → (HPO closure decision) → DONE. P3-008's
role was the final Phase 3 integration verification gate. History preserved:
BACKLOG → Batch 3 authorization → READY → dependency gate on P3-007 → final
integration verification execution → REVIEW → independent review VERIFIED → HPO
closure → DONE.

Evidence preserved: `PHASE3-P3-008-INTEGRATION-EVIDENCE.md` (Builder) and
`reviews/P3-008-independent-review.md` (reviewer). The verification harness
`app/Console/Commands/Phase3IntegrationVerification.php` remains classified as
verification tooling.

This closure is governance/state reconciliation only. It does not change any
implementation, test, migration, worker, or harness behavior. It does not close
Phase 3; Phase 3 final closure remains a separate HPO decision.

### DECISION-P3-BATCH3-CLOSURE-001 — Close Phase 3 Batch 3

Decision ID: DECISION-P3-BATCH3-CLOSURE-001

Status: DECIDED

Type: Phase Completion (Batch)

Originating Scope: Phase 3 Batch 3 (P3-007, P3-008)

Raised By: Human Product Owner

Priority: HIGH

Question:

Are all authorized Batch 3 tasks DONE such that Phase 3 Batch 3 may be recorded
as CLOSED?

Options:

1. Close Batch 3 (P3-007 = DONE, P3-008 = DONE).
2. Do not close Batch 3 yet.
3. Close only part of Batch 3.

Resolution:

HPO-CLOSED on 2026-09-19. Both authorized Batch 3 tasks are DONE:

- P3-007 (Failure / Retry / Recovery Hardening) = DONE
  (DECISION-P3-007-CLOSURE-001)
- P3-008 (Real Phase Integration Verification) = DONE
  (DECISION-P3-008-CLOSURE-001)

Phase 3 Batch 3 = CLOSED, following the Batch 1/Batch 2 precedent
(DECISION-P3-BATCH2-CLOSURE-001). Batch 3 closure is distinct from Phase 3
closure: Phase 3 is NOT closed. The Phase 3 completion gate is satisfied and
awaits a separate HPO final closure decision. No implementation change is
authorized by this closure.

### DECISION-PHASE3-CLOSURE-001 — Close Phase 3 (Real Transcription Engine)

Decision ID: DECISION-PHASE3-CLOSURE-001

Status: DECIDED

Type: Phase Completion

Originating Scope: Phase 3 — Real Transcription Engine (ADR-017)

Raised By: Human Product Owner

Priority: HIGH

Question:

Has Phase 3 satisfied its canonical Completion Gate and is it eligible for
final HPO closure?

Options:

1. CLOSE Phase 3.
2. Do not close Phase 3 yet.
3. Close only part of Phase 3.

Recommendation:

Option 1. All Phase 3 tasks and batches are complete, both mandatory
infrastructure gates were accepted by the independent P3-008 review, and no
BLOCKER/HIGH/MEDIUM finding remains.

Resolution:

HPO-CLOSED on 2026-09-19. Final Phase 3 completion gate record:

```text
P3-001 = DONE
P3-002 = DONE
P3-003 = DONE
P3-004 = DONE
P3-005 = DONE
P3-006 = DONE
P3-007 = DONE
P3-008 = DONE

Batch 1 = CLOSED
Batch 2 = CLOSED
Batch 3 = CLOSED

Live Redis = PASS
Real FFmpeg/faster-whisper large-v3 = PASS

Independent final Phase 3 integration verification = VERIFIED
```

Phase 3 = CLOSED. Every task reached DONE through the canonical sequence
(implementation/verification → REVIEW → independent verification → VERIFIED →
HPO closure → DONE). Batch closures remain historically distinct from Phase 3
closure.

Review basis: `reviews/PHASE3-BATCH1-final-hpo-decision-verification.md`
(VERIFIED, no BLOCKER/HIGH), `reviews/PHASE3-BATCH2-independent-review.md`
(VERIFIED; P3-006 HIGH-1 resolved via Correction Cycle 1),
`reviews/P3-007-independent-review.md` (VERIFIED; MEDIUM-1 reconciled to LOW-1),
`reviews/P3-008-independent-review.md` (VERIFIED; mandatory B3-06/B3-07 gates
accepted; I-01–I-22 audited). Evidence:
`PHASE3-P3-008-INTEGRATION-EVIDENCE.md`.

No BLOCKER, HIGH, or MEDIUM finding remains open. Remaining LOW/INFO
observations and deferred historical debt are recorded as non-blocking.

Phase 3 closure grants eligibility for future Phase 4 planning/authorization
only. It does not authorize Phase 4. No implementation change is authorized by
this closure.

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

**Closure (2026-09-18):** The Final HPO-Decision Verification
(`reviews/PHASE3-BATCH1-final-hpo-decision-verification.md`) returned
**VERIFIED** with no BLOCKER or HIGH findings. The HPO accepted and closed
Phase 3 Batch 1. P3-001/P3-002/P3-003 transitioned
**BLOCKED → (HPO closure decision; prerequisite: independent VERIFIED) → DONE**.
Full review history preserved (Cycle 1–4 CHANGES_REQUESTED, Post-Escalation
Independent Review CHANGES_REQUESTED, Final HPO-Decision Verification
VERIFIED). LOW/INFO findings are tracked as non-blocking follow-up debt and
do not reopen Batch 1. Batch 2/3 remain NOT AUTHORIZED.

### DECISION-P3-BATCH2-001 — Authorize Phase 3 Batch 2

Decision ID: DECISION-P3-BATCH2-001

Status: DECIDED

Type: Phase Authorization

Originating Scope: Phase 3 — Real Transcription Engine (ADR-017)

Raised By: Human Product Owner

Priority: HIGH

Question:

Should Phase 3 Batch 2 be authorized for implementation?

Options:

1. Authorize Batch 2: P3-004 (Transcript Persistence),
   P3-005 (Segment Persistence + Atomic Completion),
   P3-006 (Redis Queue Orchestration + Idempotent Delivery).
   Promote P3-004/P3-005/P3-006 to READY.
2. Authorize a subset of Batch 2.
3. Do not authorize Batch 2 yet.

Resolution:

HPO-AUTHORIZED on 2026-09-18. Batch 2 authorized for execution.
P3-004, P3-005, P3-006 promoted to READY and implemented sequentially.
Canonical Phase 3 model remains **large-v3**; turbo remains a non-default /
experimental profile. Batch 3 remains unauthorized: P3-007 and P3-008 remain
BACKLOG. OpenCode performed all Batch 2 implementation first and moved the
three tasks to REVIEW; one independent Claude Batch 2 review is next.
OpenCode did not self-assign VERIFIED or DONE.

Update (2026-09-18, post independent Batch 2 review): the review artifact
`reviews/PHASE3-BATCH2-independent-review.md` returned P3-004 = VERIFIED,
P3-005 = VERIFIED, P3-006 = CHANGES_REQUESTED (HIGH-1: genuine
independent-process concurrency evidence for the `ProcessingJob` CAS claim),
Batch 2 overall = CHANGES_REQUESTED. The HPO authorized a narrow P3-006-only
correction. Correction Cycle 1 added a real two-independent-process SQLite
race test; P3-004/P3-005 behavior, production code, and Batch 3 scope were not
touched. P3-006 returned to REVIEW for the independent re-review, which
subsequently returned VERIFIED (Batch 2 overall = VERIFIED; HIGH-1 RESOLVED);
see DECISION-P3-BATCH2-CLOSURE-001. OpenCode did
not self-assign VERIFIED or DONE.

### DECISION-P3-BATCH2-CLOSURE-001 — Close Phase 3 Batch 2

Decision ID: DECISION-P3-BATCH2-CLOSURE-001

Status: DECIDED

Type: Phase Completion

Originating Scope: Phase 3 — Real Transcription Engine (ADR-017), Batch 2

Raised By: Human Product Owner

Priority: HIGH

Question:

Are the three independently VERIFIED Batch 2 tasks eligible for HPO closure
under the State-to-Action Contract?

Options:

1. Close Batch 2: transition P3-004/P3-005/P3-006 VERIFIED → DONE and record
   Phase 3 Batch 2 = CLOSED.
2. Do not close yet (return a task for further work).
3. Close only part of the batch.

Resolution:

HPO-CLOSED on 2026-09-19. Closure is based on completed independent
verification recorded in `reviews/PHASE3-BATCH2-independent-review.md`:

- P3-004 = VERIFIED
- P3-005 = VERIFIED
- P3-006 = VERIFIED (Correction Cycle 1 re-review, artifact §14.11)
- Batch 2 overall = VERIFIED
- HIGH-1 (P3-006 concurrency-evidence gap) = RESOLVED
- No BLOCKER, no HIGH, no unresolved MEDIUM.

Canonical transition applied: VERIFIED → (HPO closure decision) → DONE for all
three tasks. Phase 3 Batch 2 = CLOSED (authorized scope: P3-004, P3-005,
P3-006 only). This does not close Phase 3 as a whole and does not authorize
Batch 3: P3-007 and P3-008 remain BACKLOG. No implementation change is
authorized by this closure. History is preserved, including the original P3-006
CHANGES_REQUESTED verdict and its correction cycle.

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

## Phase 5–7 Authorization Decisions (2026-09-21)

### DC-01 — Cross-phase browser verification governance

Decision ID: DC-01

Status: DECIDED — HPO 2026-09-21

Type: Architecture / Verification Governance

Originating Scope: Phase 5–7 browser verification

Raised By: Phase 5–7 planning package (`PHASE5-7-DECISION-REGISTER.md`)

Priority: HIGH

Question:

Should Playwright remain the canonical browser verification platform for Phases
5–7, and under what authorization?

Options:

1. Extend ADR-020 to Phase 5/6/7.
2. Create a new cross-phase browser-testing ADR generalizing ADR-020, with
   per-phase authorization retained (recommended).
3. Keep ADR-020 Phase-4-only and re-authorize per phase.

Resolution:

HPO-DECIDED 2026-09-21 as Option 2. Durable record: ADR-021 in `DECISIONS.md`.
Playwright is canonical cross-phase browser verification tooling for P5/P6/P7,
dev/test-only, Chromium baseline, per-phase authorization retained. ADR-020
preserved as the Phase 4 record.

Blocks:

- Browser-dependent P5/P6/P7 task contracts (now unblocked for governance).

Does Not Block:

- Non-browser Phase 5 critical-path work; P1–P4 records.

### DECISION-PHASE5-AUTHORIZATION-001 — Authorize Phase 5 translation implementation

Decision ID: DECISION-PHASE5-AUTHORIZATION-001

Status: DECIDED — HPO 2026-09-21

Type: Phase Authorization

Originating Scope: Phase 5 — Translation

Raised By: Human Product Owner (Controlled Parallel Execution Authorization)

Priority: HIGH

Question:

Should Phase 5 (Translation) be authorized for implementation, and should the
planning-package D5-01..D5-09 candidate decisions be frozen?

Options:

1. Authorize Phase 5 implementation with D5-01..D5-09 frozen (recommended).
2. Continue planning only.
3. Authorize a reduced Phase 5 subset.

Resolution:

HPO-AUTHORIZED 2026-09-21. Phase 5 = AUTHORIZED FOR IMPLEMENTATION. D5-01
through D5-09 frozen. Durable record: ADR-022 in `DECISIONS.md`.

This authorization permits: authoring P5 task contracts; promoting P5 tasks to
READY; implementing Phase 5 within the frozen decisions; and the early-start /
early-hardening allowlists in `PHASE5-7-EXECUTION-CLASSIFICATION.md`.

This authorization does NOT: authorize Phase 6 or Phase 7 generally; permit
self-verification or phase closure; permit modifications to frozen Phase 3/4
contracts; or permit tenancy/actor-vs-owner redesign (DC-02 remains reserved).

Blocks:

- None.

Does Not Block:

- Phase 6 early-start allowlist; Phase 7 early-hardening allowlist.

### D5-01 — Canonical translation unit

Decision ID: D5-01

Status: DECIDED — HPO 2026-09-21

Type: Architecture / Product

Question: What is the canonical translation unit?

Resolution: Segment-aligned hybrid. Persisted translation is segment-aligned;
grouped context may be supplied to the provider without allowing
re-segmentation. Durable record: ADR-022.

### D5-02 — Supported target languages

Decision ID: D5-02

Status: DECIDED — HPO 2026-09-21

Type: Product

Question: Which target languages are supported?

Resolution: `ms`, `en`, `zh`, `ta`. Source may also be `und`. Code-switched
transcripts are valid. Durable record: ADR-022.

### D5-03 — Multiple translations per transcription

Decision ID: D5-03

Status: DECIDED — HPO 2026-09-21

Type: Product / Architecture

Question: May one source transcript hold multiple translations?

Resolution: Yes — multiple persisted translations are allowed per source
transcript/revision according to target-language/lifecycle identity. Durable
record: ADR-022.

### D5-04 — Translation provider strategy

Decision ID: D5-04

Status: DECIDED — HPO 2026-09-21

Type: Architecture

Question: Which provider strategy is canonical?

Resolution: Provider-neutral application boundary with a self-hosted default
translation provider. Durable record: ADR-022.

### D5-05 — Translation source immutability

Decision ID: D5-05

Status: DECIDED — HPO 2026-09-21

Type: Architecture / Data Integrity

Question: May translation mutate the machine transcript?

Resolution: No. Translation is derived data and must not mutate the machine
transcript source. Durable record: ADR-022.

### D5-06 — Translation persistence location

Decision ID: D5-06

Status: DECIDED — HPO 2026-09-21

Type: Architecture / Data Integrity

Question: Where is translation content persisted?

Resolution: Dedicated translation persistence. Do not store translation content
in `transcriptions` or `transcription_segments`. Durable record: ADR-022.

### D5-07 — Translation UX placement

Decision ID: D5-07

Status: DECIDED — HPO 2026-09-21

Type: UX

Question: Where does translation UX live?

Resolution: Within the transcript workspace. Durable record: ADR-022.

### D5-08 — Translated export formats

Decision ID: D5-08

Status: DECIDED — HPO 2026-09-21

Type: Product

Question: Which translated export formats are in Phase 5?

Resolution: TXT, SRT, VTT, DOCX. Durable record: ADR-022.

### D5-09 — Real translation-provider gate

Decision ID: D5-09

Status: DECIDED — HPO 2026-09-21

Type: Verification

Question: Does the Phase 5 gate require the real self-hosted provider/model?

Resolution: Yes. Final Phase 5 integration verification must include the real
authorized self-hosted provider/model path; mocks cannot substitute for the
final real gate. Durable record: ADR-022.

### DECISION-PHASE5-7-PARALLEL-EXECUTION-001 — Adopt controlled parallel execution for Phases 5–7

Decision ID: DECISION-PHASE5-7-PARALLEL-EXECUTION-001

Status: DECIDED — HPO 2026-09-21

Type: Phase Authorization / Governance

Originating Scope: Phases 5–7 execution model

Raised By: Human Product Owner

Priority: HIGH

Question:

Should the strict phase serialization for Phases 5–7 be replaced by a
dependency-driven controlled parallel execution model with early-start/early-
hardening allowlists?

Options:

1. Adopt controlled parallel execution with strict contract/closure boundaries
   (recommended).
2. Keep strict phase serialization.
3. Adopt partial parallelism without allowlists.

Resolution:

HPO-DECIDED 2026-09-21 as Option 1. Durable record: ADR-023 in `DECISIONS.md`;
authorization text in `PHASE5-7-CONTROLLED-PARALLEL-EXECUTION.md`. This also
ratifies the P5-002-after-P5-001 dependency ordering (reviewer GOV-1): an
eligible task may begin once its committed predecessor reaches
`IMPLEMENTED_PENDING_REVIEW`, subject to contract-freeze and reconciliation
rules.

Blocks:

- None.

Does Not Block:

- Phase 5 critical-path implementation; independent verification.

### DECISION-P5-PERSISTENCE-FAILURE-001 � Transient vs deterministic persistence failures

Decision ID: DECISION-P5-PERSISTENCE-FAILURE-001

Status: DECIDED � HPO 2026-09-22 (Phase 5 operational pre-flight)

Type: Architecture / Product

Originating Scope: P5-004C (review residual 1; `reviews/P5-004B-P5-006-independent-review.md` �4.1)

Raised By: Human Product Owner

Priority: MEDIUM

Question:

Should a transient persistence/concurrency failure during translation result
persistence be recoverable, and should deterministic integrity/constraint
failures remain non-retryable?

Options:

1. Map transient persistence/concurrency failures (e.g. a lost SQLite write
   lock) to the retryable `PROCESSING_FAILED`; keep deterministic
   integrity/constraint failures as non-retryable `PERSISTENCE_FAILED`
   (recommended).
2. Make `PERSISTENCE_FAILED` retryable for all persistence failures.
3. Keep all persistence failures non-retryable.

Resolution:

HPO-DECIDED 2026-09-22 as Option 1. Transient persistence/concurrency failures
map to the retryable `PROCESSING_FAILED`; deterministic integrity/constraint
failures remain non-retryable `PERSISTENCE_FAILED`. No taxonomy enum change is
required (smallest contract change). UI/retry behaviour needs no change because
it already gates on `TranslationFailure::isRetryable()`. Implemented in
`App\Jobs\ProcessTranslation` with regression coverage in
`tests/Feature/Translation/TranslationHardeningTest.php`.

Blocks:

- None.

Does Not Block:

- P5-008 real integration gate.

### DECISION-P5-004B-CLOSURE-001 � Close P5-004B (Pre-UI Translation Hardening)

Decision ID: DECISION-P5-004B-CLOSURE-001

Status: DECIDED � HPO 2026-09-22

Type: Phase Completion / Task Closure

Originating Task: P5-004B

Question:

Should the independent VERIFIED verdict for P5-004B be accepted and the task be
closed as DONE?

Resolution:

HPO-ACCEPTED 2026-09-22. The recorded independent corrective re-review
(`reviews/P5-004B-corrective-independent-re-review.md`, VERIFIED, no
BLOCKER/HIGH) is accepted and P5-004B is closed DONE. LOW R-1 closed by P5-004C;
R-3 closed by commit 794802c; R-2 remains non-blocking debt. Historical review
artifacts are preserved unchanged.

Blocks: None.
Does Not Block: P5-008 (separately gated).

### DECISION-P5-006-CLOSURE-001 � Close P5-006 (Translation Workspace UI)

Decision ID: DECISION-P5-006-CLOSURE-001

Status: DECIDED � HPO 2026-09-22

Type: Phase Completion / Task Closure

Originating Task: P5-006

Question:

Should the independent VERIFIED verdict for P5-006 be accepted and the task be
closed as DONE?

Resolution:

HPO-ACCEPTED 2026-09-22. The recorded independent review
(`reviews/P5-004B-P5-006-independent-review.md`, VERIFIED, no blocking
BLOCKER/HIGH/MEDIUM; all seven acceptance criteria satisfied) is accepted and
P5-006 is closed DONE. LOW P6-3/P6-4 and INFO P6-5 remain non-blocking debt.
Historical review artifacts are preserved unchanged.

Blocks: None.
Does Not Block: P5-008 (separately gated).

### DECISION-P5-PENDING-REVIEW-AUTHORIZATION-001 � Authorize fresh independent review/re-review

Decision ID: DECISION-P5-PENDING-REVIEW-AUTHORIZATION-001

Status: DECIDED � HPO 2026-09-22

Type: Phase Authorization / Verification

Originating Scope: Phase 5 pending reviews

Question:

Should fresh independent review/re-review be authorized, without implementation
changes, for the Phase 5 tasks still awaiting independent verification?

Resolution:

HPO-AUTHORIZED 2026-09-22 for: P5-004C, P5-002B, P5-003, P5-004, P5-005,
P5-007. These reviews must be performed in a fresh reviewer context and must not
include implementation changes. Findings are reconciled through the normal
three-cycle corrective limit; a task may be closed only after its review returns
VERIFIED and the HPO accepts the closure.

Blocks: None (review is authorized).
Does Not Block: Safe allowlisted debt; no implementation authorization is
created by this decision.

### DECISION-P5-008-GATE-001 � P5-008 remains gated

Decision ID: DECISION-P5-008-GATE-001

Status: DECIDED � HPO 2026-09-22

Type: Phase Completion / Phase Authorization

Originating Scope: P5-008 (Phase 5 Integration Verification)

Question:

Should P5-008 be promoted to READY at this time?

Resolution:

HPO-DECIDED 2026-09-22: NO. P5-008 remains BACKLOG. It stays gated on
(a) successful reconciliation of the pending Phase 5 reviews
(DECISION-P5-PENDING-REVIEW-AUTHORIZATION-001) and (b) confirmation that the
operational entry prerequisites recorded by P5-004C are satisfied. P5-008
requires a separate explicit HPO READY promotion.

### DECISION-PHASE5-TASK-CLOSURES-001 � Close independently VERIFIED Phase 5 tasks

Decision ID: DECISION-PHASE5-TASK-CLOSURES-001

Status: DECIDED � HPO 2026-09-22

Type: Phase Completion / Task Closure

Originating Scope: Phase 5 tasks with recorded independent VERIFIED verdicts

Resolution:

HPO-ACCEPTED 2026-09-22. The recorded independent VERIFIED verdicts are accepted
and the following tasks are closed DONE: P5-001, P5-001A, P5-002, P5-002A,
P5-002B, P5-003, P5-004, P5-004C, P5-005, P5-007. P5-004B and P5-006 were
already DONE and are unchanged. All historical review artifacts, corrective-cycle
provenance, findings, and decision records are preserved unchanged.

Blocks: None.
Does Not Block: P5-008 (separately authorized).

### DECISION-P5-008-AUTHORIZATION-001 � Promote and authorize P5-008

Decision ID: DECISION-P5-008-AUTHORIZATION-001

Status: DECIDED � HPO 2026-09-22

Type: Phase Authorization / Task Authorization

Originating Task: P5-008 (Phase 5 Integration Verification)

Question:

Should P5-008 be promoted from BACKLOG/GATED to READY and authorized for
execution as the Phase 5 final integration gate?

Resolution:

HPO-AUTHORIZED 2026-09-22. P5-008 is promoted BACKLOG ? READY for execution as
the Phase 5 final integration gate only. This authorization does NOT authorize
Phase 6 or Phase 7 implementation. The gate must use the real authorized
self-hosted translation stack; mocks may not substitute for real-model evidence
(ADR-022 D5-09). P5-008 may reach only IMPLEMENTED_PENDING_REVIEW and requires a
fresh independent reviewer; it may not self-promote to VERIFIED.

Execution outcome: P5-008 is BLOCKED at the real-model prerequisite (B-004) �
the worker venv lacks the pinned translation runtime, no translation model is
cached, and no live Redis server is available. Evidence:
`PHASE5-P5-008-INTEGRATION-EVIDENCE.md`.

Blocks: P5-008 execution until the runtime/model/Redis prerequisites exist.
Does Not Block: Governance/state reconciliation; no Phase 6/7 work.

### DECISION-P5-008-CORRECTIVE-001 — Adopt the proven worker runtime and correct P5-008

Decision ID: DECISION-P5-008-CORRECTIVE-001

Status: DECIDED — HPO 2026-09-22

Type: Architecture / Runtime / Verification Corrective

Originating Task: P5-008 (Phase 5 Integration Verification)

Question:

Following the independent P5-008 review (`CHANGES_REQUESTED`, BLOCKER: the real
gate ran on an undeclared/unauthorized runtime set), which runtime is canonical,
and what corrective is required before P5-008 may be re-reviewed?

Resolution:

HPO-DECIDED 2026-09-22. Adopt the real runtime set already proven to execute the
canonical NLLB model: `transformers==5.17.0`, `torch==2.14.0`,
`sentencepiece==0.2.2`. This supersedes the previous Phase 5 worker pins
(`transformers==4.57.6`, `torch==2.9.1`, `sentencepiece==0.2.1`). The canonical
model identity is unchanged (`facebook/nllb-200-distilled-600M`). Durable record:
ADR-024 in `DECISIONS.md`.

Rationale: the previous exact pins were never successfully real-model gated; the
new exact set has been demonstrated to load and execute the canonical model under
the current Python/runtime environment. Phase 5 standardizes on the proven
runtime rather than regress to an unverified dependency set.

Corrective required before re-review (P5-008 remains `CHANGES_REQUESTED`):

1. Reconcile the committed worker dependency declarations and all documentation,
   evidence, runtime tables, and guard tests to the single canonical runtime.
2. Re-provision the canonical model into a clean cache location; verify artifacts
   and inference; document the reproducible provisioning procedure.
3. Commit a reproducible P5-008 real-gate harness (P3-008/P4-006 precedent).
4. Execute one true browser-to-real-model end-to-end flow (no worker double).
5. Re-run the canonical P5-008 real gate and retain tracked evidence.
6. Record the config-derived persisted model identity as retained INFO debt.
7. Record the `und → eng_Latn` worker fallback assumption.

On completion, P5-008 returns to `IMPLEMENTED_PENDING_REVIEW` and requires a fresh
independent review. This decision does not mark P5-008 VERIFIED, does not close
Phase 5, and does not authorize Phase 6 or Phase 7.

Blocks: None (corrective authorized).

Does Not Block: Governance/state reconciliation; no Phase 6/7 work.

### DECISION-P5-008-CLOSURE-001 — Accept the fresh independent P5-008 verdict and close P5-008 DONE

Decision ID: DECISION-P5-008-CLOSURE-001

Status: DECIDED — HPO 2026-09-23

Type: Phase Completion / Task Closure

Originating Task: P5-008 (Phase 5 Integration Verification)

Question:

Should the fresh independent P5-008 verdict (`VERIFIED`) be accepted and P5-008 be
closed DONE?

Resolution:

HPO-ACCEPTED 2026-09-23. The fresh independent P5-008 review
(`reviews/P5-008-independent-review.md`) returned **VERIFIED** with no BLOCKER,
HIGH, or MEDIUM findings. The reviewer independently confirmed canonical runtime
provenance; exact dependency pins; a valid canonical NLLB cache at the pinned
revision; real non-empty model inference; the committed reproducible P5-008
harness; a real Chromium → Laravel → Redis → worker → NLLB → persistence →
browser E2E flow; all required targets `ms`/`en`/`zh`/`ta`; code-switched source
handling; alignment/timestamp/source-language preservation; source immutability;
ownership isolation; translated TXT/SRT/VTT/DOCX; queue/runtime safety; and the
separation of real-model evidence from contract/concurrency evidence.

P5-008 is transitioned `VERIFIED → DONE`. The full independent review artifact and
all corrective provenance (`DECISION-P5-008-CORRECTIVE-001`; ADR-024;
`PHASE5-P5-008-INTEGRATION-EVIDENCE.md`) are preserved unchanged. This decision
does not, by itself, close Phase 5.

Blocks: None.

Does Not Block: `DECISION-PHASE5-CLOSURE-001` (separately recorded).

### DECISION-PHASE5-CLOSURE-001 — Close Phase 5 (Translation)

Decision ID: DECISION-PHASE5-CLOSURE-001

Status: DECIDED — HPO 2026-09-23

Type: Phase Completion

Originating Scope: Phase 5 — Translation (ADR-022)

Question:

Should Phase 5 be declared CLOSED?

Resolution:

HPO-DECIDED 2026-09-23: **Phase 5 = CLOSED.**

Basis:

- all required P5 tasks (P5-001, P5-001A, P5-002, P5-002A, P5-002B, P5-003,
  P5-004, P5-004B, P5-004C, P5-005, P5-006, P5-007, P5-008) = DONE;
- P5-008 independently VERIFIED (`reviews/P5-008-independent-review.md`);
- real self-hosted canonical model path demonstrated (`facebook/nllb-200-distilled-600M`);
- canonical runtime pinned and reproducible (`transformers==5.17.0`,
  `torch==2.14.0`, `sentencepiece==0.2.2`; ADR-024);
- real Redis-backed queue execution demonstrated;
- committed integration harness (`verification/p5-008-real-gate.mjs`;
  `app/Console/Commands/Phase5IntegrationVerification.php`);
- real browser-to-real-model E2E demonstrated;
- source transcript immutability and ownership isolation demonstrated;
- required multilingual/code-switch behavior demonstrated (`ms`/`en`/`zh`/`ta`;
  code-switched source);
- translated TXT/SRT/VTT/DOCX demonstrated;
- full regression/static verification passing (worker 46; translation
  193/733; full PHP 626/625; Pint clean; PHPStan 0);
- no unresolved BLOCKER/HIGH/MEDIUM findings.

No historical finding or corrective cycle is rewritten. Phase 5 closure does not
authorize general Phase 6 or Phase 7 implementation; those remain governed by
ADR-023 and `PHASE5-7-EXECUTION-CLASSIFICATION.md`.

Blocks: None.

Does Not Block: Phase 6/7 eligibility reconstruction and controlled-parallel
early-start/early-hardening work already permitted by ADR-023; general Phase 6/7
still requires separate HPO authorization.

### DECISION-PHASE5-DEBT-CARRYFORWARD-001 — Carry forward Phase 5 LOW/INFO debt as non-blocking

Decision ID: DECISION-PHASE5-DEBT-CARRYFORWARD-001

Status: DECIDED — HPO 2026-09-23

Type: Debt / Risk Acceptance

Originating Scope: Phase 5 residual LOW/INFO findings

Resolution:

HPO-DECIDED 2026-09-23: the remaining LOW/INFO findings are preserved as
non-blocking deferred debt and Phase 5 is **not** reopened to clean them. At
minimum:

- redis-payload leakage check is heuristic rather than structural;
- command-level export isolation evidence is weaker than HTTP-level evidence;
- persisted model identity remains config-derived;
- `und → eng_Latn` remains the documented fallback;
- Laravel and worker default model labels differ unless explicitly configured;
- non-translation worker dependency ranges remain outside the Phase 5 canonical
  runtime trio;
- unrelated PHPUnit warnings remain informational;
- B-006 / `D:` corruption hazard remains documented operational evidence.

Additionally retained from Phase 5 reviews: P5-002A MEDIUM (structurally
prevented by the unique index) and the P5-002B/P5-003/P5-004/P5-004B/P5-004C/
P5-005/P5-006/P5-007 LOW items recorded in `PHASE5-CLOSURE-REPORT.md`. Phase 7
owns productionization of these items.

Blocks: None.

Does Not Block: Phase 6/7 eligibility reconstruction; no Phase 6/7
implementation is authorized by this decision.

### DECISION-PHASE6-AUTHORIZATION-001 — Authorize Phase 6 contract authoring and implementation

Decision ID: DECISION-PHASE6-AUTHORIZATION-001

Status: DECIDED — HPO 2026-09-23

Type: Phase Authorization

Originating Scope: Phase 6 — Advanced Transcript UX (ADR-019 boundary)

Question:

Should Phase 6 be authorized to proceed through contract authoring and
implementation?

Resolution:

HPO-AUTHORIZED 2026-09-23. Phase 6 may proceed through contract authoring and
implementation according to `PHASE6-PLANNING.md`,
`PHASE5-7-EXECUTION-CLASSIFICATION.md`, `PHASE6-7-ELIGIBILITY-MATRIX.md`, the
dependency graph, the adopted D6/DC decisions, and the Autonomous Completion
Controller.

Constraints:

- Do **not** treat all P6 candidates as immediately READY; there is no blanket
  READY state.
- Begin with canonical task-contract authoring for P6-001
  (Advanced Transcript Editing Domain / Contract Foundation).
- Promote an implementation task to READY only after its canonical contract
  exists and its dependencies are reconciled.
- Bounded batches (max 3 dependent tasks deep, or max 5 genuinely independent
  tasks, or one major integration boundary); internal pre-review; task-scoped
  commits; fresh independent review before VERIFIED/DONE.
- D6-08 (speaker labels/annotations/bookmarks) and D6-09 (waveform/timeline) are
  DEFERRED from required Phase 6 scope and must not be implemented without
  separate authorization.
- Phase 6 may not close automatically; Phase 7 remains NOT GENERALLY AUTHORIZED;
  P7-012 remains FINAL_GATE_ONLY.

Blocks: None (contract authoring/implementation authorized).

Does Not Block: Phase 7 general work (not authorized); allowlisted P7-005 only
(separately authorized).

### DECISION-PHASE6-OWNER-DECISIONS-001 — Adopt Phase 6 owner decisions (D6-01..D6-09, DC-01)

Decision ID: DECISION-PHASE6-OWNER-DECISIONS-001

Status: DECIDED — HPO 2026-09-23

Type: Owner Decisions / Architecture

Originating Scope: Phase 6 (Advanced Transcript UX) and cross-phase browser
verification

Resolution:

HPO-DECIDED 2026-09-23. The following decisions are adopted (durable record:
ADR-025 in `DECISIONS.md`):

- **D6-01 (editing model):** preserve the completed machine transcription as an
  immutable source layer; introduce an explicit editable revision layer for user
  edits; never overwrite the machine transcript in place.
- **D6-02 (undo/redo/versions):** adopt persisted revision/version semantics;
  undo/redo derives from durable revision history, not browser-only ephemeral
  state; history must survive reload. Exact storage mechanics are defined by the
  P6-001/P6-002 contracts.
- **D6-03 (timestamp editing):** allow explicit timestamp editing subject to
  canonical timing invariants: non-negative/valid timestamps; start precedes end;
  ordering/overlap rules explicitly defined; edits never silently mutate the
  immutable machine source.
- **D6-04 (split/merge + translation):** allow split/merge within the editable
  revision model; a structural edit changing segment identity or textual source
  semantics must not silently preserve a translation as current; translation
  becomes explicitly stale/invalidated per the later P6 translation-invalidation
  contract; never silently remap translation content across changed segment
  structure.
- **D6-05 (revision history surface):** provide a user-visible revision/history
  surface; initial scope may be lightweight but must let users understand
  revisions exist and identify the active/current revision.
- **D6-06 (comparison):** provide comparison inside the transcript workspace as
  an explicit source-versus-translation/revision surface; no separate product
  module; exact visual layout is an implementation detail unless a later contract
  requires otherwise.
- **D6-07 (navigation/search/filter):** enhance navigation/search/filtering
  within the transcript workspace; do not create a standalone search product.
- **D6-08 (speaker labels/annotations/bookmarks):** DEFER from Phase 6 required
  scope; do not implement without separate authorization.
- **D6-09 (waveform/timeline):** DEFER from Phase 6 required scope; do not
  implement without separate authorization.
- **DC-01 (cross-phase browser verification):** adopt the Phase 5 cross-phase
  browser-verification governance as the Phase 6/7 default. Browser verification
  is required when browser behavior is material to acceptance criteria. Browser
  evidence does not replace lower-level concurrency, persistence, authorization,
  queue/provider integration, or real-service evidence. A browser test double
  must never be represented as proof of a real backend path.

Blocks: None.

Does Not Block: P6-006 early-start (separately authorized); P7-005
(separately authorized).

### DECISION-P6-006-AUTHORIZATION-001 — Authorize P6-006 early-start exception

Decision ID: DECISION-P6-006-AUTHORIZATION-001

Status: DECIDED — HPO 2026-09-23

Type: Task Authorization / Early-Start Exception (ADR-023)

Originating Task: P6-006 (Advanced Navigation + Search/Filter)

Resolution:

HPO-AUTHORIZED 2026-09-23. P6-006 is separately authorized for contract
authoring and implementation because the eligibility matrix identifies it as
independent of Phase 5 translation semantics. P6-006 may proceed in parallel
with early Phase 6 contract work provided its task contract confirms:

- no translation invalidation semantics;
- no revision ownership assumptions beyond frozen P6 contracts;
- no dependency on unfinished P6 comparison / split-merge behavior.

If that independence is contradicted during contract authoring, STOP and
reclassify rather than forcing early execution.

Blocks: None.

Does Not Block: P6-001 contract authoring.

### DECISION-P7-005-AUTHORIZATION-001 — Authorize P7-005 early-hardening only

Decision ID: DECISION-P7-005-AUTHORIZATION-001

Status: DECIDED — HPO 2026-09-23

Type: Phase Authorization / Early-Hardening

Originating Task: P7-005 (Observability Foundation)

Resolution:

HPO-AUTHORIZED 2026-09-23. Only P7-005 is authorized for early contract
authoring and implementation. P7-003, P7-008, P7-010, and all other Phase 7
implementation remain NOT authorized.

P7-005 must remain product-semantic-neutral and may include structured
application logging; correlation/request/job identifiers;
translation/transcription job observability; queue/runtime telemetry;
health/operational diagnostics; error classification/visibility; and
documentation/runbook conventions. It must not redesign datastore architecture,
storage architecture, tenancy, authorization, provider contracts, or retention
semantics. If any of those become necessary, STOP at a decision boundary.

Blocks: None.

Does Not Block: Phase 6 mainline; Phase 7 general work remains not authorized.

### DECISION-P6-006-CLOSURE-001 — Accept the fresh independent P6-006 re-review verdict and close P6-006 DONE

Decision ID: DECISION-P6-006-CLOSURE-001

Status: DECIDED — HPO 2026-09-23

Type: Task Closure

Originating Task: P6-006 (Advanced Navigation + Search/Filter)

Question:

Should the fresh independent corrective re-review verdict (`VERIFIED`) be
accepted and P6-006 be closed DONE?

Resolution:

HPO-ACCEPTED 2026-09-23. The fresh independent corrective re-review
(`reviews/P6-006-P7-005-corrective-independent-re-review.md`) returned
**VERIFIED** with no BLOCKER, HIGH, or MEDIUM findings. The reviewer
independently confirmed: the reserved Phase 4 selector hooks remain unique
(rows use dedicated `data-filter-language`/`data-nav-seconds`); overlapping and
zero-length segment navigation no longer traps or skips; the language-filter
count and no-media feedback are correct and accessibility-visible; the committed
P6-006 browser harness is independently reproducible (7/7, plus P4-003/P4-004/
P4-006 regression runs); and P6-006 remains independent of
translation/revision/split-merge semantics.

P6-006 is transitioned `VERIFIED → DONE`. All original review findings, the
corrective artifacts, and the independent re-review evidence are preserved
unchanged. This decision does not close Phase 6.

Blocks: None.

Does Not Block: `DECISION-PHASE6-7-DEBT-CARRYFORWARD-001`; P6-001 implementation.

### DECISION-P7-005-CLOSURE-001 — Accept the fresh independent P7-005 re-review verdict and close P7-005 DONE

Decision ID: DECISION-P7-005-CLOSURE-001

Status: DECIDED — HPO 2026-09-23

Type: Task Closure

Originating Task: P7-005 (Observability Foundation)

Question:

Should the fresh independent corrective re-review verdict (`VERIFIED`) be
accepted and P7-005 be closed DONE?

Resolution:

HPO-ACCEPTED 2026-09-23. The fresh independent corrective re-review
(`reviews/P6-006-P7-005-corrective-independent-re-review.md`) returned
**VERIFIED** with no BLOCKER, HIGH, or MEDIUM findings. The reviewer
independently confirmed: real emitted failure records carry the required
`LogContext` fields; observability enrichment is non-fatal to the underlying
job; HTTP (`http_request_id`), worker-transport (`request_id`), and queue
(`queue_job_id`) correlation identifiers have distinct semantics; `X-Request-Id`
covers matched routes, 404, 419, and `/up`; and P7-005 remains within its
product-semantic-neutral early-hardening authorization.

P7-005 is transitioned `VERIFIED → DONE`. All original review findings, the
corrective artifacts, and the independent re-review evidence are preserved
unchanged. This decision does not authorize any other Phase 7 task.

Blocks: None.

Does Not Block: `DECISION-PHASE6-7-DEBT-CARRYFORWARD-001`; Phase 7 remains not
generally authorized.

### DECISION-PHASE6-7-DEBT-CARRYFORWARD-001 — Carry forward P6-006/P7-005 LOW/INFO debt as non-blocking

Decision ID: DECISION-PHASE6-7-DEBT-CARRYFORWARD-001

Status: DECIDED — HPO 2026-09-23

Type: Debt / Risk Acceptance

Originating Scope: P6-006 and P7-005 residual LOW/INFO findings

Resolution:

HPO-DECIDED 2026-09-23: the following residual items are recorded as
non-blocking deferred debt. Neither P6-006 nor P7-005 is reopened to clean them.

1. **Pre-existing/order-dependent full-suite flaky tests** (e.g.
   `MediaManagementTest` file-size/Flysystem-on-Windows; cross-test state leakage
   observed by the reviewer). Not introduced by P6-006/P7-005. A dedicated
   suite-hygiene follow-up is recommended.
2. **Missing ADR-017 `device` / compute-device observability.** `device` is
   absent everywhere in the app, not only in `LogContext`; P7-005's contract
   requires only the minimum fields "where available". Carry forward to whichever
   task next touches provider/worker compute metadata.
3. **Known Phase 4 playback timing flake** (`V4-08`/P4-003 audio `currentTime`
   remains `0` after the fixed wait). Environmental; the H-1 selector
   regression surface is unaffected. P7-010 owns browser-flake elimination.
4. **Pre-existing `showRenameModal` console error** on the transcript workspace.
   Present before P6-006; own a separate correction only if separately
   authorized.

Phase 6/7 ownership of these items is productionization/hardening work; they do
not gate P6-006/P7-005 closure or Phase 6 mainline.

Blocks: None.

Does Not Block: P6-001 implementation; P6-002 contract authoring.

### DECISION-P6-001-CLOSURE-001 — Accept the fresh independent P6-001 corrective re-review and close P6-001 DONE

Decision ID: DECISION-P6-001-CLOSURE-001

Status: DECIDED — HPO 2026-09-23

Type: Task Closure

Originating Task: P6-001 (Phase 6 Editing Domain / Contract Foundation)

Question:

Should the fresh independent corrective re-review verdict (`VERIFIED`) be
accepted, P6-001 be closed DONE, and its final domain semantics be recorded as
frozen inputs for downstream Phase 6 work?

Resolution:

HPO-ACCEPTED 2026-09-23. The fresh independent corrective re-review
(`reviews/P6-001-corrective-independent-re-review.md`) returned **VERIFIED** with
no remaining BLOCKER/HIGH/MEDIUM. All prior findings were independently confirmed
resolved:

- BLOCKER — undo→branch version collision: resolved (transcription-scoped
  monotonic version; append-time re-validation; `RevisionConflictException`);
- HIGH — undefined redo target under branching: resolved (`redoTargetFor`
  unique-child rule; branch-point redo unavailable; old branches durable);
- MEDIUM — mixed-category staleness precedence: resolved
  (`SegmentStructureChanged > TimingChanged > SourceTextChanged`);
- LOW — test-support PHPStan findings: resolved.

The reviewer independently reproduced branch-after-undo version uniqueness,
multiple-undo / multiple-branch scenarios, redo determinism at branch points,
mixed invalidation precedence, `tests/Unit/Editing`, the full suite, Pint,
PHPStan (default and extended scope), and scope discipline. One non-blocking INFO
(unrelated pre-existing flaky test in the full suite) is preserved.

P6-001 is transitioned `VERIFIED → DONE`. All historical artifacts are preserved
unchanged: `reviews/P6-001-independent-review.md` (original CHANGES_REQUESTED),
`reviews/pre-review/P6-001-corrective-pre-review.md` (corrective handoff), and
`reviews/P6-001-corrective-independent-re-review.md` (VERIFIED).

**Recorded frozen P6-001 domain semantics** (`PHASE6-EDITING-DOMAIN-CONTRACT.md`
§0), as frozen inputs for downstream Phase 6 work: immutable machine source +
append-only editable revisions + one active pointer (`null` = machine source) +
durable revision graph/history; transcription-scoped monotonic `version`
independent of ancestry with transactional `(transcription_id, version)`
uniqueness and `parent_revision_id` ancestry; stale-base failure with no silent
merge and separate active-pointer-CAS / version-uniqueness invariants surfacing
domain conflict (not raw DB errors); durable active-pointer undo/redo with
branch-after-undo, durable old descendants, prior-redo-path invalidation, and
`redoTargetFor()` valid only for a unique deterministic child; timing invariants
(finite/non-negative, `start <= end`, ms precision, overlap legal, zero-length
legal-but-never-active, unique/contiguous positions, no cross-segment
monotonicity, lowest-position active resolution, revision timing never mutates
machine timing); active-revision segment identity/position navigation (not
machine `segment_index`); translation invalidation precedence
`SegmentStructureChanged > TimingChanged > SourceTextChanged` with all relevant
edit kinds invalidating. Downstream tasks consume these without redefining them.

Blocks: None.

Does Not Block: `DECISION-P6-002-READY-001`; Phase 6 mainline.

### DECISION-P6-002-READY-001 — Promote P6-002 to READY and authorize implementation

Decision ID: DECISION-P6-002-READY-001

Status: DECIDED — HPO 2026-09-23

Type: Readiness Promotion / Implementation Authorization

Originating Task: P6-002 (Revision Persistence / Version History)

Question:

May P6-002 proceed from its authored-and-reconciled contract to implementation?

Resolution:

HPO-PROMOTED 2026-09-23. P6-001 is DONE and its semantics are frozen
(`DECISION-P6-001-CLOSURE-001`); the P6-002 contract was authored and reconciled
against the corrected P6-001 version-allocation / redo semantics. P6-002 is
promoted to **READY** and authorized to implement within its canonical contract.

Implementation must consume the frozen P6-001 semantics without redefining them
and must safely persist `transcript_revisions`, `transcript_revision_segments`,
`transcriptions.active_revision_id`, transcription-scoped monotonic versions,
parent ancestry, the active pointer, immutable historical revisions, revision
segment identity/position, and durable history — with `(transcription_id,
version)` uniqueness enforced transactionally and DB uniqueness/locking
conflicts translated into the canonical domain conflict.

Scope restriction: P6-003, P6-004, P6-005, P6-007, P6-008, P6-009, and any new
Phase 7 task remain not started; split/merge UI and translation-staleness
persistence are out of P6-002 scope.

Blocks: None.

Does Not Block: Other Phase 6 tasks (each still requires its own contract and an
HPO READY promotion); Phase 7 remains not generally authorized.

### DECISION-P6-002-CLOSURE-001 — Accept the fresh independent P6-002 corrective re-review and close P6-002 DONE

Decision ID: DECISION-P6-002-CLOSURE-001

Status: DECIDED — HPO 2026-09-23

Type: Task Closure

Originating Task: P6-002 (Revision Persistence / Version History)

Question:

Should the fresh independent corrective re-review verdict (`VERIFIED`) be
accepted and P6-002 be closed DONE?

Resolution:

HPO-ACCEPTED 2026-09-23. The fresh independent corrective re-review returned
**VERIFIED** with no BLOCKER/HIGH/MEDIUM/LOW/INFO findings remaining. The reviewer
independently confirmed:

- the original strict-ancestor undo defect is resolved;
- strict ancestry is enforced through `RevisionService::undo()`;
- sibling, cousin, descendant, abandoned-branch, unrelated, cross-transcription,
  and unknown targets are rejected;
- valid parent / grandparent / deep-ancestor undo succeeds;
- active-pointer CAS still prevents stale writes;
- rejected calls leave persistence unchanged (active pointer and rows intact);
- redo semantics remain correct (deterministic unique child; no arbitrary branch
  selection);
- Eloquent and in-memory behavior remain aligned;
- ownership and isolation remain correct;
- version allocation, transactional rollback, machine-source immutability, schema
  constraints, and race handling remain intact.

P6-002 is transitioned `VERIFIED → DONE`. All historical artifacts are preserved
unchanged: `reviews/P6-002-independent-review.md` (original CHANGES_REQUESTED),
`reviews/pre-review/P6-002-pre-review.md` (original handoff), and
`reviews/pre-review/P6-002-corrective-pre-review.md` (corrective handoff). The
original review's single MEDIUM finding and its corrective provenance are retained,
not rewritten.

Provenance note: the HPO-accepted corrective re-review verdict is recorded here.
The reviewer-owned artifact `reviews/P6-002-corrective-independent-re-review.md`
was not present in the working tree at reconciliation time; it is the expected
durable evidence for the VERIFIED verdict and must be retained/added so the
verdict remains independently reproducible. This note records a record-completeness
gap only; it does not alter the accepted verdict or reopen P6-002.

Frozen downstream foundation: P6-001 (editing domain contract,
`PHASE6-EDITING-DOMAIN-CONTRACT.md`) and P6-002 (durable revision persistence) are
now a frozen downstream input. Downstream Phase 6 tasks consume, and must not
redefine: the immutable machine source + append-only editable revisions + one
active pointer + durable graph/history; branching after undo; the
`transcript_revisions` / `transcript_revision_segments` /
`transcriptions.active_revision_id` schema; transcription-scoped monotonic version
with unique `(transcription_id, version)`; durable `parent_revision_id` ancestry;
active-pointer CAS; transactional conflict translation; strict-ancestor-only undo;
deterministic unique-child redo; durable old branches; overlap-legal /
zero-length-legal-but-never-active timing; active-revision identity/position
navigation with immutable machine timing; and translation-invalidation precedence
`SegmentStructureChanged > TimingChanged > SourceTextChanged`.

P6-002 closure does not close Phase 6. No later Phase 6/7 task is promoted or
authorized by this decision.

Blocks: None.

Does Not Block: P6-003/P6-004/P6-005/P6-007/P6-008 (each still requires its own
contract and an explicit HPO READY promotion); P6-009 (FINAL_GATE_ONLY); Phase 7
remains not generally authorized.

### DECISION-P6-007-SCOPE-001 — P6-007 source/translation comparison is presentation-only

Decision ID: DECISION-P6-007-SCOPE-001

Status: DECIDED — HPO 2026-09-23

Type: Product / Architecture (Phase 6 task scope)

Originating Task: P6-007 (Source / Translation Comparison)

Question:

Does P6-007 own translation-invalidation persistence, or is its required Phase 6
scope presentation-only?

Resolution:

HPO-DECIDED 2026-09-23 — **P6-007 Source/Translation Comparison is
presentation-only for its required Phase 6 scope.**

P6-007 may display:

- the immutable machine source;
- the active editable revision;
- persisted translation content already available from Phase 5;
- source / revision / translation comparison relationships.

P6-007 must **not** own or persist translation invalidation. Translation-staleness
display is **optional**. If staleness state is not yet available because P6-005
has not implemented it, P6-007 must degrade gracefully and must **not** invent or
infer stale/current state. If a later P6-005 staleness marker exists, P6-007 may
consume it through an explicit contract without owning it.

This decision preserves P6-007 independence from P6-005.

Constraints (non-scope for P6-007): P6-007 must not mutate the machine source,
mutate revision history, persist invalidation, silently remap translation content,
or own the translation lifecycle. It must not silently present a translation as
aligned with edited revision text when the persisted translation belongs to a
different source/revision identity.

Blocks: None.

Does Not Block: P6-003 contract authoring/implementation; P6-005 (still owns
translation-invalidation persistence); P6-009 (FINAL_GATE_ONLY).

### DECISION-P6-003-P6-007-READY-BATCH-001 � HPO promotes P6-003 and P6-007 to READY

Decision ID: DECISION-P6-003-P6-007-READY-BATCH-001

Status: DECIDED - HPO 2026-09-23

Type: Governance / Phase 6 task promotion

Originating Tasks: P6-003 (Text Editing + Undo/Redo); P6-007 (Source / Translation Comparison)

Question:

Are P6-003 and P6-007 promoted to READY and authorized for implementation?

Resolution:

HPO-DECIDED 2026-09-23 - **P6-003 and P6-007 are each promoted to READY and
authorized for implementation**, since each canonical contract exists and its
binding dependencies are satisfied:

- P6-003: `DECISION-P6-003-READY-001`; contract
  `tasks/P6-003-text-editing-undo-redo.md`; P6-001 DONE, P6-002 DONE, Phase 4
  primitives, P6-006 DONE, DC-01.
- P6-007: `DECISION-P6-007-READY-001`; contract
  `tasks/P6-007-source-translation-comparison.md`; Phase 5 CLOSED, P6-001/P6-002
  DONE, P6-006 DONE, `DECISION-P6-007-SCOPE-001` (presentation-only), DC-01.

Execution order: implement P6-003 first (tests, browser evidence, pre-review),
then implement P6-007 against the stabilized workspace. If parallel execution is
attempted instead, explicit file/component ownership of
`resources/views/transcriptions/show.blade.php` must be established first; the
two tasks must not independently modify the same workspace surface concurrently.

P6-003 implements only its canonical scope, preserving immutable machine source,
append-only revisions, expected-base concurrency, no silent merge, strict-ancestor
undo, unique-child redo, branch-after-undo semantics, P6-006 navigation/filter
behavior, and reserved Phase 4 hooks. Textual edits map to `EditKind::Textual` ->
`SourceTextChanged`; P6-003 does not persist translation staleness (P6-005 owns
that). After implementation P6-003 = `IMPLEMENTED_PENDING_REVIEW`; it must not be
self-verified.

P6-007 remains presentation-only. It may display machine source, active revision,
persisted Phase 5 translation, and comparison relationships, but must not mutate
machine source or revision history, persist staleness, remap translations
silently, or own the translation lifecycle. Persisted Phase 5 translations align
to machine `segment_index`, not revision segment identity, so edited-revision vs
translation comparisons must state that the translation belongs to machine source
unless an explicit persisted revision linkage exists, and structurally changed
revisions must never be mapped by index. Where no persisted staleness marker
exists, only factual state is shown. After implementation P6-007 =
`IMPLEMENTED_PENDING_REVIEW`; it must not be self-verified.

Record completeness (retained): `reviews/P6-002-corrective-independent-re-review.md`
is still missing; P6-002 is **not** reopened and no artifact may be fabricated or
reconstructed from summaries. The accepted reviewer should add the actual artifact
when available.

Blocks: None.

Does Not Block: P6-004/P6-005/P6-008/P6-009 (each still requires its own contract
and an explicit HPO READY promotion; P6-005 additionally waits on P6-004 DONE;
P6-009 is FINAL_GATE_ONLY); Phase 7 remains not generally authorized.

### DECISION-P6-003-CLOSURE-001 — Accept the P6-003 independent review verdict and close P6-003 DONE

Decision ID: DECISION-P6-003-CLOSURE-001

Status: DECIDED — HPO 2026-09-24

Type: Task Closure

Originating Task: P6-003 (Text Editing + Undo/Redo)

Question:

Should the fresh independent review verdict (`VERIFIED`) be accepted and P6-003
be closed DONE?

Resolution:

HPO-ACCEPTED 2026-09-24. The fresh independent review
(`reviews/P6-003-P6-007-independent-review.md`) returned **VERIFIED** for P6-003
with no BLOCKER/HIGH/MEDIUM findings remaining. The reviewer independently
confirmed and reproduced:

- append-only text editing that preserves segment identity, position, timing, and
  language and never mutates the immutable machine source in place;
- expected-base concurrency re-checked at the HTTP boundary, with stale bases
  rejected as the canonical conflict and persistence left unchanged;
- strict-ancestor undo and unique-child redo, including branch-after-undo with
  durable old branches and correctly disabled redo at a branch point;
- ownership/isolation (non-owner edit/undo/redo denied; cross-transcription
  revision ids rejected);
- reload durability of edits, active revision, and undo/redo state;
- real-browser (DC-01) behavior, with the committed Playwright suite reproduced
  end-to-end from a freshly seeded database;
- no translation-staleness persistence (`translations.stale_at` /
  `staleness_reason` absent) and no P6-004/P6-005/P6-008 scope leakage.

The reviewer also independently reproduced the full PHP suite (797 tests, 796
passed, 1 skipped, 0 failures at review time), Pint clean, and PHPStan 0 errors.

P6-003 is transitioned `VERIFIED → DONE`. The independent review artifact
`reviews/P6-003-P6-007-independent-review.md` is preserved unchanged.

Phase consequence:

P6-003 closure does not close Phase 6. The same review returned P6-007 =
CHANGES_REQUESTED for one MEDIUM presentation-truthfulness finding; P6-007 was
corrected (the per-row "edited after the translation was produced" note is now
gated on a persisted translation existing for that row) and remains
`IMPLEMENTED_PENDING_REVIEW`, requiring a fresh independent corrective re-review
before HPO closure. P6-004/P6-005/P6-008/P6-009 remain not started; each still
requires a canonical contract and an explicit HPO READY promotion; P6-009 is
FINAL_GATE_ONLY. No additional Phase 7 task is authorized.

Record completeness (retained): `reviews/P6-002-corrective-independent-re-review.md`
is still missing; P6-002 is **not** reopened and no artifact may be fabricated or
reconstructed from summaries. The accepted reviewer should add the actual artifact
when available.

Blocks: None.

Does Not Block: P6-004/P6-005/P6-007/P6-008/P6-009 (P6-007 awaits a fresh
independent corrective re-review; the others each require a canonical contract and
an explicit HPO READY promotion; P6-005 additionally waits on P6-004 DONE; P6-009
is FINAL_GATE_ONLY); Phase 7 remains not generally authorized.

### DECISION-P6-007-CLOSURE-001 - Accept the P6-007 corrective re-review verdict and close P6-007 DONE

Decision ID: DECISION-P6-007-CLOSURE-001

Status: DECIDED - HPO 2026-09-24

Type: Task Closure

Originating Task: P6-007 (Source / Translation Comparison)

Question:

Should the fresh independent corrective re-review verdict (`VERIFIED`) be accepted
and P6-007 be closed DONE?

Resolution:

HPO-ACCEPTED 2026-09-24. The fresh independent corrective re-review of P6-007
confirmed the original MEDIUM presentation-truthfulness finding is closed and
returned **VERIFIED** with no remaining BLOCKER/HIGH/MEDIUM/LOW/INFO finding.
Independently confirmed:

- edited revision + no translation shows only the factual no-translation state;
- no false "Edited after the translation was produced" claim remains;
- edited revision + persisted translation preserves machine-source provenance;
- structurally incompatible revisions are never silently index-remapped;
- no staleness is inferred or persisted (no `translations.stale_at` /
  `staleness_reason` read or written);
- P6-007 remains presentation-only (read-only; no database writes);
- P6-003 shared-workspace behavior remains green;
- the real-browser (DC-01) evidence was independently reproduced;
- no BLOCKER/HIGH/MEDIUM/LOW/INFO findings remain.

P6-007 is transitioned `VERIFIED -> DONE`. The historical independent review
artifact (`reviews/P6-003-P6-007-independent-review.md`, which documented the
original CHANGES_REQUESTED MEDIUM finding) and the corrective provenance (task
file corrective cycle; `PHASE6-P6-003-CLOSURE-P6-007-CORRECTIVE-BATCH-REPORT.md`)
are preserved unchanged; no historical finding was rewritten.

Frozen downstream semantics (settled):

Phase 5 translations align to the machine `segment_index`; P6-007 does not own
the translation lifecycle; P6-007 does not persist or infer staleness; the edited
revision <-> machine translation provenance relationship must remain explicit;
structurally incompatible revisions must not be silently aligned.

Phase consequence:

P6-007 closure does not close Phase 6. P6-004/P6-005/P6-008/P6-009 remain not
implemented; P6-004's canonical contract is authored (this batch) and awaits an
explicit HPO READY promotion; P6-005 additionally waits on P6-004 DONE; P6-008
requires its own contract and READY promotion; P6-009 is FINAL_GATE_ONLY. No
further Phase 7 task is authorized.

Record completeness (retained): `reviews/P6-002-corrective-independent-re-review.md`
remains absent; P6-002 is **not** reopened and no artifact may be fabricated or
reconstructed from summaries.

Blocks: None.

Does Not Block: P6-004 (contract authored; awaits its own explicit HPO READY
promotion), P6-005 (waits on P6-004 DONE), P6-008 and P6-009 (each requires its
own contract / READY promotion; P6-009 is FINAL_GATE_ONLY); Phase 7 remains not
generally authorized.

### DECISION-P6-004-READY-001 - HPO promotes P6-004 to READY and authorizes implementation

Decision ID: DECISION-P6-004-READY-001

Status: DECIDED - HPO 2026-09-24

Type: Task Promotion (READY)

Originating Task: P6-004 (Timing Editing + Validation)

Question:

Should P6-004 be promoted to READY and authorized for implementation?

Resolution:

HPO-APPROVED 2026-09-24. The canonical P6-004 contract
(`tasks/P6-004-timing-editing-validation.md`) exists and its dependencies are
satisfied: P6-001 = DONE (frozen D6-03 timing invariants), P6-002 = DONE
(revision service), P6-003 = DONE (shared workspace surface now free), D6-03
adopted, and Phase 4 primitives / P6-006 available. P6-004 does not depend on
Phase 5 or P6-005. P6-004 is promoted to **READY** and authorized for
implementation.

Frozen timing semantics the implementation must consume, not redefine:
finite; non-negative; `start <= end`; millisecond precision with no silent
rounding; overlaps legal; nested overlaps legal; equal starts/ends legal;
zero-length legal but never active; no cross-segment timestamp monotonicity;
ordering by revision `position`, not time; machine-source timing immutable.

Required behavior:

- timing-only edits on the active editable revision, append-only, expected-base
  CAS, stale saves fail as canonical conflict, no in-place revision mutation;
- preserve text, language/source metadata, revision segment identity/position;
  change only canonical timing fields;
- first timing edit from the machine source materializes the initial revision
  (machine timing copied verbatim) then appends the edit; machine timing
  unchanged; exact parent/version/active-pointer behavior verified;
- `EditKind::Timing` -> `TranslationStalenessReason::TimingChanged`; **do not**
  persist translation staleness (P6-005 owns it);
- `TimingEditComposer` (or equivalent narrow domain service) rejects NaN/infinite,
  negative, `start > end`, and out-of-contract precision; allows overlap, nested
  overlap, equal starts/ends, zero-length, out-of-time-order positions, and
  extending past a neighbor; no accidental monotonicity rule;
- playback source of truth: when an active revision exists, playback seek
  controls and active-segment resolution use active-revision timing, P6-006
  navigation stays ordered by revision `position`, and machine timing must not
  leak into edited playback; when no active revision exists, Phase 4 machine
  timing remains authoritative; preserve half-open semantics and lowest-position
  tie resolution;
- workspace UX: explicit timing edit mode, visible start/end, save, cancel,
  inline/accessible validation feedback, stale-conflict feedback, active-revision
  indicator, dedicated `data-timing-*` hooks; reserved Phase 4 hooks not
  repurposed; no interference with P6-003 text editing or P6-007 comparison;
- real-browser DC-01 verification is mandatory (valid edit; negative rejected;
  `start > end` rejected; zero-length accepted; overlap accepted; reload
  durability; stale conflict; cancel no write; machine timing unchanged;
  ownership denial; playback seek uses active-revision timing after save;
  active-segment resolution uses active-revision timing; P6-006 navigation stays
  position-based);
- regressions re-run for P6-003, P6-006, P6-007, and Phase 4 playback/seek;
- after implementation P6-004 = `IMPLEMENTED_PENDING_REVIEW`; no self-VERIFIED or
  self-DONE; a durable pre-review artifact and browser evidence are produced.

Boundaries:

- P6-004 must not implement split, merge, structural segment-identity change,
  persisted translation staleness, or translation remapping (all P6-005);
- P6-005 remains dependency-blocked until P6-004 is DONE;
- P6-008/P6-009 and all further Phase 7 work remain not started/not promoted;
- missing independent-review artifacts for P6-002/P6-007 must not be fabricated;
  their record-completeness notes are retained.

Blocks: None.

Does Not Block: P6-005 (still waits on P6-004 DONE), P6-008/P6-009 (each requires
its own contract / READY promotion; P6-009 is FINAL_GATE_ONLY); Phase 7 remains
not generally authorized.

### DECISION-P6-004-CLOSURE-001 - Accept the P6-004 independent review verdict and close P6-004 DONE

Decision ID: DECISION-P6-004-CLOSURE-001

Status: DECIDED - HPO 2026-09-24

Type: Task Closure

Originating Task: P6-004 (Timing Editing + Validation)

Question:

Should the fresh independent review verdict (`VERIFIED`) be accepted and P6-004 be
closed DONE?

Resolution:

HPO-ACCEPTED 2026-09-24. The fresh independent review of P6-004 returned
**VERIFIED** with no remaining BLOCKER/HIGH/MEDIUM finding. Independently
confirmed:

- the frozen Phase 6 timing semantics are implemented exactly (finite,
  non-negative, `start <= end`, millisecond precision without silent rounding,
  overlap/nested/equal-bounds/zero-length legality, no cross-segment
  monotonicity, ordering by revision `position`, immutable machine timing);
- invalid first (machine-source) edits are write-free;
- active-revision timing is the playback / active-segment-resolution source of
  truth (half-open, lowest-`position` tie resolution preserved);
- machine-source timing remains immutable;
- stale-base compare-and-set behavior remains correct (no silent merge);
- no P6-003/P6-006/P6-007 regression was found;
- no split/merge or translation-staleness persistence was introduced.

P6-004 is transitioned `VERIFIED -> DONE`. The independent review and the
historical implementation/pre-review artifacts
(`reviews/pre-review/P6-004-pre-review.md`,
`PHASE6-P6-004-IMPLEMENTATION-BATCH-REPORT.md`,
`verification/p6-004/P6-004-BROWSER-VERIFICATION-EVIDENCE.md`) are preserved
unchanged.

Phase consequence:

P6-004 closure does not close Phase 6. P6-005 is unblocked by P6-004 DONE and is
reclassified to `CONTRACT_REQUIRED / READY-ELIGIBLE AFTER CONTRACT`
(`DECISION-P6-005-ELIGIBILITY-001`); P6-008 remains separately contract-gated;
P6-009 remains FINAL_GATE_ONLY. No further Phase 7 work is authorized.

Blocks: None.

Does Not Block: P6-005 (now eligible for contract authoring / READY promotion
after its contract and unresolved owner decisions), P6-008/P6-009 (each requires
its own contract / READY promotion; P6-009 is FINAL_GATE_ONLY); Phase 7 remains
not generally authorized.

### DECISION-P6-004-INFO-CARRYFORWARD-001 - Carry forward P6-004 non-blocking INFO debt without reopening P6-004

Decision ID: DECISION-P6-004-INFO-CARRYFORWARD-001

Status: DECIDED - HPO 2026-09-24

Type: Debt Carry-Forward (non-blocking)

Originating Task: P6-004 (Timing Editing + Validation)

Resolution:

HPO-ACCEPTED 2026-09-24. The following non-blocking INFO items are carried forward
without reopening P6-004 and without changing its DONE state:

1. **Pre-existing P6-007 / P4-006 V4-13 locator-scoping issue.** The older
   P4-006 V4-13 spec's `getByText('Segmen kedua').first()` can match hidden P6-007
   comparison-table content rendered before the transcript rows. This was
   introduced by P6-007 (`985d2c5`) and is independent of P6-004. Recommended
   future fix: scope the older spec's locator to `[data-transcript-region]`
   (separate, scoped change; not P6-004/P6-005 scope).
2. **Shared Laravel validation error bag.** The timing toolbar renders
   `$errors->first()`; in principle an unrelated form error in the same request
   could surface there. No observed failure exists and no P6-004 acceptance
   criterion depends on it. Future hardening (optional): scope the toolbar to
   `$errors->getBag(...)` or an error-bag key.
3. **Phase 4 headless playback-start flake (V4-08/V4-09).** In this headless
   environment `<audio>/<video>.currentTime` does not advance after `play()`
   (`currentTimeAfterPlay: 0`); the same value exists in the pre-existing
   `verification/artifacts/p4-003-browser-results.json`. This is environmental
   and pre-dates P6-004; P6-004 does not touch the media element, stream route, or
   player component.

These are not P6-004 blockers. P6-004 remains DONE; no code, test, or contract
change is authorized by this carry-forward.

Blocks: None.

Does Not Block: P6-005 contract authoring; P6-008/P6-009; Phase 7 remains not
generally authorized.

### DECISION-P6-005-ELIGIBILITY-001 - Reclassify P6-005 and authorize its canonical contract

Decision ID: DECISION-P6-005-ELIGIBILITY-001

Status: DECIDED - HPO 2026-09-24

Type: Task Eligibility / Contract Authorization

Originating Task: P6-005 (Split / Merge + Translation Invalidation)

Question:

With P6-004 DONE, is P6-005 eligible, and is its canonical contract authorized?

Resolution:

HPO-APPROVED 2026-09-24. With P6-004 = DONE, P6-005 is reclassified from
`DEPENDENCY_BLOCKED` to **`CONTRACT_REQUIRED / READY-ELIGIBLE AFTER CONTRACT`**.
Its canonical contract is authorized for authoring in this batch.

The contract must consume, without redefining, the frozen P6-001 through P6-004
semantics: the revision model (immutable machine source, append-only revisions,
one active pointer, durable graph), `RevisionSegmentIdentity` / contiguous
`position`, the D6-03 timing invariants, expected-base concurrency, and the frozen
translation-invalidation taxonomy and precedence
`SegmentStructureChanged > TimingChanged > SourceTextChanged`.

P6-005 owns two high-risk areas: structural segment editing (split/merge) and
persisted translation invalidation. It must not implement yet. Implementation
requires, in addition to this contract:

- resolution of the surfaced owner decisions
  (`DECISION-P6-005-SPLIT-BOUNDARY-001`, `DECISION-P6-005-MERGE-JOIN-001`,
  `DECISION-P6-005-LANGUAGE-PROVENANCE-001`,
  `DECISION-P6-005-STALENESS-LIFECYCLE-001`, `DECISION-P6-005-SCHEMA-001`); and
- an explicit HPO READY promotion.

P6-005 must not rewrite Phase 5 translation segments, silently remap translations
to new revision segment identities, or pretend structurally modified revisions
remain aligned to machine translation. Structural changes invalidate the
appropriate translation state.

Blocks: P6-005 implementation (pending contract, owner decisions, and HPO READY
promotion).

Does Not Block: P6-004 (DONE); P6-008/P6-009 (each separately governed; P6-009 is
FINAL_GATE_ONLY); Phase 7 remains not generally authorized.

### DECISION-P6-005-SPLIT-BOUNDARY-001 - DECIDED: split boundary and degenerate-split policy

Decision ID: DECISION-P6-005-SPLIT-BOUNDARY-001

Status: DECIDED - HPO 2026-09-24 (strict interior boundary; option 1)

Type: Product / Domain Decision

Question:

D6-04 and the frozen P6-001 contract define split's identity/position/language
rules but not the split boundary. Should a split be allowed exactly at a segment
start or end (producing a zero-length child), and what text-boundary degeneracy is
allowed?

Context:

- Frozen timing semantics make zero-length segments legal but never active.
- P6-004 established that no hidden monotonicity/restriction is added.

Candidate options:

1. Require a strictly interior boundary (`start < t < end`) and a strictly
   interior text offset (`0 < k < length`); reject splits that would produce a
   zero-length or empty-text child.
2. Allow `t` anywhere in `[start, end]` and `k` anywhere in `[0, length]`;
   zero-length / empty-text children are legal per the frozen semantics.

The contract records option 1 as the default and must be confirmed or overridden.

Resolution:

HPO-DECIDED on 2026-09-24 — Adopt option 1. **Split is allowed only at a strict
interior boundary.** For the segment being split:

- split at the beginning is rejected;
- split at the end is rejected;
- the operation must produce two meaningful child segments;
- degenerate structural splits are not allowed.

For timing, the split instant must satisfy the corresponding strict interior rule
required by the canonical split operation (`start < t < end`); for text, the
offset must satisfy `0 < k < length(text)` (Unicode code points). Do not create
zero-duration/empty structural children merely to permit a boundary split. This
does **not** change the general P6 timing rule that zero-length segments may exist
through other valid editing operations. Durable record: `DECISIONS.md` (P6-005
Owner Decisions).

### DECISION-P6-005-MERGE-JOIN-001 - DECIDED: merge text-join and resulting-timing rule

Decision ID: DECISION-P6-005-MERGE-JOIN-001

Status: DECIDED - HPO 2026-09-24 (plain-space join; option 1)

Type: Product / Domain Decision

Question:

D6-04 defines merge adjacency and identity but not the text-join separator or the
resulting start/end timing rule. What are the canonical rules?

Context:

- Merge is limited to adjacent revision segments (frozen P6-001 §7).
- Text joining and resulting timing must be explicit and deterministic.
- Frozen timing permits overlap / out-of-time-order positions, so "first start to
  last end" is not the only candidate.

Candidate options (to be confirmed/overridden):

1. Text: concatenate contributing texts in `position` order separated by a single
   space. Timing: `start` = start of the earliest-position contributing segment,
   `end` = end of the latest-position contributing segment.
2. Text: concatenate with no separator. Timing: `start` = minimum contributing
   start, `end` = maximum contributing end.

The contract records option 1 as the default.

Resolution:

HPO-DECIDED on 2026-09-24 — Adopt option 1. **Merge is adjacent-only and uses one
canonical plain-space separator between contributor texts.** Contributors are
ordered by revision position; the resulting text is
`segment1_text + " " + segment2_text [+ ...]`. Do not trim or rewrite the internal
content of contributor text; do not perform punctuation-aware rewriting; do not
infer sentence structure; merge remains deterministic. Resulting timing is the
start of the earliest-position contributor and the end of the latest-position
contributor. Durable record: `DECISIONS.md` (P6-005 Owner Decisions).

### DECISION-P6-005-LANGUAGE-PROVENANCE-001 - DECIDED: representation of mixed-language merge provenance

Decision ID: DECISION-P6-005-LANGUAGE-PROVENANCE-001

Status: DECIDED - HPO 2026-09-24 (explicit nullable provenance representation)

Type: Product / Schema Decision

Question:

The frozen P6-001 §7 rule fixes the merge language value ("carries the language of
the earliest contributing segment") and requires it to be "flagged as
mixed-language provenance", but no representation for that flag is frozen. How is
the mixed-language provenance flag represented?

Context:

- Split: both children carry the split source segment's language marker (frozen;
  no new decision).
- Merge: value = earliest contributing segment's language (frozen); the
  mixed-language *flag* representation is not frozen.
- `transcript_revision_segments` (P6-001 §10) has only a `language` column.

Candidate options:

1. Add an explicit revision-segment provenance marker (for example a nullable
   `language_provenance` string or `mixed_language` boolean) — schema addition.
2. Represent mixed-language provenance without a new column (for example via a
   reserved language value) — to be assessed against the BCP 47 vocabulary
   `ms/en/zh/ta/und`.

No language value may be silently chosen or inferred beyond the frozen rule.

Resolution:

HPO-DECIDED on 2026-09-24 — Adopt an explicit nullable provenance representation
(option 1). **Split:** both child segments inherit the source segment's language
marker (unchanged frozen rule). **Merge:** if every contributor has the same
language, the resulting segment retains that language; if contributors contain
different language markers, the resulting segment language becomes `und` and the
original contributor-language provenance must be retained explicitly. Do not
silently select the first/earliest language as the merged segment's canonical
language. Do not attempt automatic language redetection during split/merge. The
implementation must use an explicit persistence representation for mixed-language
provenance (a typed/JSON ordered list of the contributor language markers) rather
than relying on `und` alone. Durable record: `DECISIONS.md` (P6-005 Owner
Decisions).

### DECISION-P6-005-STALENESS-LIFECYCLE-001 - DECIDED: persisted translation staleness lifecycle

Decision ID: DECISION-P6-005-STALENESS-LIFECYCLE-001

Status: DECIDED - HPO 2026-09-24 (canonical lifecycle frozen)

Type: Product / Domain Decision

Question:

D6-04 fixes the invalidation policy (every edit invalidates; never silently
preserve/remap) and P6-001 names the marker shape, but the staleness lifecycle
semantics are not fully frozen. The following must be decided:

- Scope/identity: staleness is per `translations` row (= per
  `(transcription_id, target_language)`), which is the only authorized Phase 5
  identity. Confirm.
- Repeated edits: on a second invalidation, is `staleness_reason` replaced by the
  new reason, or does it accumulate the highest-severity reason per the frozen
  precedence? Is `stale_at` refreshed?
- Retranslation: does a newly completed translation for the target clear
  `stale_at`/`staleness_reason`?
- Historical viewability: after invalidation, may the previous (now stale)
  translation output remain viewable as historical/stale content, and how is that
  distinguished from current content?
- Caused-by identity: must the causing revision/source identity be recorded (see
  `DECISION-P6-005-SCHEMA-001`)?

The contract records the following defaults pending confirmation: per-translation
scope; `staleness_reason` replaced using frozen precedence and `stale_at`
refreshed; retranslation clears staleness; stale output remains viewable but is
clearly marked stale.

Resolution:

HPO-DECIDED on 2026-09-24 — Adopt the following canonical translation-invalidation
lifecycle (it supersedes the contract defaults above where they differ).

Scope: staleness is persisted per translation row / translation target identity.
Existing Phase 5 translations remain historical outputs. Structural or other
approved source revision changes do not rewrite or remap existing translation
segments.

First invalidation: when a currently non-stale translation becomes invalidated,
set `stale_at`, set `staleness_reason`, and record the causing revision.

Repeated invalidation: if an already-stale translation is affected by another
edit, preserve the original `stale_at`; choose the canonical reason using the
frozen precedence `SegmentStructureChanged > TimingChanged > SourceTextChanged`;
upgrade `staleness_reason` only when the new reason outranks the stored reason;
retain/update causing-revision provenance consistently with the canonical stored
reason. Do not downgrade a stronger reason to a weaker reason. (`stale_at` is
**preserved**, not refreshed, on repeated invalidation.)

Historical translation: a stale translation remains viewable as historical output
where existing product surfaces permit it, and must not be represented as current
for the active revision.

Retranslation: do not clear staleness on the historical translation row. A later
successful retranslation creates/uses the canonical new translation identity for
that source/revision/target lifecycle. The old translation remains stale
historical evidence; do not delete or mutate its translated segment content to
make it appear current.

Failed retranslation: a failed retranslation must not make the previous stale
translation current again.

Durable record: `DECISIONS.md` (P6-005 Owner Decisions).

### DECISION-P6-005-SCHEMA-001 - DECIDED: exact schema additions for persisted invalidation

Decision ID: DECISION-P6-005-SCHEMA-001

Status: DECIDED - HPO 2026-09-24 (minimum additive schema authorized)

Type: Schema Decision

Question:

Which schema additions does P6-005 require? P6-001 §10 already names
`translations.stale_at` (timestamp, nullable) and `translations.staleness_reason`
(string, nullable) as owned by P6-005. Does P6-005 also need to record the causing
revision/source identity, and (per `DECISION-P6-005-LANGUAGE-PROVENANCE-001`) a
mixed-language provenance marker on revision segments?

Context:

- Phase 5 translation identity is `(transcription_id, target_language)` with one
  active translation per target (partial unique index on active statuses);
  `translation_segments` align to machine `segment_index`.
- No `stale_at`/`staleness_reason` columns exist yet.

Candidate additions (name-only; not implemented in this batch):

- `translations.stale_at` (timestamp, nullable) — named by P6-001.
- `translations.staleness_reason` (string, nullable) — named by P6-001.
- optionally `translations.stale_revision_id` (string, nullable) or equivalent
  causing-revision provenance — new, requires a decision.
- optionally a revision-segment mixed-language provenance marker — new, requires
  `DECISION-P6-005-LANGUAGE-PROVENANCE-001`.

No final schema may be invented without tracing the Phase 5 translation identity
contract.

Resolution:

HPO-DECIDED on 2026-09-24 — Authorize the minimum additive schema required by the
P6-005 contract.

Translation invalidation — add to `translations`:

- `stale_at` (nullable timestamp);
- `staleness_reason` (nullable canonical reason);
- `stale_caused_by_revision_id` (nullable reference to the revision responsible
  for the stored canonical invalidation reason).

The schema must preserve existing Phase 5 translation rows and translation
segments; do not rewrite Phase 5 translation identity.

Revision-segment language provenance — add an explicit representation on revision
segments for mixed-language provenance. It must be nullable when unnecessary,
deterministic, capable of retaining the contributor language markers in
contributor order, and independent of machine `segment_index`. A simple typed/JSON
representation is acceptable if consistent with repository conventions and
validated at the domain boundary. Do not add a generic metadata dumping field.

Durable record: `DECISIONS.md` (P6-005 Owner Decisions).

### DECISION-P6-005-READY-001 - Promote P6-005 to READY and authorize implementation

Decision ID: DECISION-P6-005-READY-001

Status: DECIDED - HPO 2026-09-24

Type: Phase Authorization / Task Authorization

Originating Task: P6-005 (Split / Merge + Translation Invalidation)

Question:

Are the five P6-005 owner decisions and the reconciled canonical contract
sufficient to promote P6-005 to READY and authorize implementation?

Resolution:

HPO-AUTHORIZED on 2026-09-24. The five owner decisions
(`DECISION-P6-005-SPLIT-BOUNDARY-001`, `DECISION-P6-005-MERGE-JOIN-001`,
`DECISION-P6-005-LANGUAGE-PROVENANCE-001`,
`DECISION-P6-005-STALENESS-LIFECYCLE-001`, `DECISION-P6-005-SCHEMA-001`) are
recorded DECIDED, and the canonical contract
(`tasks/P6-005-split-merge-translation-invalidation.md`) is reconciled to
incorporate all five.

Transition applied: P6-005: `CONTRACT_AUTHORED / READY-ELIGIBLE AFTER CONTRACT`
→ **READY**, authorized for implementation.

Scope: implement only P6-005 — structural split; structural merge; required
revision-segment language provenance; additive translation-staleness schema;
atomic invalidation (structural revision append and invalidation in one
transaction using the existing P6-002 CAS/version rules); required UI;
feature/domain/concurrency tests; and real-browser DC-01 verification.

Not authorized: P6-008 (arbitrary revision-history UI), P6-009 (Phase 6 final
gate), rewriting Phase 5 translations/translation segments, and any new Phase 7
work. P6-005 must not redefine P6-001..P6-004 semantics.

Review model: per-task independent review (Claude Code). P6-005 moves to
`IMPLEMENTED_PENDING_REVIEW` after implementation; the implementer must not
self-mark VERIFIED or DONE.

Blocks: none.

Does Not Block: P6-008/P6-009 (separately governed; P6-009 is FINAL_GATE_ONLY);
Phase 7 remains not generally authorized.

Durable record: `DECISIONS.md` (P6-005 Owner Decisions; P6-005 READY Promotion).
