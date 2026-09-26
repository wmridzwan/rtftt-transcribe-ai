# Technical Debt Register — Canonical

Date: 2026-09-24 (STEP B audit; supersedes the 7-row draft of the same date)
Authority: `AGENTS.md` (canonical truth); Step A governance reconciliation
(`CURRENT_STATE.md` § Governance Reconciliation; `docs/GOVERNANCE-RECONCILIATION-REPORT.md`).
Status: CANONICAL — this is the single authoritative debt list. It extends, not
replaces, the closure carry-forward decisions
(`DECISION-PHASE5-DEBT-CARRYFORWARD-001`,
`DECISION-PHASE6-7-DEBT-CARRYFORWARD-001`,
`DECISION-P6-004-INFO-CARRYFORWARD-001`).

## Rules

1. Other documents may reference a `TD-*` ID but must not restate a conflicting
   status. New debt gets the next `TD-*` ID with evidence links.
2. Production-blocker states (exact): `YES` / `NO` / `CONDITIONAL` /
   `HPO_DECISION_REQUIRED`.
   - `YES` only where evidence shows the issue can materially prevent safe or
     correct production use.
   - `CONDITIONAL` where the risk depends on the chosen deployment topology.
   - `HPO_DECISION_REQUIRED` where product policy or risk acceptance is needed.
3. Severity (independent of the word "deferred"): `CRITICAL` / `HIGH` / `MEDIUM`
   / `LOW`.
4. Status: `OPEN` / `MITIGATED` / `ACCEPTED` (HPO risk acceptance recorded) /
   `CLOSED` (evidence + HPO closure).
5. Phase 7 task IDs (P7-001/002/003/004/008/009/010/011/012) are **candidate**
   decomposition from `PHASE7-PLANNING.md` §G — NOT created, NOT authorized.
   Only P7-005 exists as an authored task file (DONE). Candidate IDs must not be
   treated as executable tasks.
6. Historical evidence is never rewritten to match stricter present
   requirements; gaps are recorded here with references to the historical
   artifact.

## Authority conflict recorded (not silently resolved)

STEP B §10 maps infrastructure owners onto P6-001..P6-007 (data store, queue,
deployment, security, monitoring, delivery gate, readiness gate). Repository
evidence contradicts this: the authorized Phase 6 is Advanced Transcript UX —
P6-001 editing domain, P6-002 revision persistence, P6-003 text editing, P6-004
timing, P6-005 split/merge, P6-006 navigation/filter, P6-007 comparison
(`AGENTS.md`; `DECISION-PHASE6-AUTHORIZATION-001`; ADR-025). There is no P6-006
"delivery/launch contract gate" task; the real gates are P6-009 (Phase 6
integration, FINAL_GATE_ONLY, not started) and P7-012 (production readiness,
not authorized). Owner mapping below therefore uses Phase 7 candidate tasks
per `PHASE7-PLANNING.md` §G. HPO decision HPO-B1 is required to formally
supersede the §10 mapping (see `docs/GOVERNANCE-RECONCILIATION-REPORT.md` §7
convention; recorded as an open decision in the final report).

## P7-005 interaction

P7-005 (DONE) owns the observability foundation: structured logging, request
correlation, job observability fields where available, diagnostics command,
runbook. TD-010 (metrics/tracing/alerting/device observability) is explicitly
beyond P7-005's non-scope (`tasks/P7-005-observability-foundation.md:71-78`) —
no duplicate ownership, P7-005 status unchanged, P7-005 not reviewed here.

## Summary matrix

