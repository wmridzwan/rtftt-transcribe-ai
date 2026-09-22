# P4-006 — Phase 4 Integration Verification

## Status

DONE

## Ownership

Implementation Owner: UNASSIGNED (evidence harness + execution)
Reviewer: Claude Code (independent verification; per AGENTS.md agent model)

## Authorization

This contract was authored under `DECISION-PHASE4-AUTHORIZATION-001` (Phase 4
authorized for task-contract authoring, 2026-09-19), audited/finalized on
2026-09-19, and authorized for execution under
`DECISION-P4-006-AUTHORIZATION-001` (HPO, 2026-09-20): BACKLOG → READY.
Dependency gate satisfied: P4-001 through P4-005 are each DONE (HPO-closed).

P4-006 is the final Phase 4 integration verification gate. It must
independently execute the V4-01..V4-25 evidence over a real completed persisted
transcription and must not substitute P4-003's prior observed results for its
own execution. Feature behavior P4-001..P4-005 is frozen for final verification;
P4-006 must not silently fix feature defects (record the failure → identify the
owning task → return to governance for correction/re-review). The authorized
Playwright verification tooling, fixture infrastructure, auth setup, and harness
primitives may be reused per ADR-020 / `DECISION-P4-BROWSER-VERIFICATION-002`.

## Authorized Phase

Phase 4 — Transcript Experience baseline (ADR-019)

## Batch

Batch 1 (proposed; to be confirmed by the implementation authorization)

## Objective

Independently verify the frozen Phase 4 transcript experience end-to-end over a
real completed persisted transcription, and confirm no Phase 1/2/3 regression.
Mock-only verification is insufficient for the integration gate.

## Context

- ADR-019; `PHASE4-PLANNING.md`
- P4-001 through P4-005 (frozen Phase 4 behavior)
- Phase 3 persisted data and real completed transcripts
- P3-008 as the precedent for an independent phase integration gate
- `.ai/guidelines/orchestration-policy.md`

## Verification Nature

P4-006 is a verification task, not feature development. The Builder prepares and
executes the integration harness and captures reproducible evidence; Claude Code
independently reproduces the evidence and issues the VERIFIED /
CHANGES_REQUESTED verdict; the Human Product Owner closes.

## End-to-End Flow

```text
completed persisted transcription
  → open transcript workspace
  → authorized private media playback
  → range request
  → play media
  → click segment seek
  → synchronized active segment
  → search
  → copy
  → export
```

Include representative audio and video where required. No translation.

## Verification Matrix

| ID | Scenario | Layer | Expected result |
|----|----------|-------|-----------------|
| V4-01 | Completed workspace gating | UI/feature | Workspace interaction available only for `completed` |
| V4-02 | Processing-state gating | UI/feature | `queued`/`preparing`/`transcribing` show processing state; no transcript interaction |
| V4-03 | Failed-state retry preservation | UI/feature | Phase 3 retry surface intact; no transcript interaction |
| V4-04 | Private media full response | HTTP | Owner `200` with correct `Content-Type`, `Accept-Ranges`, length |
| V4-05 | Media partial range response | HTTP | `206` for `bytes=0-`, `N-M`, `N-`, `-N` with correct `Content-Range` |
| V4-06 | Invalid/unsatisfiable range | HTTP | `416` with `Content-Range: bytes */<size>`; malformed → `200` |
| V4-07 | Cross-user denial | Security | Cross-user/unauthenticated media access denied |
| V4-08 | Audio playback | Browser | Audio element plays via the stream URL |
| V4-09 | Video playback | Browser | Video element plays via the stream URL |
| V4-10 | Click seek | Browser | Clicking a timestamp seeks to exact ms target |
| V4-11 | Active segment sync | Browser | Active segment matches resolver during playback |
| V4-12 | Gap behavior | Browser/unit | No active segment in gaps, before first, after final |
| V4-13 | Multilingual display | UI | `ms`/`en`/`zh`/`ta`/`und` labels/text render without corruption |
| V4-14 | Search Latin | Browser/feature | Case-insensitive substring match/highlight |
| V4-15 | Search Chinese | Browser/feature | Chinese substring match/highlight |
| V4-16 | Search Tamil | Browser/feature | Tamil substring match/highlight |
| V4-17 | Copy full transcript | Browser/feature | Plain text, ordered, newline-separated, no timestamps |
| V4-18 | Copy segment | Browser/feature | Segment plain text only, no timestamp |
| V4-19 | TXT export | HTTP | Completed-only; persisted content; Unicode correct |
| V4-20 | SRT export | HTTP | Valid `HH:MM:SS,mmm`; no `,1000`; ordered |
| V4-21 | VTT export | HTTP | `WEBVTT`; valid `HH:MM:SS.mmm`; no `.1000`; ordered |
| V4-22 | DOCX export | HTTP | Valid document; persisted Unicode text |
| V4-23 | No-speech | E2E | Completed, `speech_detected=false`, no segments; workspace valid; exports valid |
| V4-24 | Phase 3 regression | Regression | Auth, dashboard, navigation, ownership, upload/private storage, transcription pipeline, retry/recovery intact |
| V4-25 | Full quality suite | Quality | Full PHP suite green (no new unexplained skips); Pint clean; PHPStan 0 errors |

