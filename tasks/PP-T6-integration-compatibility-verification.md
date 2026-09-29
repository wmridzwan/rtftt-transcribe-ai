# PP-T6 — Integration & Phase 1–7 Compatibility Verification (FINAL_GATE_ONLY pattern)

## 1. Status

`DONE — CLOSED BY HPO (DECISION-PP-T6-CLOSURE-001)`

Step-2 execution record (2026-09-28): lifecycle `READY → IN_PROGRESS →
REVIEW` under `DECISION-PP-T6-EXECUTION-AUTHORIZATION-001`, strictly within
the reconciled contract. Builder verification: focused 49/49 (after
test-only corrective cycle PP-T6-REV-01, LOW, re-verified) + 106/106;
Phase-2 92/92; Phase-3 120/120; Phase-5 315/315; Phase-6 251/251 + 12/12 +
23/23; LogContext 6/6 isolated (one preserved A-01 batch strike, classified
pre-existing); full suite 1253 (1248 passed, 5 pre-existing skips,
0 failures); Pint clean; PHPStan 0 errors; browser N/A justified; runtime
diff clean (one test file + governance/verification only). AC1–AC8 PASS on
executed evidence. Evidence:
`verification/pp-t6/PP-T6-FINAL-GATE-EVIDENCE.md`. Builder claims neither
VERIFIED nor DONE.

Independent review (2026-09-28, `reviews/PP-T6-INDEPENDENT-REVIEW.md`):
`PP-T6 = VERIFIED` on independently rerun evidence (focused 155/155; full
suite 1253/1248/5/0 reproduced; scope audit clean; no BLOCKER/HIGH/MEDIUM).
Lifecycle `REVIEW → VERIFIED` evidenced by the review artifact.

Closure (2026-09-28, HPO `DECISION-PP-T6-CLOSURE-001`): HPO accepts PP-T6;
canonical transition `VERIFIED → DONE`. Full lifecycle history:
`READY → IN_PROGRESS → REVIEW → VERIFIED → DONE`. Closure authorizes no
post-PP-T6 work. Processing Provider track = CLOSED (final gate passed).

Step-1 record (2026-09-28; governance/readiness only — no implementation, no
gate execution): readiness review `reviews/PP-T6-READINESS-REVIEW.md`
returned `READY-ELIGIBLE` (no unresolved BLOCKER/HIGH/MEDIUM) after inline
reconciliation (stale T4-exclusion in §§4/14/19 corrected to require T4
evidence per `DECISION-PP-T4-DEFERMENT-RELEASE-001` +
`DECISION-PP-T4-CLOSURE-001`; §13 ACs assigned AC1–AC8 with per-assert pass
rules and dual-domain AC6; §14 mandatory T1–T5 suite/command list; §17
rollback-rehearsal clarification; §18 frozen-scope consolidation; no scope or
ADR change, no closed contract reopened, review artifact itself unchanged).
Promotion + execution authorization recorded in
`DECISION-PP-T6-EXECUTION-AUTHORIZATION-001`. Final state: `PP-T6 = READY —
EXECUTION AUTHORIZED` (Step 2 not started; gate NOT executed; PP-T6 is not
VERIFIED and not DONE).

## 2. Objective

Serve as the Wave-1 integration gate: prove provider extension preserves Phase 1–7 contracts and that the external path (fixtures) normalizes, maps errors, recomposes chunks, identifies itself, honors kill-switch, and never silently falls back. No feature development.

## 3. Why

T1–T3–T5 change the seams around frozen domains; only a dedicated verification gate (P6-009/P7 precedent) can certify no silent contract rewrite before any enablement conversation.

## 4. Dependencies