| Debt ID | Title | Category | Severity | Production Blocker | Owner | Status |
|---|---|---|---|---|---|---|
| TD-001 | Full-chain real-model/no-fake E2E never proven | Evidence gap | HIGH | YES | Phase 7 → P7-009 + P7-012 (candidate) | OPEN |
| TD-002 | PHP upload limits default below 500 MiB boundary | Environment/config | HIGH | YES | Phase 7 → P7-001 + P7-008 + P7-009 (candidate) | OPEN |
| TD-003 | Queue default `database`, no production supervision; `sync` guard exemption | Queue/worker | HIGH | YES | Phase 7 → P7-003 (candidate; D7-02) | OPEN |
| TD-004 | Redis passwordless-by-default posture undecided | Security | MEDIUM | CONDITIONAL | Phase 7 → P7-001 + P7-003 (candidate) | OPEN |
| TD-005 | Playwright V4-08/V4-09 flake + selective exclusion | Test reliability | MEDIUM | HPO_DECISION_REQUIRED | Phase 7 → P7-010 (candidate; D7-05) | OPEN |
| TD-006 | Upload-progress browser-runtime evidence missing | UX evidence | LOW | NO | Phase 7 → P7-010 (candidate) | OPEN |
| TD-007 | Automated staging cleanup deferred (Option D) | Storage/ops | MEDIUM | HPO_DECISION_REQUIRED | Phase 7 → P7-011 + explicit Option D decision (candidate; D7-06) | OPEN |
| TD-008 | Order-dependent full-suite flakiness | Test reliability | MEDIUM | CONDITIONAL (pre-P7-012) | Dedicated suite-hygiene task before P7-012 (`DECISION-TD-008-REPRIORITIZATION-001`) | OPEN |
| TD-009 | Config-derived model identity + `und`→`eng_Latn` fallback assumption | Data integrity/assumption | MEDIUM | NO | Phase 5 (accepted); change needs new HPO decision | ACCEPTED |
| TD-010 | Metrics/tracing/alerting + `device` observability absent | Observability | LOW | NO | Phase 7 metrics/tracing wave if adopted (beyond P7-005) | OPEN |
| TD-011 | Streaming coverage gaps (bounded-stream, range-edge, zero-byte) | Test reliability | LOW | NO | Phase 7 → P7-004 test-hardening (candidate) | OPEN |
| TD-012 | `D:` volume silent-corruption hazard (local env) | Environment/config | LOW | NO | Environment runbook (`verification/p5-008/README.md`) | MITIGATED |
| TD-013 | Pre-existing `showRenameModal` console error | Defect | LOW | NO | Phase 7 → P7-010 (candidate) | OPEN |
| TD-014 | Retention staging-claim crash-recovery gap | Storage/ops | LOW | NO | Pre-P7-012 follow-up (P7-011 follow-on; no task exists) | OPEN |

## A. Must resolve before production launch

- TD-001 (YES/HIGH) — full-chain real-model E2E.
- TD-002 (YES/HIGH) — 500 MiB receiving contract in the deployed stack.
- TD-003 (YES/HIGH) — supervised production queue/worker topology.
- TD-004 (CONDITIONAL/MEDIUM) — resolves to YES if Redis leaves loopback or is
  shared; posture decision required regardless.
- TD-005 (HPO_DECISION_REQUIRED/MEDIUM) — HPO must rule whether full
  elimination precedes P7-012.
- TD-007 (HPO_DECISION_REQUIRED/MEDIUM) — explicit Option D outcome required.
- TD-008 (CONDITIONAL/MEDIUM) — dedicated suite-hygiene remediation must
  land before the P7-012 terminal gate (reprioritized
  `DECISION-TD-008-REPRIORITIZATION-001`); full-suite gates are
  unreliable at the observed 7-of-8 flake frequency.

## B. Can be scheduled after launch

- TD-006 (UX evidence), TD-009 (accepted
  assumption), TD-010 (observability enhancement), TD-011 (test hardening),
  TD-012 (mitigated environment note), TD-013 (minor defect fix).
- TD-014 excluded from this bucket: pre-P7-012 follow-up (retention
  crash-recovery reclaim before G-09 consumption).
- TD-008 excluded from this bucket: reprioritized pre-P7-012
  (`DECISION-TD-008-REPRIORITIZATION-001`).

## Detail

### TD-001 — Full-chain real-model/no-fake E2E never proven

- Description: No single continuous run proves real media upload → ingestion →
  real transcription engine → persisted transcript → translation → Transcript
  Workspace → delivery/export with real models and zero doubles.
- Evidence:
  - Worker unit tests mock the model: `worker/tests/test_transcription.py:16`.
  - P3-008 proved transcription with real `large-v3`, but the MP3 fixture was
    placed in storage, not driven through the HTTP upload endpoint
    (`PHASE3-P3-008-INTEGRATION-EVIDENCE.md` §§B–E).
  - P5-008 proved translation on seeded transcripts with real NLLB
    (`PHASE5-P5-008-INTEGRATION-EVIDENCE.md`, Canonical Real Gate; deterministic
    `p5-008-seed.php` fixtures).
  - Term `fake-whisper` does not exist in the repository; "fake transcription
    provider" as a component does not exist either — doubles appear as mocked
    unit tests and seeded fixtures only.
