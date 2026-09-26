# P7-010 Builder Report — Browser Support Matrix + Flake Elimination

Task: `tasks/P7-010-browser-support-matrix-flake-elimination.md` (READY → IN_PROGRESS 2026-09-26, authorized `DECISION-PHASE7-WAVE1-EXECUTION-AUTHORIZATION-001`).
Builder: OpenCode. No self-verification; submitted for independent review.

## Files changed (new unless noted)

- `docs/BROWSER-SUPPORT-MATRIX.md` (new) — Chromium-only declaration, gate config expectations, TD-005 disposition + recurrence handling, preserved non-Chromium history, expansion criteria.
- `resources/views/transcriptions/show.blade.php` (edit, TD-013) — rename modal visibility returned to native Flux (`flux:modal.trigger` + `modal-show` event + `:show` for errors/`?rename=1`); custom cross-scope Alpine (`showRenameModal` read/write from teleported modal scope) removed; `?rename=1` opens via root-scope `$nextTick` dispatch of the native event; root server marker retained for the existing `TranscriptionManagementTest` assertion. Pre-existing residue in the same file preserved (edit is adjacent-only).
- `verification/p4-006/phase4-integration.spec.js` (edit) — `waitForPlaybackStarted` helper (readyState ≥ 2 → awaited `play()` → poll `currentTime > 0`, 15s budget) applied to V4-08/V4-09; V4-13 locator scoped to visible `[data-segment-row]` (spec bug fix; no app change).
- `verification/p7-010/` (new): `upload-progress.spec.js` (UPL-01…04, TD-006), `console-clean.spec.js` (CON-01…03, TD-013); `p7-010-seed.php`, `p7-010-fixtures.json`, `p7-010-auth.setup.js`; `playwright.p7-010.config.js` (chromium-only, workers 1, retries 0, port 8127).
- Prior `p4-006-browser-results.json` backed up to `*.pre-p7010.json` before the re-run (history preserved; artifacts/ is gitignored).

No Firefox/WebKit repair, promotion, or claim. No historical evidence rewritten (V4-08/09 fix strengthens the assertion; old results backed up).

## AC results

| AC | Verdict | Evidence |
|---|---|---|
| AC1 matrix declared, no non-Chromium support claim | PASS | Matrix doc §1; support statement; user-facing copy untouched except declaration |
| AC2 gate suites run Chromium project per spec | PASS | Audit: all 14 `playwright.*.config.js` chromium-only, workers 1, retries 0 — no change needed; new p7-010 config compliant |
| AC3 V4-08/V4-09 deterministic, no silent skips | PASS | `p4-006` re-run 14/14 on real Chromium (loopback Redis, seeded DB): V4-08/V4-09 green via `waitForPlaybackStarted`; retries 0; rationale recorded (buffering race at readyState 1 + fixed sleep) |
| AC4 TD-006 four scenarios with retained evidence | PASS | UPL-01…04 green (progress visible + no premature nav; 100%/Complete→redirect; native required-guard, no redirect; abort→interrupted, no success); `p7-010-upload-results.json` retained. Note: UPL-03 asserts the native `required` guard (spec corrected — custom-handler path unreachable for empty submit) |
| AC5 TD-013 fixed + console-clean spec | PASS | Fix as above; CON-01/02/03 green with zero console + zero page errors; `p7-010-console-results.json` retained |
| AC6 surface regression green with retained results | PASS | p4-006 full matrix 14/14 (workspace, playback, seek, timestamps, search, copy, export, multilingual, no-speech, coexistence) |
| AC7 non-Chromium history preserved, no fixed-by-exclusion claims | PASS | No non-Chromium file touched; V4-13 fix is Chromium-spec scoping with the pre-existing interaction cited, not a product claim |
| AC8 expansion criteria documented | PASS | Matrix doc §5 (HPO decision + remediation + stabilized matrix + support statement) |
| AC9 full PHP suite green, Pint, PHPStan, app-diff audit | PASS | Full suite 927/926+1 pre-existing skip; Pint clean; PHPStan 0; app diff = modal visibility only (adjacent to, not altering, Phase 6 residue) |

## Test / verification matrix

- Playwright 1.63.0, real Chromium headless, `p7-010` 7/7, `p4-006` 14/14 (servers: 8127/p7-010 DB, 8123/existing p4-006 DB as-found, no reseed needed).
- Environment: loopback Redis live (translation override in local `.env` unaffected — no jobs dispatched by browser specs).

## TD mapping

- TD-005: elimination inside Chromium implemented + evidenced (this report + results JSON). Closure follows HPO lifecycle — NOT marked closed here. Recurrence handling documented (matrix doc §3).
- TD-006: evidence captured. TD-013: fix + regression spec delivered. TD-008: untouched (one opportunistic spec-scope fix is V4-13's documented spec bug, not suite hygiene). NOT marked closed.

## Known limitations (for reviewer)

1. V4-13's pre-existing exclusion-table context (P6-004 INFO) is superseded by this deterministic run; history preserved via backup + review trail.
2. `test-results/` and `verification/artifacts/` are gitignored by repo policy; retained locally for the reviewer to reproduce (`playwright.p7-010.config.js`, `playwright.p4-006.config.js` + seeds).

## State

IN_PROGRESS → REVIEW on handoff. No VERIFIED/DONE claimed.
