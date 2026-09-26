# P7-010 — Browser Support Matrix + Flake Elimination

## Status

DONE — closed by the Human Product Owner on 2026-09-26
(`DECISION-P7-010-CLOSURE-001`) on the independent VERIFIED verdict
(`reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md` §D; no BLOCKER/HIGH).

History preserved: BACKLOG — CONTRACT_AUTHORED (2026-09-26) → READY (HPO
`DECISION-PHASE7-WAVE1-READY-PROMOTION-001`) → IN_PROGRESS (execution
authorized `DECISION-PHASE7-WAVE1-EXECUTION-AUTHORIZATION-001`; work begun
2026-09-26) → REVIEW (builder report `reviews/P7-010-BUILDER-REPORT.md`) →
VERIFIED (`reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md`§D; all 9 ACs
independently confirmed, incl. traced TD-013 fix mechanism and retained
result-artifact corroboration) → DONE (HPO `DECISION-P7-010-CLOSURE-001`,
2026-09-26). This review does not authorize any later Phase 7 wave.

## Ownership

Implementation Owner: (unassigned — HPO assigns on READY promotion)
Reviewer: Claude Code (independent review on REVIEW)

## Authorized Phase

Phase 7 — Production Hardening (`PHASE7-SCOPE-CONTRACT.md`, ADOPTED —
NOT AUTHORIZED FOR IMPLEMENTATION). This contract alone authorizes no
implementation.

## 1. Task Identity

- Task ID: P7-010
- Canonical title: Browser Support Matrix + Flake Elimination
- Phase: 7 — Production Hardening
- Proposed state: READY (HPO promotion `DECISION-PHASE7-WAVE1-READY-PROMOTION-001`, 2026-09-26)

## 2. Objective

Lock the production browser contract to Chromium-only (D7-05/A),
eliminate the historical Playwright flake inside the supported matrix
(TD-005), capture the deferred upload-progress browser evidence (TD-006),
and fix the pre-existing console defect (TD-013) — with deterministic,
retained Chromium verification and preserved historical evidence.

## 3. Why This Task Exists

- TD-005 (MEDIUM/HPO_DECISION_REQUIRED): headless playback-start gates
  (V4-08/V4-09, `currentTime === 0` after `play()`) fail intermittently;
  V4-08/V4-09 were excluded from one P6-004 regression table, eroding
  verification trust (R-11). D7-05 narrows the boundary; elimination work
  inside Chromium is still owed.
- TD-006 (LOW/NO): upload-progress UI behavior attested by inspection
  only; no retained real-browser proof of visible progress and
  no-premature-success.
- TD-013 (LOW/NO): pre-existing `showRenameModal is not defined` console
  error on the transcript workspace; predates Phase 3/4.
- Production gate G-11 requires the supported matrix green with
  deterministic verification.

## 4. Binding Decisions / ADRs