- Origin / related task: Phase 3/5 verification boundaries; readiness item for
  P7-012.
- Current behaviour: component gates pass; chain unproven.
- Expected production behaviour: one retained gate run of the unbroken chain
  per `docs/PRODUCTION_READINESS_GATE.md` G-12.
- Risk: inference latency, capacity, and failure behavior unknown end to end.
- User impact: launch without proof risks production transcription/translation
  failures discovered by users.
- Operational impact: capacity planning has no end-to-end measurement.
- Security impact: none directly.
- Data integrity impact: unproven persistence handoffs between stages.
- Production blocker: YES. Severity: HIGH.
- Recommended owner: Phase 7 → candidate P7-009 + P7-012.
- Dependency: TD-002 and TD-003 resolved in the deployed stack first.
- Legal next action: schedule the G-12 gate run after HPO-08 (Phase 7
  authorization). Do not execute in Step B.
- STEP C (2026-09-25) PARTIAL evidence: `verification/REAL-MODEL-FULL-CHAIN-E2E.md`
  proves the integrated chain with a real faster-whisper engine and zero
  fakes/doubles/seeds, but with model `base` substituted for canonical
  `large-v3` (weights unprovisonable in-session) — TD-001 stays OPEN.
- STEP C2 (2026-09-25) canonical-model PASS:
  `verification/LARGE-V3-FULL-CHAIN-E2E.md` proves actual `large-v3` inference
  (runtime identity: env + weight-file access at inference time + 121 s timing
  signature + quality differential; persisted label truthful for C2 lineage),
  same-run lineage, and downstream compatibility. Overall TD-001 REMAINS OPEN
  pending deployed-stack gates (G-01..G-13 entry criteria unmet); G-12 is
  PARTIAL (prerequisite evidence supplied, not satisfied).
- Status: OPEN.

### TD-002 — PHP upload limits default below 500 MiB boundary

- Description: Default PHP/web-server receiving limits cannot accept the
  approved 500 MiB (524,288,000-byte) product boundary.
- Evidence: `DECISIONS.md:324` (CLI `upload_max_filesize=2M`,
  `post_max_size=8M`); `config/media.php:16` (exact boundary);
  `CURRENT_STATE.md` Phase 2 infrastructure + DEFERRED DEPLOYMENT REQUIREMENT
  (no retained 500 MiB multipart proof through the production HTTP stack).
  Dev-path verification reached 512M/520M (`tasks/P2-007-*.md` receiving-path
  table) — valid for dev only.
- Origin: Phase 2 (P2-001/P2-003/P2-007).
- Current behaviour: application validation enforces the exact boundary, but
  files above the runtime limit are rejected before validation.
- Expected production behaviour: deployed stack configured for 500 MiB plus
  overhead, with a retained multipart proof (G-01).
- Risk: 500 MiB contract unmeetable in any stack inheriting defaults.
- User impact: large legitimate uploads rejected.
- Operational impact: deployment checklist incomplete.
- Security impact: raising limits must be paired with abuse limits (P7-006).
- Data integrity impact: none.
- Production blocker: YES. Severity: HIGH.
- Recommended owner: Phase 7 → candidate P7-001 + P7-008 + P7-009.
- Dependency: deployed-stack topology decision.
- Legal next action: fail-fast env validation + retained 500 MiB proof in the
  deployed stack.
- Status: OPEN.

### TD-003 — Queue default `database`, no production supervision; `sync` guard exemption

- Description: Default queue connection is `database` with no supervisor,
  recovery, dead-letter, or failed-job visibility; the `sync` driver exists and
  is exempt from the translation `retry_after` boot guard.
- Evidence: `.env.example:40`; `config/queue.php:16`; `PHASE7-PLANNING.md` §A/§C;
  `config/queue.php:34-36` (`sync` driver; used only as a test override in
  `TranslationOperationalPreflightTest.php:43`);
  `worker/TRANSLATION-OPERATIONS.md:78-80` (drivers without `retry_after`,
  e.g. `sync`, exempt — selecting `sync` silently voids the provider < job <
  retry_after invariant and runs long jobs inline).