## Out of Scope

Do not implement:

- new features or fixes beyond what verification requires to be recorded;
- transcript editing, diarization, chapters, annotations (Phase 6);
- translation (Phase 5);
- production hardening (Phase 7);
- any schema/migration change.

## Dependencies

Requires (each VERIFIED and closed DONE):

- P4-002 (Authorized Private Media Playback)
- P4-003 (Segment Navigation + Synchronized Highlighting)
- P4-004 (Transcript Search + Copy)
- P4-005 (Export Hardening)

## Acceptance Criteria

1. V4-01 through V4-25 are each executed and recorded with an outcome.
2. A real completed persisted transcription drives the end-to-end flow.
3. Audio and video playback are both verified (or a documented environment
   limitation is explicitly recorded, never silently omitted).
4. Browser-level behavior (V4-08..V4-12, V4-14..V4-18) is verified at browser
   level or via the authorized browser-test tool; backend-only tests must not be
   represented as proving browser behavior.
5. Cross-user isolation is verified for playback/search/copy/export.
6. No-speech is verified as a valid completed workspace.
7. Phase 1/2/3 regression is verified with no new unexplained skips.
8. No translation or editing is introduced.
9. Evidence is retained and reproducible.
10. Final independent review completed with no blocking findings.
11. Full quality suite: tests pass; Pint clean; PHPStan 0 errors.

## Browser Verification Strategy

- Logic is covered by unit/feature/Livewire tests (resolver, gating, search
  state, copy content, exports).
- Real `HTMLMediaElement` seek/playback/highlight and clipboard behavior cannot
  be proven by backend tests. The repository currently has no browser automation
  (Pest only; no Dusk/Playwright).
- P4-006 must therefore either:
  - (a) execute a documented, reproducible, environment-dependent browser
    verification with retained evidence (step list, measured `currentTime`
    values, screenshots/recording); or
  - (b) use a lightweight browser-test tool, but only if that tool is explicitly
    justified and authorized before implementation.
- Introducing a new browser-testing platform is an outstanding owner decision
  (see the audit report, Outstanding Owner Decisions). Do not add one by
  preference.

## Evidence Requirements

- Reproducible commands and environment metadata.
- Per-matrix-ID outcome with the observed evidence.
- Explicit statement of what was automated vs environment-dependent vs not
  executed.
- No false claim of browser/media behavior that was not actually exercised.

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Backend-only evidence claimed as browser behavior | Separate browser verification; explicit claim scoping |
| Missing video verification | V4-09 required; limitation must be recorded |
| Incomplete Phase 3 regression | V4-24 fixed checklist + full suite |
| Unfrozen behavior verified | Dependency gate requires VERIFIED/DONE predecessors |
| Clipboard unavailability in headless env | Record environment limitation; verify at browser level |

## Implementation Notes

To be completed by the implementation owner.

### Files Changed

- `verification/p4-006-seed.php` (new) — deterministic final-gate fixtures.
- `verification/playwright.p4-006.config.js`, `verification/p4-006-auth.setup.js`,
  `verification/p4-006/phase4-integration.spec.js` (new) — final-gate browser suite.
- `tests/Feature/Phase4IntegrationTest.php` (new) — V4-01/V4-02/V4-03 backend.
- `P4-006-INTEGRATION-VERIFICATION-EVIDENCE.md` (new) — final-gate evidence.

### Important Decisions

- Reused the authorized Playwright infrastructure (ADR-020) with a dedicated
  p4-006 suite; independently executed all browser evidence (no reuse of P4-003
  results).
- Feature behavior P4-001..P4-005 was treated as frozen; no product correction
  was made.

### Known Limitations

- The final integration gate did NOT pass: V4-14 (search next/previous current-match
  navigation) and V4-18 (segment copy) FAIL, owned by P4-004 (see blocker).

## Verification

Commands executed on 2026-09-20 (PHP 8.4, Pest 5.1, Playwright 1.63.0, Chromium
headless shell 153.0.8010.12):