- D7-05 = OPTION A (Chromium-only initial support) —
  `DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026. Firefox/Safari remain
  best-effort unless newly promoted. Do not reopen.
- DC-01 (cross-phase browser verification governance; ADR-025): browser
  verification required where browser behavior is material; browser
  evidence never replaces concurrency/persistence/authorization/queue/
  real-service evidence; a browser double is never proof of a real
  backend path.
- `PHASE7-SCOPE-CONTRACT.md` §§B–F; production gate G-11.

## 5. Dependencies

- Hard prerequisites (satisfied at policy level):
  - D7-05 resolved (OPTION A); scope contract adopted.
  - DC-01 governance in force; Playwright harness precedent
    (`verification/playwright*.config.js`, per-task configs, auth-setup
    and seed scripts) exists and is Chromium-based.
- Soft dependencies: none — this task consumes only the closed Phase
  1–6 application surfaces.
- Downstream: P7-012 gate G-11.
- Parallel-safe: P7-003 and P7-008 (no shared files or semantics; needs
  only a running app instance, which the dev/test stack already
  provides).

## 6. In Scope

1. Supported-matrix declaration: Chromium-based browsers are the
   formally supported production family; Firefox and Safari/WebKit are
   best-effort/unsupported. User-facing support statement updated
   accordingly (docs/support copy; no code behavior gated on UA unless
   reviewed).
2. Playwright project configuration: production-gate browser suites run
   the Chromium project; configuration expectations documented
   (single-browser project, `workers: 1`, `retries: 0` for gate runs,
   headed-out headless with the established launch flags). Non-Chromium
   projects must not gate production readiness.
3. TD-005 elimination inside Chromium: V4-08/V4-09 playback-start flake
   root-caused within the supported matrix (timing-margin, autoplay
   policy, media readiness) and fixed or deterministically accommodated
   with a recorded rationale; the exclusion-table practice ends — the
   production gate runs the full Chromium matrix with no silent skips.
   Any remaining accommodation (e.g. documented retry-with-reason) must
   be explicit in the gate record, never a silent exclusion.
4. TD-006 evidence capture: retained real-Chromium proof for the four
   deferred upload-progress scenarios (visible progress; `Finalizing…`
   only at 100%; completion only on server-confirmed redirect;
   failure/interrupted states) per `resources/views/media/upload.blade.php`
   behavior and P2-003 AC 25.
5. TD-013 fix: resolve the `showRenameModal` console error with a
   regression test asserting a clean console on the transcript workspace.
6. Regression coverage across existing surfaces on Chromium: upload,
   media show/playback, transcript workspace (playback, navigation,
   search/filter, editing, comparison, revision history, export entry
   points), translation views where applicable.
7. Future-expansion criteria: the documented conditions for promoting
   Firefox and later WebKit (separate HPO decision; Firefox-specific
   failure remediation; codec/quirks handling; stabilized multi-browser
   matrix) — criteria only, no implementation.

## 7. Explicit Non-Scope

- Fixing Firefox/WebKit-specific defects (excluded from the supported
  matrix; their historical failures are preserved, not repaired, unless
  the shared root cause also affects Chromium — in which case the
  Chromium fix is in scope and the Firefox/WebKit effect is reported, not
  claimed).
- Claiming non-Chromium readiness: nothing in this task promotes Firefox
  or Safari to supported.
- Deleting or rewriting historical non-Chromium evidence or review
  findings (TD-005 history, V4-08/V4-09 records, P6-004 INFO carry-forward
  stay intact).
- New product UX, accessibility redesign, or visual overhaul beyond the
  existing V4-xx gate expectations.
- TD-008 suite-hygiene (no task owns it; opportunistic fixes allowed but
  must not expand this task's scope or gate its closure).
- Backend/concurrency/persistence evidence (DC-01: browser evidence never
  substitutes for it).

## 8. Architecture / Domain Contract

- Browser layer is verification-only: no application behavior may be
  changed to "fix" a test except the TD-013 defect fix and genuine
  Chromium-facing bugs with reviewed root causes; timing-margin
  accommodations must be explicit and recorded.
- Matrix rule: Chromium project = production gate; any Firefox/WebKit
  project = informational only and clearly labeled non-gating.
- Historical evidence rule: past exclusions and flakes stay in the audit
  trail with their original verdicts; this task adds new green evidence,
  it does not rewrite old red evidence.

## 9. Detailed Implementation Requirements

1. Matrix declaration artifact (support statement + gate-project mapping)
   referencing D7-05/ADR-026.
2. Playwright configuration audit: every production-gate config runs the
   Chromium project as specified in §6.2; non-gating projects labeled.
3. V4-08/V4-09 work: root-cause note + fix/accommodation + full-matrix
   Chromium gate run with zero silent skips; exclusion-table practice
   removed where it hid the flake.
4. TD-006: four-scenario Chromium specs with retained results in
   `verification/` following existing naming/retention precedent.
5. TD-013: fix + console-clean regression spec on the workspace.
6. Surface regression pass on Chromium covering §6.6; results retained.
7. Expansion criteria documented (§6.7).

## 10. Failure / Recovery Semantics

- Flake recurrence: on any gate-run flake, retain the trace/screenshot
  artifact, record the occurrence against TD-005 history, and re-run the
  affected spec explicitly (no silent suite-level retry masking); three
  unexplained recurrences reopen diagnosis rather than hardening the
  accommodation.
- Browser-binary drift (Playwright/Chromium version change): pin and
  record versions with gate evidence; version bumps re-run the matrix.
- TD-013 fix regression: console-clean spec fails the suite on any new
  console error on the covered surface.

## 11. Security / Privacy Requirements

- Auth-state fixtures follow existing precedent (seeded test users only);
  no production credentials in browser artifacts.
- Retained traces/screenshots must not contain real user media; use
  fixtures.
- No new publicly reachable surface is introduced by this task.

## 12. Observability / Operations Requirements

- Gate-run results retained in `verification/` with environment, browser/
  Playwright versions, and pass/fail per spec — consumable by the P7-012
  gate without re-execution archaeology.
- Flake-occurrence log maintained against TD-005 until the elimination
  is independently VERIFIED.

## 13. Acceptance Criteria

- AC1: Supported-matrix declaration exists: Chromium-only supported;
  Firefox/Safari best-effort; no non-Chromium support claim anywhere in
  user-facing or gate records.
- AC2: Production-gate browser suites run the Chromium project per §6.2;
  no non-Chromium project gates readiness.
- AC3: V4-08/V4-09 pass deterministically in the Chromium gate run with
  no silent skips or exclusions; root-cause/accommodation rationale
  recorded.
- AC4: TD-006 four upload-progress scenarios proven on Chromium with
  retained evidence.
- AC5: TD-013 fixed; console-clean regression spec green on the
  transcript workspace.
- AC6: Surface regression pass (§6.6) green on Chromium with retained
  results.
- AC7: Firefox/WebKit historical evidence preserved unmodified; no
  fixed-outside-matrix claims.
- AC8: Expansion criteria for Firefox/WebKit documented.
- AC9: Full PHP regression suite green; Pint clean; PHPStan 0 errors; no
  application-behavior change beyond the TD-013 fix (audited by diff).

## 14. Test / Verification Requirements

- Automated: TD-013 console-clean spec; TD-006 four specs; V4-08/V4-09
  deterministic gate specs; surface regression specs — all Chromium.
- Integration: gate specs run against the full stack (real app +
  real browser); seeded fixtures allowed for regression, never
  misrepresented as production proof.
- Failure-path: flake recurrence handling (§10) demonstrated by record,
  not by induced flakiness; failure/interrupted upload states covered in
  TD-006 specs.
- Regression: full PHP suite; Phase 6 browser suites re-run green on
  Chromium (P6-003/P6-004/P6-006/P6-007/P6-008 behaviors intact).
- Manual/operational: reviewer reproduces the Chromium gate run from
  retained artifacts + commands; versions recorded.

## 15. Technical Debt Mapping

- TD-005 (MEDIUM/HPO_DECISION_REQUIRED): owner-policy resolved by
  D7-05/A (narrowed boundary); this task is the elimination owner inside
  Chromium. May move toward CLOSED only with implementation +
  independent VERIFIED evidence, by HPO closure. Non-Chromium flake
  history is preserved, not claimed fixed.
- TD-006 (LOW/NO): evidence owner — this task captures the four
  scenarios. May close with the retained evidence, by HPO closure.
- TD-013 (LOW/NO): fix owner — this task. May close with fix +
  regression spec, by HPO closure.
- TD-008: explicitly not owned (suite-hygiene follow-up, no task);
  opportunistic fixes permitted without gating this task.

## 16. Risks / Regression Concerns

- "Fixing" the flake by weakening the assertion (e.g. dropping the
  playback-start check) would destroy the gate's meaning → any
  accommodation needs reviewer-agreed rationale and preserved
  sensitivity to real regressions.
- Chromium-version drift reintroducing flake → version pinning + matrix
  re-run discipline.
- TD-013 fix touching shared workspace JS risks P6-003/P6-004/P6-007
  behavior → Phase 6 browser suites re-run as regression (AC9).
- Scope creep into Firefox/WebKit repair → §7 boundary enforced at
  review.

## 17. Completion Evidence Required

- Contract diff + implementation diff + Chromium gate results (per-spec
  pass/fail, versions, retained `verification/` artifacts) + TD-006
  evidence + TD-013 fix + full suite counts + Pint + PHPStan + matrix
  declaration, referenced from the builder report.

## 18. Reviewer Checklist

- [ ] Chromium-only gate; non-Chromium projects non-gating and labeled.
- [ ] No silent skips/exclusions in the production gate run.
- [ ] V4-08/V4-09 rationale reviewed; gate sensitivity preserved.
- [ ] Historical Firefox/WebKit evidence untouched; no fixed-by-exclusion
  claims.
- [ ] TD-006/TD-013 evidence complete; TD-008 not smuggled into scope.
- [ ] Application diff audited (TD-013 fix only, plus reviewed
  Chromium-facing bugs if any).
- [ ] Phase 6 browser regressions green.
- [ ] Evidence independently reproducible.

## 19. State Transition Rule

- `BACKLOG / CONTRACT_REQUIRED → READY`: requires (a) this canonical
  contract, (b) dependency reconciliation recorded (D7-05 resolved;
  scope adopted; DC-01 in force), and (c) an explicit HPO READY
  promotion (`DECISION-P7-010-READY-001` or batch equivalent). Contract
  authoring alone does not promote.
- `READY → IN_PROGRESS → REVIEW → VERIFIED → DONE`: per
  `.ai/guidelines/orchestration-policy.md` — HPO assigns (or authorizes
  self-assignment); OpenCode implements; Claude Code reviews (VERIFIED /
  CHANGES_REQUESTED, max 3 cycles then BLOCKED); HPO closes VERIFIED as
  DONE. OpenCode must not self-verify or self-close. READY does not equal
  execution authorization: IN_PROGRESS additionally requires the separate
  HPO Wave 1 execution authorization.