- Origin: Phase 3/5 queue design; production topology deferred to Phase 7.
- Current behaviour: dev-correct; production-unsupervised.
- Expected production behaviour: Redis-backed supervised topology with
  recovery, dead-letter, failed-job visibility; `sync` prohibited for
  transcription/translation queues (HPO-05).
- Risk: stuck/abandoned/invisible jobs; (`sync`) lost timeout and
  stale-recovery guarantees.
- User impact: jobs silently stall; retries unpredictable.
- Operational impact: no failed-job visibility; R-10.
- Security impact: none directly.
- Data integrity impact: redelivery/duplication risk without fencing evidence
  under the production queue.
- Production blocker: YES. Severity: HIGH.
- Recommended owner: Phase 7 → candidate P7-003 (D7-02).
- Dependency: D7-02 queue/worker decision; HPO-05 sync prohibition.
- Legal next action: decide D7-02, certify Redis, stand up supervision; record
  the `sync` prohibition.
- HPO D7 resolution (2026-09-26): D7-02 = OPTION A (Redis +
  systemd/supervisord, no Horizon) via `DECISION-PHASE7-OWNER-DECISIONS-001`
  / ADR-026. Owner-policy direction is SET; implementation and verification
  remain OPEN in candidate P7-003. This entry is not marked remediated or
  VERIFIED by the decision.
- Wave 1 implementation VERIFIED + DONE (2026-09-26): P7-003 closed
  (`DECISION-P7-003-CLOSURE-001`; `reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md`
  §B — all 10 ACs independently confirmed; full suite/Pint/PHPStan
  reproduced). Evidence recorded; Status stays OPEN pending production-gate
  proof at P7-012 (G-03). This closure does not mark the debt CLOSED.
- Status: OPEN.

### TD-004 — Redis passwordless-by-default posture undecided

- Description: Redis ships with `REDIS_PASSWORD=null`; no per-environment
  auth/network/TLS posture has been decided.
- Evidence: `.env.example:57`; `.env:47` (local, untracked); config consumes
  `REDIS_PASSWORD` when provided (`config/database.php:156-180`); gates ran on
  loopback `127.0.0.1:6379` (P3-008 evidence §A; `PHASE5-CLOSURE-REPORT.md`).
- Origin: Phase 3/5 integration environments.
- Current behaviour: loopback-only usage; acceptable for local dev.
- Expected production behaviour: decided posture (auth/network/TLS, version
  certification) recorded in P7-001/P7-003.
- Risk: passwordless Redis relies entirely on network isolation (R-14).
- User impact: none directly.
- Operational impact: secrets-handling gap.
- Security impact: material exposure if Redis leaves loopback or is shared.
- Data integrity impact: none directly.
- Production blocker: CONDITIONAL (YES if network-exposed or shared).
- Severity: MEDIUM.
- Recommended owner: Phase 7 → candidate P7-001 + P7-003.
- Dependency: deployment topology.
- Legal next action: HPO-04 posture decision, then certify.
- HPO D7 resolution (2026-09-26): D7-02 = OPTION A sets Redis as the
  production queue backbone (`DECISION-PHASE7-OWNER-DECISIONS-001` /
  ADR-026); the password/auth/network/TLS posture must be decided and
  certified per the final Redis deployment/security topology in candidate
  P7-001/P7-003. CONDITIONAL status is preserved; this entry stays OPEN.
- Status: OPEN.

### TD-005 — Playwright V4-08/V4-09 flake + selective exclusion

- Description: Headless playback-start gates intermittently fail
  (`currentTime === 0` after `play()`); V4-08/V4-09 were excluded from one
  P6-004 regression table.
- Evidence: `reviews/P4-006-independent-review.md` §§F/LOW-1 (5/6 independent
  executions);
  `reviews/pre-review/P6-004-pre-review.md:199-213` (environmental flake +
  exclusion); `DECISION-PHASE6-7-DEBT-CARRYFORWARD-001` item 3. No committed
  `.skip(` exists in `verification/`; no skipped test covers a
  production-critical path by marker — the risk is verdict
  non-reproducibility, and the reviewer classifies it as evidentiary, not a
  product defect.