- `node_modules/.bin/playwright test --config=verification/playwright.p4-006.config.js`
  → 12 passed, 2 failed (V4-14, V4-18); stable across cold and repeat runs.
- `vendor/bin/pest tests/Feature/Phase4IntegrationTest.php` → 3 passed, 19 assertions.
- Phase 4 focused: P4-001 30/117; P4-002 13/61; P4-003 8/32; P4-004 7/19; P4-005 21/116.
- Full suite: 433 tests, 432 passed, 1 skipped, 1453 assertions, 2 warnings.
- PHPStan 0 errors; Pint passed.

Result:

**FAILED — final integration gate not passed.** V4-14 and V4-18 FAIL (owning
task P4-004). Full matrix and defect detail:
`P4-006-INTEGRATION-VERIFICATION-EVIDENCE.md`.

### Blocker

A frozen Phase 4 feature (P4-004 `transcriptSearch`) is defective in a real
browser: methods querying the DOM via `this.$el` fail when invoked from
child-element event handlers (Alpine `$el` resolves to the event target, not the
component root). Effects: per-segment copy copies nothing; search
next/previous does not move the current-match highlight. Per the P4-006 contract,
this is recorded and attributed, not patched. HPO reconciliation required
(recommended: reopen P4-004 for a narrow correction cycle, then re-run P4-006).

### Release (2026-09-20)

Blocker resolved. P4-004 was reopened (`DECISION-P4-004-REOPEN-001`), corrected,
independently re-verified (`reviews/P4-004-corrective-independent-re-review.md`:
V4-14 and V4-18 now PASS), and HPO re-closed DONE
(`DECISION-P4-004-CORRECTIVE-CLOSURE-001`). `DECISION-P4-006-FINDING-001` = CLOSED.
P4-006 released BLOCKED → READY. The existing
`DECISION-P4-006-AUTHORIZATION-001` remains valid; no new authorization is
required. On re-execution, P4-006 must run the full V4-01..V4-25 matrix again
with fresh browser evidence, cold/repeat stability, and fresh full quality
gates; a selective rerun of only V4-14/V4-18 is insufficient. Feature behavior
P4-001..P4-005 remains frozen.

## Review

Review File:

`reviews/P4-006-independent-review.md`

Review Status:

VERIFIED (no BLOCKER/HIGH/MEDIUM; LOW-1 and INFO-1 non-blocking). Not DONE —
Human Product Owner closure required. Phase 4 is not closed by this
verification.

## Rerun (2026-09-20)

Fresh final-gate execution after the P4-004 correction and finding closure. The
original failed execution is preserved unchanged in
`P4-006-INTEGRATION-VERIFICATION-EVIDENCE.md`; fresh evidence is in
`P4-006-INTEGRATION-VERIFICATION-RERUN-EVIDENCE.md`.

- Full V4-01..V4-25 matrix re-executed: all mandatory items PASS.
- Playwright p4-006 suite: 14 passed (cold) and 14 passed (repeat); p4-004
  regression 3 passed. V4-14 and V4-18 PASS.
- Full PHP: 433 tests, 432 passed, 1 skipped, 1451 assertions, 2 warnings.
- Pint passed; PHPStan 0 errors.
- Historical cold-start flake not reproduced.
- No product or harness changes; feature behavior P4-001..P4-005 remains frozen.

Result: PASSED — eligible for independent review. Not VERIFIED/DONE.

## Closure

Closed as DONE by the Human Product Owner on 2026-09-20
(DECISION-P4-006-CLOSURE-001), based on `reviews/P4-006-independent-review.md`
(P4-006 = VERIFIED; no BLOCKER/HIGH/MEDIUM; all 25 mandatory V4-01..V4-25 items
PASS; LOW-1 intermittent V4-08 Playwright timing flake and INFO-1 pre-existing
`showRenameModal` retained as non-blocking).

Canonical transition: VERIFIED → (HPO closure decision) → DONE.

Full history preserved (not a straight-line success): first failed final-gate
execution (V4-14/V4-18 FAIL), `DECISION-P4-006-FINDING-001`, the P4-004
corrective reopen/review/re-closure, and the fresh passing rerun. Evidence:
`P4-006-INTEGRATION-VERIFICATION-EVIDENCE.md` and
`P4-006-INTEGRATION-VERIFICATION-RERUN-EVIDENCE.md`.

Closure is governance/state reconciliation only; no implementation or test change
is authorized.

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED. P4-006 is the final
Phase 4 integration verification gate; it does not close Phase 4. Phase 4 closure
remains a separate Human Product Owner decision.