Implementation requires applicable Wave-1 tasks complete. Expected chain: T1 → T2 → T3 → T4 → T5 → T6. T6 executes last. (Reconciled 2026-09-28, Step 1: the authored draft predates PP-T4 DONE and records "(T4 excluded)" under the ADR-027 Wave-1 deferment; that deferment was released by `DECISION-PP-T4-DEFERMENT-RELEASE-001` and PP-T4 closed DONE under `DECISION-PP-T4-CLOSURE-001` with dual-domain PP-T5 controls. A gate that excluded T4 would leave the translation reference path and half of PP-T5's dual-domain scope unverified; T4 evidence is therefore required, not optional.)

## 5. Inputs / frozen contracts

All T1–T3–T5 contracts + acceptance suites; Phase 2 (media/checksum/storage/500 MiB), Phase 3 (normalization/lifecycle), Phase 5 (NLLB identity/lifecycle/staleness), Phase 6 (revision/invalidation/export), Phase 7 (observability/retention/storage/backup); `PHASE17-COMPATIBILITY-REVIEW.md`.

## 6. Scope

Compatibility matrix execution (Phase-by-Phase asserts below); external-path fixture verification (normalization, error map, chunk recomposition, identity, kill-switch, no-fallback); regression gate (targeted + Phase 3/5/6 + relevant Phase 7 + full suite + Pint + PHPStan + browser where affected); verdict report with historical FAIL preservation.

## 7. Non-scope

Feature work; vendor approval (live-vendor proof needs separate provider-specific gate); spike execution; prod traffic; durable schema additions.

## 8. Architecture / behavioral contract

T6 is read-mostly verification harness + report: drives existing + new suites, fault-injection (synthetic), kill-switch rehearsal, log/evidence queries. Asserts behavior, changes nothing. Any FAIL → file remediation task, preserve history (P6-009 precedent).

## 9. Failure semantics

Gate verdict PASS only on all-green fresh run; any mapping/normalization/identity/fallback breach → FAIL with finding ID (F-001 style), severity, evidence path. T6 itself never retries or patches product code.

## 10. Security/privacy constraints

Zero live egress during T6 (fixtures/fakes only); no credentials required; no customer media transmitted; secret-absence assertions part of gate.

## 11. Observability/audit requirements

Gate report records: suite versions, seed/config, per-Phase verdicts, external-path proofs, kill-switch rehearsal log, identity-field queries, alert firings. Evidence paths cited (e.g., `verification/pp-t6/...`).

## 12. Compatibility requirements (the gate)

- Phase 2: media identity, checksum, private storage, upload lifecycle, 500 MiB limit unchanged (assert each).
- Phase 3: canonical normalization, segment/timestamp/language semantics, lifecycle, self-hosted behavior unchanged.
- Phase 5: translation NLLB/self-hosted, identity unchanged, no stale regression.
- Phase 6: active-revision authority, edit invalidation, historical preservation, export correctness (active revision, F-001 guard), segment/revision identity.
- Phase 7: provider observability present, retention/storage/backup unaffected, diagnostics truthful.

## 13. Acceptance criteria

Each AC passes only on fresh Step-2 evidence (no carried-over results); every assert below is independently reproducible by the reviewer. FAIL on any single assert.

- [ ] AC1 — Phase-2 asserts pass: media identity, checksum, private storage, upload lifecycle, and 500 MiB limit each asserted unchanged.
- [ ] AC2 — Phase-3 asserts pass: canonical normalization, segment/timestamp/language semantics, lifecycle, and self-hosted parity each asserted unchanged.
- [ ] AC3 — Phase-5 asserts pass: NLLB canonical/self-hosted identity unchanged, translation lifecycle/staleness intact, no stale regression.
- [ ] AC4 — Phase-6 asserts pass: active-revision authority, edit invalidation, historical preservation, export correctness (active revision, F-001 guard), segment/revision identity.
- [ ] AC5 — Phase-7 asserts pass: provider observability present, retention/storage/backup unaffected, diagnostics truthful.
- [ ] AC6 — External-path fixture proofs pass in BOTH domains (transcription T3 + translation T4): normalization, error map, recomposition (transcription chunk fan-out; translation single-shot 1:1 alignment), identity, kill-switch, and no-fallback — one assert each per domain.
- [ ] AC7 — Regression gate green: targeted PP suites + Phase 3/5/6 + relevant Phase 7 + full suite + Pint + PHPStan (+ browser where affected; N/A requires stated justification, never silent skip).
- [ ] AC8 — Verdict report published at `verification/pp-t6/` with suite versions, seed/config, per-Phase verdicts, external-path proofs, kill-switch rehearsal log, identity-field queries, and alert firings with evidence paths; any FAIL preserved with a remediation task filed; no vendor production-approval claim without live evidence.

## 14. Testing requirements

Gate executes (not authors) the suites defined in the T1–T5 contracts (reconciled 2026-09-28, Step 1: the authored draft cited T1–T3–T5 only; T4 evidence is required per §4); adds only glue/asserts/report. Mandatory surface (green-by-skipping prohibited; every skip justified in the verdict report):

- PP-T1: `tests/Feature/SelfHostedTranscriptionProviderTest.php`, `tests/Feature/Translation/SelfHostedTranslationProviderTest.php`, plus the contract/parity suites cited in the T1 contract.
- PP-T2: `tests/Feature/Transcription/TranscriptionProviderResolutionTest.php`, `tests/Feature/Translation/TranslationProviderResolutionTest.php`, both `ReferenceExternalProviderResolutionTest.php` files (Transcription + Translation).
- PP-T3: `tests/Feature/Transcription/ReferenceExternalTranscriptionProviderTest.php`.
- PP-T4: `tests/Feature/Translation/ReferenceExternalTranslationProviderTest.php`.
- PP-T5: `tests/Feature/ProcessingProvider/` (all 5 files).
- Phase regression: Phase 3/5/6 suites + relevant Phase 7 suites as cited in T1–T5 §16; full suite via `php artisan test --compact` (or `vendor/bin/pest`); `composer lint:check` (Pint); `composer types:check` (PHPStan).
- Browser (Playwright, ADR-021): only where Step-2 glue touches UI-affecting behavior; PP-T6 is expected to require no browser run — N/A must be stated with this justification, not silently omitted.

Fresh-run evidence required; reviewer reproduces or explicitly marks implementer-reported vs reviewer-reproduced (ADR-011).

## 15. Risks

Gate-as-development (scope creep) → charter guard. Fixture-reality gap → explicit live-evidence disclaimer. Green-by-skipping → mandatory test list + skip justification rule.

## 16. Regression concerns

T6 is the regression backstop; it must itself not modify product behavior. Harness changes isolated to `verification/` + gate report.

## 17. Rollback expectations

No product rollback (the gate ships no product change). The PP-T5 kill-switch rollback rehearsal (runbook procedure → self-hosted-only restoration) is exercised and its rehearsal log reported as part of AC8 evidence.

## 18. Governance / authorization boundary

CONTRACT AUTHORING ONLY. FINAL_GATE_ONLY execution pattern: runs once per completed wave, not per task. Independent review required. No implementation.

Step-2 frozen / protected scope (reconciled 2026-09-28, Step 1): the gate must not alter `TranscriptionProvider` / `TranslationProvider` signatures; self-hosted adapters; resolver selection/fail-closed/kill-switch semantics; canonical config keys (`transcription.provider_selection`, `translation.provider_selection`, `RTFTT_PROCESSING_EXTERNAL_KILL_SWITCH`); invocation `requestId` minting/reuse and job `httpRequestId`; chunk/execution-id execution-only semantics; queue/dispatch, manual-only retry, and 300/330/420 timeout semantics; adapter-internal safety ceilings; secrets handling (env-only, fail-closed, never logged/persisted); storage/schema (no migration); Phase 1–7 frozen contracts; or any PP-T1–PP-T5 closure decision. Any required product change → STOP, file a remediation task, preserve history (P6-009 precedent); the gate itself never patches product code.

## 19. READY eligibility conditions

Eligible only after T1/T2/T3/T4/T5 implementation evidence exists and is reviewable (reconciled 2026-09-28, Step 1: T4 added per §4; the authored draft cited T1/T2/T3/T5 only). HPO releases T6 explicitly (do not self-release).

## 20. Exact next legal action

Begin PP-T6 — Step 2: Execute Final Gate, Independently Verify & Close, only
when explicitly instructed (under `DECISION-PP-T6-EXECUTION-AUTHORIZATION-001`,
strictly within this reconciled contract; builder moves `READY → IN_PROGRESS
→ REVIEW`; no VERIFIED/DONE claim by the builder).