- Origin: P4-003 LOW-2 → P4-006 LOW-1 → carried forward.
- Current behaviour: gates pass on retry; verdicts occasionally non-reproducible.
- Expected production behaviour: deterministic browser matrix (P7-010).
- Risk: eroded verification trust for browser gates (R-11).
- User impact: none proven (playback works; timing-margin only).
- Operational impact: gate reruns cost time and confidence.
- Security impact: none. Data integrity impact: none.
- Production blocker: HPO_DECISION_REQUIRED (must P7-010 fully eliminate before
  P7-012, or accept with posture?).
- Severity: MEDIUM.
- Recommended owner: Phase 7 → candidate P7-010 (D7-05).
- Dependency: HPO-06 ruling before P7-012 browser portions.
- Legal next action: HPO-06, then P7-010 elimination/matrix work.
- HPO D7 resolution (2026-09-26): D7-05 = OPTION A (Chromium-only initial
  support) via `DECISION-PHASE7-OWNER-DECISIONS-001` / ADR-026. The support
  boundary is narrowed; P7-010 must align its verification contract to
  Chromium-only and must not misrepresent non-Chromium evidence as a
  production guarantee. Elimination work remains OPEN in candidate P7-010;
  this entry is not marked remediated or VERIFIED by the decision.
- Wave 1 implementation VERIFIED + DONE (2026-09-26): P7-010 closed
  (`DECISION-P7-010-CLOSURE-001`; `reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md`
  §D — V4-08/V4-09 deterministic on Chromium, retries 0, no silent skips).
  Evidence recorded; Status stays OPEN pending production-gate proof at
  P7-012 (G-11). This closure does not mark the debt CLOSED.
- Status: OPEN.

### TD-006 — Upload-progress browser-runtime evidence missing
- Description: Upload progress UI exists and behaves correctly by inspection,
  but no retained real-browser proof covers visible progress and
  no-premature-success.
- Evidence: implementation in `resources/views/media/upload.blade.php:77-182`
  (XHR progress %, `Finalizing…` at 100%, completion only on server-confirmed
  redirect, failure/interrupted states);
  `tasks/P2-003-*.md:178-179` (AC 25); `CURRENT_STATE.md` DEFERRED UX
  VERIFICATION; `PHASE7-PLANNING.md:85`. P2-006 is failure/retry handling
  (closed as covered) — not telemetry; the two must not be conflated.
- Origin: Phase 2 UX verification.
- Current behaviour: correct client behavior attested by inspection + server
  tests.
- Expected production behaviour: retained browser evidence (P7-010 wave).
- Risk/impacts: usability-assurance gap only; no correctness, reliability,
  security, or integrity impact evidenced.
- Production blocker: NO. Severity: LOW.
- Recommended owner: Phase 7 → candidate P7-010 (Phase 2 UX evidence).
- Dependency: none.
- Legal next action: capture the four deferred UX scenarios in the P7-010 wave.
- Wave 1 implementation VERIFIED + DONE (2026-09-26): P7-010 closed
  (`DECISION-P7-010-CLOSURE-001`; UPL-01…04 green on Chromium, retained
  `p7-010-upload-results.json`). Evidence recorded; Status stays OPEN
  pending gate consumption at P7-012. This closure does not mark the debt
  CLOSED.
- Status: OPEN.

### TD-007 — Automated staging cleanup deferred (Option D)

- Description: No automated abandoned-staging cleanup is scheduled or executed;
  staging artifacts accumulate as an accepted interim cost.
- Evidence: ADR-013 (`DECISIONS.md:561-585`); `tasks/P2-004A-*.md` and
  `tasks/P2-004A1-*.md` BLOCKED; `tasks/P2-004A2-*.md` DONE but explicitly not
  lifting Option D; `routes/console.php` schedules only
  `translation:recover-stale-attempts` — `media:cleanup-staging` is NOT
  scheduled, by design. `P2-004B/B1/B2` do not exist as task files. No
  `delete_on_success` mechanism exists in the repo. Zero-byte items in evidence
  are P4-002 streaming edge cases (LOW, latent, unreachable path unproven),
  not cleanup incompleteness.
- Origin: Phase 2 (ADR-013/014/016).
- Current behaviour: synchronous compensation + CAS claim protocol (P2-004A2)
  guard the verified paths; out-of-band cleanup absent.
