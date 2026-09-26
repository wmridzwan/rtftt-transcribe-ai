# Browser Support Matrix (P7-010)

Status: AUTHORITATIVE for production browser scope. Binding decision:
D7-05 = Chromium-only initial support (`DECISION-PHASE7-OWNER-DECISIONS-001`;
ADR-026).

## 1. Supported matrix (initial production release)

| Family | Status | Gate |
|---|---|---|
| Chromium-based browsers (Chrome, Edge, Brave, Opera current) | **Supported** | Chromium Playwright project must be green with zero silent skips |
| Firefox | Best-effort / unsupported | Informational only; never gates readiness |
| Safari / WebKit | Best-effort / unsupported | Informational only; never gates readiness |

## 2. Playwright configuration expectations (production gate)

Every production-gate browser run must satisfy ALL of:

- exactly one project: `chromium` (Desktop Chrome device, headless);
- `workers: 1`, `fullyParallel: false` (media-clock determinism);
- `retries: 0` (a retry would mask the TD-005 flake class);
- headless launch flags include
  `--autoplay-policy=no-user-gesture-required --mute-audio --no-sandbox`;
- results retained under `verification/` with runner/browser versions.

Audit 2026-09-26 (P7-010 AC2): all 14 `verification/playwright.*.config.js`
harnesses satisfy the above with no change required. No non-Chromium
project gates readiness anywhere.

## 3. TD-005 disposition (inside the supported matrix)

Historical V4-08/V4-09 playback-start flake (`currentTime === 0` after
`play()`) root-caused as a buffering race: `play()` issued at
`readyState >= 1` (metadata only) plus a fixed 1500ms sleep. Fixed
deterministically in `verification/p4-006/phase4-integration.spec.js`
(`waitForPlaybackStarted`): wait for `readyState >= 2`, await the
`play()` promise (rejection fails loudly with its reason), poll
`currentTime > 0` with a 15s budget. Gate sensitivity preserved — the
assertion is strictly stronger than before. The exclusion-table practice
is ended: the production gate runs the full Chromium matrix with no
silent skips. Flake recurrence handling: retain trace/screenshot, record
against TD-005 history, explicit re-run (never suite-level retry
masking); three unexplained recurrences reopen diagnosis.

## 4. Non-Chromium history (preserved, not repaired)

Firefox/WebKit historical failures and the P6-004 INFO carry-forward
(headless playback-start environmental flake; V4-13 locator scoping) stay
in the audit trail with original verdicts. Nothing in P7-010 promotes
them, repairs them, or represents them as fixed by exclusion.

## 5. Expansion criteria (Firefox, then WebKit)

Promotion of an additional engine requires ALL of: a separate HPO
decision; remediation of that engine's specific failures (codecs, quirks,
timing margins); a stabilized multi-browser matrix run (workers: 1 per
engine, retries: 0); updated user-facing support statement. Criteria
only — no implementation in P7-010.