- Expected production behaviour: explicit Option D outcome (run/schedule under
  a proven mechanism, or accept + monitor accumulation) with D7-06 retention
  semantics.
- Risk: storage leaks/orphans over time; running the unresolved mechanism
  risks unsafe deletion (explicitly forbidden).
- User impact: none directly. Operational impact: disk growth without a
  cleanup story. Security impact: stale user media at rest (privacy hygiene).
  Data integrity impact: deletion under unproven mechanism could destroy active
  attempts — hence the prohibition.
- Production blocker: HPO_DECISION_REQUIRED (explicit Option D outcome, HPO-07).
- Severity: MEDIUM.
- Recommended owner: Phase 7 → candidate P7-011 + D7-06.
- Dependency: D7-06; storage backend decision (P7-004).
- Legal next action: HPO-07, then implement/monitor per the decision.
- HPO D7 resolution (2026-09-26): D7-06 = MODIFIED OPTION A — 30-day
  retention auto-purge via `DECISION-PHASE7-OWNER-DECISIONS-001` / ADR-026.
  The owner-policy portion (explicit Option D outcome) is RESOLVED;
  implementation remains OPEN in candidate P7-011 (gated additionally on
  P7-004 VERIFIED) and is not marked remediated or VERIFIED by the
  decision.
- Status: OPEN.

### TD-008 — Order-dependent full-suite flakiness

- Description: Full-suite results vary with test ordering (e.g.
  `MediaManagementTest` file-size/Flysystem-on-Windows behavior; cross-test
  state leakage observed by reviewers).
- Evidence: `DECISION-PHASE6-7-DEBT-CARRYFORWARD-001` item 1;
  `reviews/PHASE4-final-closure.md:91-92` (assertion-count variance between
  legitimate runs);
  `reviews/PHASE7-WAVE2-INDEPENDENT-REVIEW.md` §4 (7 of 8 full runs with
  1–3 failures/errors across Wave 2 review, varying pre-existing files,
  never Wave 2 diffs);
  `reviews/PHASE7-WAVE3A-INDEPENDENT-REVIEW.md` §H/§L (Wave 3A review:
  2 of 4 runs hit `LogContextTest` attempt-ordinal unique-collision,
  full-suite-only, 6/6 green in isolation, file untouched by Wave 3A;
  builder's 3 runs showed zero flakes — observation only, no
  disposition change).
- Origin: test-suite hygiene, pre-existing.
- Current behaviour: most full runs show 1–3 failures in varying
  pre-existing files; targeted suites stay stable.
- Expected production behaviour: deterministic suite (dedicated
  suite-hygiene remediation task, scoped in Wave 3/4 planning).
- Risk/impacts: reliability debt — at this frequency every future
  full-suite gate is unreliable and "full suite green" erodes as
  reported evidence; no user, security, or integrity impact.
- Production blocker: CONDITIONAL (must resolve before P7-012; does not
  block Wave 2 closure — no failure attributed to any Wave 2 diff).
  Severity: MEDIUM.
- Recommended owner: dedicated suite-hygiene remediation task landing
  before the P7-012 terminal gate (scoping in Wave 3/4 planning; no
  task exists — do not invent one here).
- Dependency: none (gates P7-012 confidence).
- Legal next action: `DECISION-TD-008-REPRIORITIZATION-001`
  (`TD-008 — REPRIORITIZATION REQUIRED`, 2026-09-26).
- Status: OPEN.

### TD-009 — Config-derived model identity + `und`→`eng_Latn` fallback assumption

- Description: Laravel persists the translation model identity from config
  without validating the worker's self-reported model; unknown/`und` source
  segments are translated as English (`eng_Latn`).
- Evidence: `PHASE5-P5-008-INTEGRATION-EVIDENCE.md` §Model identity provenance
  (INFO debt, pre-existing P5-003 L-3 design);
  `worker/translation.py:30,82` (`DEFAULT_SOURCE_CODE = "eng_Latn"`);
  `worker/tests/test_transcription.py:148` (fallback test);
  `DECISION-PHASE5-DEBT-CARRYFORWARD-001` (carried non-blocking).
- Origin: Phase 5 (accepted INFO debt).
- Current behaviour: documented, HPO-accepted fallback; harness independently
  confirms the canonical model via `/runtime` + live probe.
- Expected production behaviour: unchanged unless a new HPO decision amends
  the contract.
- Risk: mislabeled model identity; mistranslation of genuinely non-English
  `und` segments as English.
- User impact: wrong model label surfaced; possible mistranslation of
  undefined-language segments.
- Operational impact: provenance caveat for audits. Security impact: none.
- Data integrity impact: translation correctness nuance for `und` segments.
- Production blocker: NO (accepted product behavior).
- Severity: MEDIUM.
- Recommended owner: Phase 5 (accepted); any change needs a new HPO decision +
  contract amendment — not Phase 6/7 scope by default.
- Dependency: HPO decision to change.
- Legal next action: none unless HPO reopens; retain documentation.
- Status: ACCEPTED.

### TD-010 — Metrics/tracing/alerting + `device` observability absent

- Description: Beyond the P7-005 foundation (structured logging, correlation,
  diagnostics), there are no metrics/tracing/alerting backends and no
  compute-`device` observability.
- Evidence: `tasks/P7-005-observability-foundation.md:71-78` (external
  metrics/tracing explicitly non-scope);
  `DECISION-PHASE6-7-DEBT-CARRYFORWARD-001` item 2 (`device` absent app-wide;
  P7-005 emits minimum fields "where available").
- Origin: observability roadmap beyond the P7-005 foundation.
- Current behaviour: logs + correlation + diagnostics only.
- Expected production behaviour: adopted backends + alerting per a Phase 7
  decision (no backend chosen yet — do not assume Prometheus/OTel/Sentry).
- Risk/impacts: future observability enhancement; production failures harder
  to detect without it, but P7-005 covers the minimum — NO user/security/
  integrity impact today.
- Production blocker: NO. Severity: LOW.
- Recommended owner: Phase 7 metrics/tracing wave if adopted (beyond P7-005;
  no duplicate ownership).
- Dependency: backend adoption decision.
- Legal next action: decide backend + alerting scope during Phase 7 planning.
- Status: OPEN.

### TD-011 — Streaming coverage gaps (bounded-stream, range-edge, zero-byte)

- Description: P4-002 streaming behavior is partly code-reviewed rather than
  directly tested: bounded-stream regression assertions, some range-edge
  branches, and a latent zero-byte `Content-Length`/body edge (no confirmed
  ingestion path produces a zero-byte `MediaFile`).
- Evidence: `reviews/PHASE4-final-closure.md:107-109`;
  `reviews/P4-002-independent-review.md:97-103` (LOW-3, no security/leakage
  consequence); `PHASE7-PLANNING.md` §E rows.
- Origin: Phase 4 (accepted LOW debt).
- Current behaviour: correct by review; latent edge unreached in evidence.
- Expected production behaviour: direct tests against the chosen storage
  backend (P7-004 scope).
- Risk/impacts: test-hardening only; no demonstrated user/security/integrity
  impact.
- Production blocker: NO. Severity: LOW.
- Recommended owner: Phase 7 → candidate P7-004 test-hardening.
- Dependency: storage backend decision (D7-03).
- Legal next action: add the direct tests in the P7-004 wave.
- HPO D7 note (2026-09-26): D7-03 = OPTION A (local private storage) via
  `DECISION-PHASE7-OWNER-DECISIONS-001` / ADR-026; TD-011 remains a Phase 7
  verification concern — direct streaming tests are owed against the local
  backend in candidate P7-004. Status unchanged.
- Status: OPEN.

### TD-012 — `D:` volume silent-corruption hazard (local environment)

- Description: The `D:` volume silently corrupted large files (same length,
  different bytes) and its Redis disabled writes after RDB failures during the
  P5-008 gate.
- Evidence: B-006 (`BLOCKERS.md`); `PHASE5-P5-008-INTEGRATION-EVIDENCE.md`
  §Clean model cache (recorded environment defect);
  `DECISION-PHASE5-DEBT-CARRYFORWARD-001` (retained as operational evidence).
- Origin: local gate environment, not product code.
- Current behaviour: mitigated — cache re-provisioned on `C:`
  (`C:\rtftt-hf-cache`), Redis re-pointed to `C:\rtftt-redis`, reproducible
  provisioning in `verification/p5-008/README.md`.
- Expected production behaviour: gates run only on verified volumes; production
  storage separately decided (P7-004).
- Risk/impacts: future local gates could silently corrupt large artifacts if
  rerun on `D:`; no product-code impact.
- Production blocker: NO. Severity: LOW.
- Recommended owner: environment runbook (no product task).
- Dependency: none.
- Legal next action: none; retain the runbook warning.
- Status: MITIGATED.

### TD-013 — Pre-existing `showRenameModal` console error

- Description: `ReferenceError: showRenameModal is not defined` on the
  transcript workspace; predates Phase 3/4.
- Evidence: `reviews/PHASE4-final-closure.md:117-118` (INFO);
  `PHASE6-P6-005-IMPLEMENTATION-BATCH-REPORT.md` / corrective lineage carries
  it as non-blocking.
- Origin: pre-existing frontend defect.
- Current behaviour: console error; no functional breakage evidenced.
- Expected production behaviour: fixed with regression test (P7-010 scope).
- Risk/impacts: minor UX/console hygiene defect; no security/integrity impact.
- Production blocker: NO. Severity: LOW.
- Recommended owner: Phase 7 → candidate P7-010.
- Dependency: none.
- Legal next action: fix + regression test in the P7-010 wave.
- Wave 1 implementation VERIFIED + DONE (2026-09-26): P7-010 closed
  (`DECISION-P7-010-CLOSURE-001`; rename modal returned to native Flux
  visibility, CON-01…03 green with zero console/page errors). Evidence
  recorded; Status stays OPEN pending gate consumption at P7-012. This
  closure does not mark the debt CLOSED.
- Status: OPEN.

### TD-014 — Retention staging-claim crash-recovery gap

- Description: `RetentionPurge` claims an expired staging row
  (`held_by` upload → cleanup) and, if the purge process itself is
  killed (OOM/SIGKILL/host restart) after claim acquisition but before
  release/completion, the row stays at `held_by = 'cleanup'`
  permanently. Unlike `CleanupStaging` (15-minute stale-claim reclaim),
  `RetentionPurge` has no crash-recovery reclaim for its own stuck
  claims, and reclaimed-by-nobody rows are invisible to later purge
  runs (the expired-unheld query never matches them).
- Evidence: `reviews/P7-011-INDEPENDENT-REVIEW-CYCLE2.md` §E.1/§I.3
  (new LOW observation, non-blocking; pre-existing since the claim
  path landed — not a cycle-1 regression); failure-path release
  (`held_by` back to upload) covers caught failures, not uncaught
  process termination.
- Origin: P7-011 staging purge (cycle-2 re-review observation).
- Current behaviour: successful/failed-audited runs unaffected; only
  an uncaught kill in the claim→completion window strands a row
  (narrow reachability).
- Expected production behaviour: a bounded reclaim (e.g. mirroring
  the 15-minute stale-claim convention) or explicit operator
  procedure, decided and implemented before P7-012 G-09 consumption.
- Risk/impacts: stranded staging rows accumulate instead of purging;
  no data loss, no false success, no security impact (file bytes
  remain; claim row remains auditable).
- Production blocker: NO (pre-P7-012 follow-up). Severity: LOW.
- Recommended owner: P7-011 follow-up task or TD-008-style hygiene
  scope landing before the P7-012 terminal gate (no task exists — do
  not invent one here).
- Dependency: none (gates G-09 confidence, not P7-011 DONE).
- Legal next action: HPO scopes the follow-up during Wave 3B/4
  planning; P7-011 closure is not blocked.
- Status: OPEN.

## Sweep record (§4 signals)

Searched `app/`, `worker/`, `config/`, `routes/`, `tests/`, `resources/`,
`verification/`, `tasks/`, `docs/`, `reviews/` for: TODO, FIXME, HACK, XXX,
placeholder, workaround, temporary, fake, mock, not implemented, pending
review/verification, future work, known issue, blocker, production gap,
fallback, manual step, unsafe default, unsupported, retry/monitoring missing,
`.skip(`, `markTestSkipped`, `fixme`, `quarantine`, `delete_on_success`,
`P2-004B`.

Material findings are registered above. Deliberately NOT registered: benign
code comments (e.g. `worker/ffmpeg.py` mkstemp note); the intentional 2FA test
skip and the FFprobe-unavailable guard (environmental, expected); historical
review-cycle language preserved in closed task files; candidate P7 task IDs
(which are planning, not debt).
