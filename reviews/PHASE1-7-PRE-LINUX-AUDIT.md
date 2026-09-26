# RTFTT — Phase 1–7 Pre-Linux Full Review

**Role:** Independent architecture/governance reviewer (Claude Code), acting per `AGENTS.md` / `.ai/guidelines/orchestration-policy.md`.
**Nature:** Read-only pre-deployment audit. No code changed, no task states changed, no phase authorized, no P7-009 Phase B, no P7-007 drill, no P7-012 started.
**Date:** 2026-09-27.
**Method:** Direct review of governance artifacts plus five parallel evidence-gathering passes (governance/canonical state, PostgreSQL portability, Redis/queue/scheduler topology, filesystem/runtime/security, Phase 1–6 feature coherence + test-suite credibility). All findings below are evidence-based with file:line citations from those passes; this document is the synthesis.

---

## A. Executive summary

Phases 1–6 are coherently closed, with a real, auditable independent-review trail and frozen invariants (especially Phase 6's revision/editing model). The engineering is generally careful: idempotent/CAS-fenced jobs, no hardcoded Windows paths in application code, no public-disk media leaks, clean authorization boundaries, defense-in-depth PostgreSQL-compatible SQL patterns.

However, this audit surfaces two categories of problem that are **not** "needs Linux to prove" — they are governance and code defects present *right now*, in the current dev environment, and should be fixed before paying for a VPS:

1. **The entire Phase 7 governance record — every wave, every closure decision, P7-011 DONE, P7-009 Phase A, P6-008/009/010 — exists only in the uncommitted working tree.** Git HEAD stops at Phase 6 closure. This contradicts the project's own stated principle that the repository is the durable source of truth.
2. **PostgreSQL readiness is asserted but never executed.** P7-002 (the datastore migration task) was closed DONE under an explicit environmental exception with its four most important acceptance criteria (dual-driver suite, migration rehearsal, Phase 6 regression on pgsql, two-process claim-fencing on pgsql) marked NOT PASS because no PostgreSQL server was available. Worse: `BackupManager` currently *refuses to run* on `pgsql` — there is no working backup mechanism the moment the datastore cuts over. A test needed for the Postgres claim-fencing proof also hardcodes SQLite-only `PRAGMA` syntax that will throw (not skip) under Postgres.
3. **The target host itself is verified NOT READY** (no PostgreSQL, no supervised Redis, wrong OS/topology, upload-limit floor below the required contract, unreachable model workers) — this is expected pre-provisioning, but it means P7-009 Phase B and the P7-007 restore drill cannot even be attempted yet.
4. Two technical-debt items (**TD-008**, **TD-014**) are explicitly flagged in the project's own register as blocking P7-012 with **no task ID assigned to close them.**

None of these require a Linux box to fix. All are addressable on the current Windows/Herd/SQLite dev environment. **Verdict: `PRE_LINUX_CHANGES_REQUIRED`.**

---

## B. Canonical Phase 1–7 state table

Statuses reflect the working-tree governance narrative (self-consistent across `AGENTS.md`, `CURRENT_STATE.md`, `plan.md`, `DECISIONS.md`, `DECISION_QUEUE.md`, task files) unless flagged otherwise. **Committed = true in `git show HEAD`; everything else lives only in the uncommitted working tree** (see §C, Phase 7, and the critical finding in §P).

| Phase | Task | Status | Committed? |
|---|---|---|---|
| 1 | TASK-000..004(+A/B/C), TASK-P1-STATIC-001..004, TASK-P1-CREATE-001 | DONE | Yes |
| 1 | Phase 1 overall | ACCEPTED (2026-09-11, ADR-007) | Yes |
| 2 | P2-001, P2-002, P2-003, P2-004A2, P2-005, P2-007 | DONE | Yes |
| 2 | P2-004A, P2-004A1 | **BLOCKED** (deferred, Option D, ADR-013) | Yes |
| 2 | P2-006 | CLOSED_AS_ALREADY_COVERED | Yes |
| 2 | Phase 2 overall | **COMPLETE_WITH_DEFERRED_DEBT** (2026-09-17) | Yes |
| 3 | P3-001..P3-008 | DONE (canonical model **large-v3**; turbo non-default/experimental) | Yes |
| 3 | Phase 3 overall | **CLOSED** (2026-09-19) | Yes |
| 4 | P4-001, P4-002, P4-003, P4-005 | DONE | Yes |
| 4 | P4-004 | DONE (reopened once for a real Alpine `this.$el` scoping defect found by P4-006, fixed, re-closed) | Yes |
| 4 | P4-006 | DONE (first gate run FAILED V4-14/V4-18 on the same defect; rerun PASSED 25/25) | Yes |
| 4 | Phase 4 overall | **CLOSED** (2026-09-20); residual LOW/INFO debt retained, not defect-free | Yes |
| 5 | P5-001..P5-008 (13 tasks) | DONE (P5-008 against canonical self-hosted NLLB-200-distilled-600M, ADR-024) | Yes |
| 5 | Phase 5 overall | **CLOSED** (2026-09-23) | Yes |
| 6 | P6-001..P6-007 | DONE | Yes |
| 6 | P6-008, P6-009, P6-010 | DONE (P6-009 first run **FAILED** on F-001 MAJOR — exports read machine source, not active revision; remediated by P6-010; full rerun PASSED, independently VERIFIED) | **No — untracked** |
| 6 | Phase 6 overall | **CLOSED** (2026-09-25) | Governance statement yes (commit `4d90d5b`); supporting P6-008/009/010 evidence **no** |
| 7 | P7-005 (Observability) | DONE (early-authorized) | No |
| 7 | P7-003, P7-008, P7-010 (Wave 1) | DONE — Wave 1 CLOSED (P7-008 closed under exception, AC2 NOT PASS) | No |
| 7 | P7-001, P7-006, P7-007 (Wave 2) | DONE — Wave 2 CLOSED (P7-001 closed under exception, AC8 NOT PASS; **P7-007 = foundation only, restore drill DEFERRED/not executed**) | No |
| 7 | P7-002, P7-004 (Wave 3A) | DONE — Wave 3A CLOSED (**P7-002 closed under exception, AC1/2/5/6 pg-halves NOT PASS**) | No |
| 7 | P7-011 (Retention) | **DONE** (2026-09-26, cycle-2 re-review VERIFIED) | No |
| 7 | P7-009 (Performance/Load) | **IN_PROGRESS** — Phase A (rehearsal harness) complete; **Phase B (real production-shaped capacity run) explicitly NOT AUTHORIZED**; target-host readiness review independently returned **`TARGET_HOST_NOT_READY`** | No |
| 7 | P7-012 (Final Gate) | **FINAL_GATE_ONLY** — no task file exists (correct by design); not started | No (n/a) |
| 7 | Phase 7 overall | **NOT CLOSED / not generally authorized beyond executed waves** | No |

**Contradictions found:**
- `AGENTS.md` contains a stale catch-all sentence ("P7-009, P7-011, P7-012 ... remain unauthorized") that contradicts the same file's own later paragraph recording P7-011 DONE. Documentation drift, not a real authorization conflict.
- `CURRENT_STATE.md`'s header says "Last Updated: 2026-09-24" but its own body contains entries dated through 2026-09-26; and separately, `CURRENT_STATE.md` still narrates P6-009 as "READY... not executed," which is superseded by `reviews/PHASE6-CLOSURE-REVIEW.md` (P6-009 DONE, Phase 6 CLOSED, 2026-09-25). **`CURRENT_STATE.md` prose is not reliable as a current-status source on its own — cross-check `reviews/` and `DECISIONS.md` dates.**
- `tasks/P7-009-performance-load-validation.md`'s status log reads newest-first, which can be misread as "READY happened after IN_PROGRESS" — clarity issue, not a governance defect.
- No contradiction found between `PHASE6-7-ELIGIBILITY-MATRIX.md`, `PHASE5-7-DECISION-REGISTER.md`, `DECISIONS.md`, `DECISION_QUEUE.md` — these are mutually consistent.

---

## C. Phase-by-phase assessment

### Phase 1 — Foundation
DONE/ACCEPTED, fully committed. No later phase surfaced a Phase 1 regression. Not independently re-audited in this pass beyond confirming no contradicting evidence; if Phase 1 needs its own fresh closure sign-off, that's a separate, low-priority exercise.

### Phase 2 — Media ingestion
`COMPLETE_WITH_DEFERRED_DEBT`. The 500 MiB (524,288,000-byte) product limit, MIME/extension validation, checksum, private opaque storage, staging→promotion lifecycle, and the SQLite-safe CAS claim/cleanup protocol (P2-004A2) are all implemented and independently VERIFIED — but only against SQLite and only on the Windows/Herd dev box. P2-004A/P2-004A1 (automated abandoned-staging cleanup) remain BLOCKED under Option D — accepted carry-forward debt (TD-007), not a defect. `ffprobe` availability is a runtime dependency resolved via `$PATH`/`config('media.ffprobe_path')`, portable but requires explicit provisioning on Linux (see §J).

### Phase 3 — Transcription
CLOSED. Real faster-whisper `large-v3` architecture with FFmpeg preparation, segment-level language handling for ms/en/zh/ta, retry/recovery, and a normalized result contract are all implemented and reviewed. The queue-orchestration verification for P3-006 ("Redis Queue Orchestration") was, per the project's own record, run against Laravel's `database` queue driver because "real working Redis was not available in this environment" — the task's title promises Redis verification that its own closing evidence didn't provide at the time. Redis was later exercised for real in P7-003, but only with a single worker (see §G). Distinguishing correctness layers: application-level correctness (contracts, retry, idempotent claim-fencing) is well-evidenced; model-worker correctness (large-v3 inference quality) has real-model evidence; deployment/network correctness (Redis under concurrent supervised workers, model-worker reachability from a real Linux host) remains unproven (Linux-only, §Q).

### Phase 4 — Playback/transcript interaction
CLOSED. One real, since-fixed defect: Alpine.js `this.$el` resolved to the event target rather than the component root inside child-element handlers, breaking search-highlight movement (V4-14) and segment-copy (V4-18); fixed by capturing `rootEl` once in `init()`, independently re-verified. One flake (V4-08, `currentTime>0` after `play()`) was carried forward as non-blocking through three phase closures and was ultimately fixed not in application code but in the *verification harness* (Phase 7, `verification/p4-006/phase4-integration.spec.js`, replacing a fixed 1500ms sleep with a `readyState`-polling wait) — confirming the underlying playback capability was correct all along and the flake was purely evidentiary. Disposition: environment/harness flake, correctly resolved, not a lingering product defect.

### Phase 5 — Translation
CLOSED. Real self-hosted NLLB-200-distilled-600M model, real Redis queue, ms/en/zh/ta/und round-trip, revision-aware staleness. No old translation can be silently presented as current after a transcript edit — every applicable edit kind marks affected translations explicitly stale (frozen further in Phase 6, see D6-04 below). LOW/INFO debt (heuristic-not-structural Redis-payload leakage check, config-derived model identity, undocumented `und→eng_Latn` fallback) explicitly accepted, non-blocking.

### Phase 6 — Editing/revision integrity
CLOSED. This is the most safety-critical phase for a migration and its invariants are unusually well-specified (`PHASE6-EDITING-DOMAIN-CONTRACT.md`, D6-01..D6-09). **Invariants the Linux deployment must not break** — full list in §D. One MAJOR historical defect (F-001: all four export formats read the immutable machine transcript, silently ignoring the active revision — i.e., exports could omit user edits) was found at the P6-009 final gate, root-caused, remediated in P6-010 (`exportRows()` now resolves the active revision first), and independently re-verified at rerun. **Recommend confirming no other export/download code path (e.g., a future CLI/batch export) bypasses `exportRows()`.** D6-08/D6-09 (diarization/waveform/annotation-adjacent features) are explicitly deferred, not implemented — this is a verified frozen state, not an oversight; do not silently "complete" them without a fresh HPO decision.

### Phase 7 — Operationalization
See §B for per-task status. Substantively: Wave 1/2/3A are DONE with several tasks closed under explicit environmental exceptions (P7-001 AC8, P7-008 AC2, P7-002 AC1/2/5/6) that were "carried forward, to be consumed by P7-007's drill + P7-009's final run" — i.e., the exceptions were deliberately deferred to exactly the two gates (a real backup/restore drill, a real capacity run) that have not happened yet. P7-011 is DONE. P7-009 is IN_PROGRESS with Phase A complete and Phase B explicitly not authorized. P7-012 correctly has no task file and has not started. **The entire Phase 7 record is uncommitted — see §P, this is the single largest governance risk in this audit.**

---

## D. Phase 6 invariants the Linux deployment must not violate

From `PHASE6-EDITING-DOMAIN-CONTRACT.md` (D6-01..D6-09):

- **D6-01**: Machine transcription tables are immutable once `completed`. All edits live only in an append-only revision layer. Exactly one active revision per transcription (`active_revision_id`, nullable = machine source authoritative). Machine original always recoverable.
- **D6-02**: `RevisionId` is an opaque UUID, never reused. `version` is a transcription-scoped monotonic sequence, independent of ancestry, unique per `(transcription_id, version)`, re-validated at append. Ancestry (`parent_revision_id`) and creation order (`version`) are deliberately not conflated. Undo/redo moves a pointer; it never rewrites the durable revision graph. Undo-then-edit branches rather than overwrites history.
- **Concurrency**: every edit states its base revision id; the write applies only if base equals the current active revision id, else a stale-write conflict is raised — no silent merge, no last-writer-wins.
- **D6-03**: Timing is finite, `>=0`, `start<=end`, millisecond precision. Overlaps are legal. Zero-length is legal but never active for playback. Ordering is strictly by `position` (unique, contiguous), never array index or timestamp. Revision timing edits never mutate the machine segment's own timing columns.
- **D6-04**: Split creates two new identities; merge creates one new identity at the earliest contributing position and requires adjacency (non-adjacent merge is rejected outright). Mixed-language merge carries the earliest segment's language, flagged mixed-provenance. **Every applicable edit kind always marks affected translations explicitly stale** — never silently preserved, never silently remapped.
- **D6-05**: Exactly one active revision per transcription at any time.
- **§8**: After a structural edit, the machine `segment_index` is provenance-only, never a safe navigation identity — UI navigation/selection must use revision-segment identity + position.
- **§9 (Export)**: an active revision, when present, is authoritative for all export formats; falls back to machine source only when no active revision exists. (This is the exact invariant F-001 violated and P6-010 fixed.)
- **§12**: `data-seek-seconds` / `data-segment-language` are reserved Phase 4 browser selectors; editing/revision UI must not reuse them.
- **D6-08/D6-09**: explicitly deferred — verified absence, not a gap to silently fill.

---

## E. Technical-debt register (full)

| ID | Title | Severity | Blocks Linux provisioning? | Blocks P7-009? | Blocks P7-012? | Carry-forward acceptable? |
|---|---|---|---|---|---|---|
| TD-001 | Full-chain real-model/no-fake E2E never fully proven at deployed-stack scale | HIGH | No | **Yes** (gates G-12) | Yes | No — must resolve pre-launch |
| TD-002 | Default PHP upload limits below the 500 MiB contract | HIGH | **Yes** (config floor) | Yes (G-01) | Yes | No |
| TD-003 | Queue defaults to `database`; no production supervision proven; `sync` guard exemption | HIGH | Partially (posture, not code) | Yes (G-03) | Yes | No — stays OPEN even though P7-003 implementation is DONE |
| TD-004 | Redis passwordless-by-default posture undecided | MEDIUM | Conditional (yes if network-exposed) | Yes (G-04) | Yes | Owner decision needed pre-launch |
| TD-005 | Playwright V4-08/09 flake + selective exclusion | MEDIUM | No | No | Yes (G-11) | Operationally closed by P7-010, debt stays OPEN pending gate consumption |
| TD-006 | Upload-progress browser evidence gap | LOW | No | No | No | Yes |
| TD-007 | Automated staging cleanup deferred (Option D) | MEDIUM | No | No | Yes (G-09) | Yes, with 30-day retention policy now implemented (P7-011) — closure deferred to gate consumption |
| **TD-008** | **Order-dependent full-suite flakiness** | MEDIUM | No | No | **Yes — hard pre-P7-012 prerequisite** | **No task exists to own this** |
| TD-009 | Config-derived model identity + `und→eng_Latn` fallback | MEDIUM | No | No | No | Yes — HPO-accepted |
| TD-010 | Metrics/tracing/alerting absent beyond P7-005 baseline | LOW | No | No | No | Yes |
| TD-011 | Streaming coverage gaps (bounded-stream/range-edge/zero-byte tests) | LOW | No | Weakens G-05 | No | Yes |
| TD-012 | Local dev volume corruption hazard | LOW | No | No | No | Mitigated |
| TD-013 | Pre-existing console error (`showRenameModal`) | LOW | No | No | No | Fixed by P7-010; debt stays open pending gate consumption |
| **TD-014** | **Retention staging-claim crash-recovery gap** (found in P7-011 cycle-2 review) | LOW | No | No | Flagged pre-P7-012 follow-up | **No task exists to own this** |

**No LOW/INFO item was silently omitted** — all fourteen are listed. **TD-008 and TD-014 are the two items requiring immediate attention before this audit's recommendations can be considered complete**: both are pre-P7-012 blockers or blocker-adjacent with zero owning task ID.

---

## F. PostgreSQL portability

**Framing: treat Postgres readiness as "designed and defensively coded for," not "verified."** `tasks/P7-002-production-datastore-migration.md` explicitly records AC1 (dual-driver full suite), AC2 (migration-rehearsal reconciliation), AC5 (Phase 6 regression on pgsql), and AC6 (two-process claim-fencing on pgsql) as **NOT PASS**, closed under `DECISION-P7-002-PG-ENV-DISPOSITION-001` because "no PostgreSQL server, binaries, or Docker exist in this environment." CI (`.github/workflows/tests.yml`) runs a single `ubuntu-latest` job with no `services: postgres:` — every green check in this repo's history is SQLite-only.

**BLOCKER**
- **No PostgreSQL execution evidence exists anywhere.** Nothing about revision/version conflicts, translation CAS races, or migration rehearsal has ever run for real against Postgres.
- **`BackupManager` refuses to run on `pgsql`** (`app/Backup/BackupManager.php:62-69`): `run('pgsql')` unconditionally returns `ok=false`, "PostgreSQL-native backup requires P7-002 ... DONE; refusing to half-execute." `BackupPreMigrate.php:22` hardcodes `--driver=sqlite`. Since the pg-halves of P7-002 never ran, this path has never been activated. **If Postgres reaches production before this is unblocked, there is no working automated backup at all.**

**HIGH**
- Multiple test-only race-harness commands (`TranslationRetryRaceWorker.php:86`, `TranslationRequestRaceWorker.php:77`, `TranslationClaimRaceWorker.php:81`, `TranscriptionRetryRaceWorker.php:87`, `TranscriptionClaimRaceWorker.php:88`, `RevisionAppendRaceWorker.php:76`) and `tests/Feature/Translation/TranslationOperationalPreflightTest.php:82-86` execute raw `PRAGMA busy_timeout` — SQLite-only syntax that will **throw a PDO exception** under Postgres, not fail gracefully. These are exactly the commands AC6 (two-process claim-fencing on pgsql) depends on; they need a driver-conditional rewrite before AC6 can ever run.
- The staging-claim CAS protocol (P2-004A2) is documented throughout as "SQLite-Safe," with its correctness argument never re-validated against Postgres. On inspection the mechanism (`insertOrIgnore` → `ON CONFLICT DO NOTHING` on pgsql; guarded single-statement `UPDATE`s) is very likely Postgres-safe or better (real MVCC row locks vs. SQLite's coarse file lock) — but no genuine two-OS-process race test exists against a live Postgres instance, and the acceptance criteria explicitly required that proof.

**MEDIUM**
- `lockForUpdate()` calls throughout (`EloquentRevisionRepository.php:105,156`, `ProcessTranscription.php:302,312`, `TranslationOrchestrator.php:180`, others) are silent no-ops on SQLite today but become real blocking row locks under Postgres — a behavioral change (lock-wait/deadlock exposure under real concurrent load) never exercised in any test.
- Two migrations add partial unique indexes gated `if (driver !== 'sqlite') return` (no-ops on Postgres), with a later migration (`2026_09_26_130000_add_pg_partial_unique_indexes.php`) correctly recreating the same indexes for `pgsql` — good defense-in-depth, but since it's never actually run against live Postgres, its `CREATE UNIQUE INDEX ... WHERE ...` syntax is unconfirmed in practice (low code risk, zero evidence).

**LOW/INFO**
- `->change()` on decimal columns takes structurally different code paths per driver (table-recreate on SQLite vs. native ALTER on Postgres) — functionally equivalent on a fresh migrate, worth noting only.
- `config/database.php`'s `pgsql` connection block and `.env.example`'s pgsql cutover variables look standard, no anomalies.
- No `json_extract`, `strftime`, `date_trunc`, `NOW()`, raw boolean-literal comparisons, or case-sensitivity-dependent raw string comparisons found anywhere in `app/`. All raw SQL found is portable ANSI aggregate SQL.
- `Str::uuid()` usage is extensive and safe (always lowercase, no mixed-case comparison risk); UUID columns map to native Postgres `uuid` type.
- Only one JSON column exists (`processing_jobs.logs`); it's never queried structurally, so SQLite/Postgres JSON-function divergence is a non-issue.

**Bottom line**: the code is careful and mostly portable on static inspection, but the two concrete pre-cutover blockers — no working Postgres backup mechanism, and no actual Postgres execution of AC1/2/5/6 — must be closed for real, not re-dispositioned again, before Postgres goes anywhere near production.

---

## G. Redis/queue concurrency

**Job inventory** (`app/Jobs/`, 3 classes): `ProcessTranscription`, `ProcessTranslation`, `CapacityProbeJob`. All payloads are scalar-only (ids, tokens, strings) — fully serializable, no bound Eloquent models, no closures. `$tries=1` by design (frozen policy); redelivery safety rests entirely on atomic CAS/token-fenced claim updates (`UPDATE ... WHERE status = 'queued'` / `WHERE attempt_token = ? AND status IN (...)`), which is a genuine strength — these are safe under true concurrent consumption by construction, not because of single-worker assumptions.

**Configuration**: `config/queue.php` defines a proper `redis` connection with its own `retry_after`; `.env.example` documents the production posture (`QUEUE_CONNECTION=redis`, password required off-loopback) purely as comments — the actual example values are dev/loopback defaults. `job_batches` lives on the app DB connection regardless of queue driver (currently moot, `Bus::batch` is unused).

**What has and hasn't been proven with real Redis**:
- P3-006 ("Redis Queue Orchestration") was, per the project's own closure record, verified against the `database` driver because real Redis was unavailable at the time.
- P7-003 later exercised real Redis, but with a **single loopback worker**, not multiple concurrently-supervised workers. The SIGTERM graceful-drain criterion (AC8) was explicitly **not executed** ("not executable on Windows dev machine; budget + mechanism documented"). The drain/rollback criterion (AC9) was only dry-run verified.
- **No test anywhere spins up 2+ concurrent `queue:work` processes against Redis simultaneously.** The existing concurrency proofs race against SQLite at the database layer, proving CAS correctness, not Redis-broker-level concurrent consumption.

**Single-process assumptions worth flagging**: SQLite's whole-database write lock (not row-level) means `lockForUpdate()`-based serialization (e.g. `TranscriptionOrchestrator::request()`) currently behaves as a coarse bottleneck under load — this changes materially once Postgres is live, and the "multiple Redis workers" story has not been re-proven against either backend under real concurrency. `ObservabilityDiagnostics --probe-worker` only reflects the in-process `Schedule` facade, not whether a systemd timer is actually installed — that verification gap is deferred to P7-008 by design but nothing currently closes it. `withoutOverlapping()` only fences scheduler-triggered runs against each other, not manual CLI invocations (`deployment:verify`, `backup:pre-migrate`) against a concurrently scheduled run of the same command — a minor real gap on a host that will have both a scheduler and manual/CI invocations.

---

## H. Scheduler/process supervision topology

`routes/console.php` registers exactly 5 `Schedule::` entries, all `withoutOverlapping()`: `translation:recover-stale-attempts` (every minute), `transcription:recover-stale-attempts` (every minute), `clamav:health` (daily), `backup:run --driver=sqlite` (daily), `retention:purge` (daily). No separate Console Kernel class (Laravel 11+ style).

**Expected Linux process topology** (per `docs/QUEUE-WORKER-SUPERVISION.md`):
- `queue:work redis --queue=transcription ...` — **systemd service, `Restart=always`**
- `queue:work redis --queue=translation ...` — **systemd service, `Restart=always`**
- `schedule:run` — **systemd timer or cron, every minute**, driving the 5 entries above
- `backup:pre-migrate`, `deployment:verify`, `env:audit-limits`, `storage:validate-topology`, `capacity:validate`, `deployment:record-target-evidence` — **one-shot, manual/CI invocations**, never scheduled
- 6 `test:*-race-worker` commands and 2 `test:p*-integration` commands — **test-only harnesses**, never run in production

**Gap found**: `observability:diagnostics` is described by the P7-003 spec as a health signal an external monitor should poll, and `media:cleanup-staging` is referenced in P7-011's rationale — **neither has a `Schedule::` entry**. If production relies on cron/systemd-timer wrapping for either, that wrapping doesn't exist in this repo; it would need to be added at the P7-008 installation layer, which is explicitly out of P7-003's stated scope. Flag as a real gap for whoever performs P7-008 installation on the actual host.

---

## I. Filesystem/Linux portability

**No blocking Windows-only path assumptions in application business logic.** `app/Backup/BackupManager.php` uses `DIRECTORY_SEPARATOR` throughout. Media staging/durable/quarantine paths are all disk-relative through Laravel's Flysystem abstraction, resolved to a single `local` disk root (`storage/app/private`) — staging→durable promotion is stream-copy+delete rather than atomic rename, which is fine since everything is one filesystem root (no cross-mount hazard as currently configured; **flag for deploy-time verification only if ops ever splits storage across multiple mounts/volumes**, since `rename(2)` atomicity requires same-mount).

**One confirmed Windows-only branch**: `app/Capacity/EnvironmentMetadata.php:99-104,124-128` shells out to `wmic` for CPU/memory metadata on Windows, correctly falling back to `/proc/cpuinfo`/`/proc/meminfo` on Linux — this already degrades gracefully; it's inert dead code in production, not a portability bug. (Note: `wmic` is also deprecated on modern Windows, but this only affects non-authoritative Phase-A capacity-rehearsal metadata.) Severity: LOW/INFO.

No other filesystem risks found; `app/Backup/`, `app/Deployment/`, `app/Capacity/`, `app/Queue/`, `app/Storage/` are all config/posture-driven with no hardcoded paths or OS-specific shell syntax beyond the one flagged branch.

---

## J. Runtime/web-server requirements

**PHP extensions** — `composer.json` declares only `"php": "^8.3"` with no explicit `ext-*` requirements; the real requirement list is transitive via `composer.lock`:
- Laravel framework: ctype, filter, hash, mbstring, openssl, session, tokenizer, json
- Guzzle (worker HTTP calls): json, curl
- **`phpoffice/phpword`** (`.docx` export): **dom, gd, json, xml, zip** — all hard requires
- SQLite runtime: **pdo, pdo_sqlite** — a pure runtime assumption, declared nowhere explicitly

**Concrete risk**: Herd on Windows bundles all of these by default, and CI's `shivammathur/setup-php` step has no explicit `extensions:` key (relies on that action's Ubuntu defaults) — so a green CI run does **not** prove a bare production Linux box will have `gd`/`zip`/`xml`/`dom`/`pdo_sqlite` installed. A minimal `apt install php8.3-fpm php8.3-cli` commonly omits all of these. **Action item: pin an explicit extension list in both CI and the Linux provisioning script.** Also reconcile the PHP version target — `composer.json` says `^8.3`, CI uses `8.4`, project docs say "PHP 8.4."

**FFmpeg/ffprobe/ClamAV**: `ffprobe` is invoked via Symfony `Process` with an argument array (not a shell string — safe on both OSes), path resolved via `config('media.ffprobe_path', 'ffprobe')`/`$PATH`. No `ffmpeg` transcoding binary is invoked anywhere in this repo — that's delegated to an external worker service. ClamAV is invoked purely via PHP sockets (Unix domain socket preferred, TCP loopback fallback, non-loopback refused by default) — fully portable, no binary shelling required.

**Web-server boundary / 500 MiB contract**: `config/media.php` enforces exactly 524,288,000 bytes app-side. `app/Deployment/UploadLimitAudit.php` correctly computes a 600 MiB floor for `upload_max_filesize`/`post_max_size` (headroom for multipart overhead) — but `max_execution_time`/`fastcgi_read_timeout`/nginx `proxy_read_timeout` reconciliation for large transfers was not found addressed by this audit class. **Add to the production runbook.** Media streaming/download (`MediaActionController::stream()`/`download()`) is implemented as manual byte-range PHP streaming with correct RFC-7233 behavior but **no X-Accel-Redirect/X-Sendfile** — every byte of every download ties up a PHP-FPM worker for the full transfer. Flagged MEDIUM: fine functionally, a real scalability item for concurrent large-file streams on the Linux/nginx target — recommend nginx X-Accel-Redirect with signed internal URLs.

---

## K. Model-worker architecture

Faster-whisper `large-v3` and self-hosted NLLB are accessed via HTTP worker URLs (`RTFTT_*_WORKER_URL` config), i.e. an explicit HTTP/service boundary rather than in-process execution — consistent with a separate-worker-host topology. **This audit found no formal, explicit, HPO-recorded decision on CPU-vs-GPU deployment for the model workers.** Per instruction, this is not invented here: **flag as an owner/infrastructure decision required before final capacity validation (P7-009 Phase B)** — the target-host readiness review already lists "model workers unreachable" as a blocker (B-5), consistent with this decision not yet being operationalized.

---

## L. Security

Findings, classified:

| Severity | Finding |
|---|---|
| MEDIUM | Media streaming/download is pure-PHP with no X-Accel-Redirect (see §J) — production scalability item for the Linux/nginx target. |
| MEDIUM | PHP extensions (`gd`, `zip`, `dom`/`xml`, `pdo_sqlite`) are hard runtime requirements with no pinned provisioning list anywhere (see §J). |
| LOW/INFO | `wmic`-based capacity metadata is Windows-only dead code on Linux, non-authoritative data only. |
| INFO | `.env.example` ships `APP_DEBUG=true`/`APP_ENV=local` correctly for the example file; `ProductionConfigGuard::violations()` fails fast in production if debug is truthy, queue isn't Redis, `APP_KEY` looks unset, or worker URLs still point at localhost — verified, no gap. |
| INFO | No hardcoded secrets/credentials found anywhere in `app/`/`config/`. |
| INFO | Private-media boundary enforced: no controller calls `Storage::disk('public')` for media; all media access goes through the private `local` disk, consistent with the P2-002A decision. |
| INFO | Download/stream authorization enforced via policy gate (`authorize('view'/'update'/'delete', ...)`) on every media/export action before storage is touched. |
| INFO | `SecurityHeaders` middleware + `CspReportController` are complete and correctly scoped (CSRF-exempt-by-design report endpoint, body-capped, truncated storage). |
| INFO | ClamAV fails closed on scanner unavailability/timeout/error, quarantines on infected verdict, refuses non-loopback TCP by default. |
| INFO | `SESSION_DRIVER=database` — no file-based session store portability concern. `SESSION_ENCRYPT=false` default is a product-owner call, not an OS-portability finding. |

No pre-VPS security BLOCKER was found. The two MEDIUM items above are worth resolving before or shortly after provisioning but do not by themselves justify delaying provisioning.

---

## M. Backup/recovery

- **Implemented**: SQLite-native backup mechanism (`BackupManager`, driver `sqlite`), scheduled daily via `backup:run --driver=sqlite`, plus a pre-migration hook (`backup:pre-migrate`).
- **Independently verified**: only for the SQLite path, on the dev host.
- **Disaster-recovery drill evidence**: **none.** `tasks/P7-007-backup-restore-foundation.md` explicitly scopes itself as foundation-only; the actual restore drill against PostgreSQL requires P7-002 DONE (satisfied on paper) plus a **separate HPO drill authorization that has not been granted**. No drill has been executed.
- **PostgreSQL backup**: `BackupManager::run('pgsql')` currently and explicitly refuses to execute at all (see §F) — this must be resolved before Postgres is anywhere near production, independent of Linux.

**What still must be done on the Linux target**: provision Postgres, unblock/re-implement the pgsql-native backup path, obtain a real HPO drill authorization, and execute + independently verify an actual restore drill (P7-007's remaining scope, gate G-08).

---

## N. Observability

P7-005 delivered a structured-log baseline plus `observability:diagnostics` (structured JSON channel checks, queue-timeout-ordering checks, `Http::fake`-based worker-health probes — i.e. these probes have never checked a real network endpoint). Required Linux operational signals not yet wired: (1) `observability:diagnostics` is not itself scheduled or externally polled anywhere in-repo (see §H gap); (2) no metrics/tracing/alerting beyond the log baseline exists (TD-010, accepted post-launch debt); (3) queue backlog/failure visibility relies on Laravel's `failed_jobs` table plus manual `queue:monitor`, not yet proven under supervised systemd workers; (4) disk-pressure, retention-failure, and backup-failure signals exist as log lines/command exit codes but have no external alerting wrapper defined in this repo — that's expected to be added at the P7-008/ops layer.

---

## O. Test-suite credibility

Inventory: 118 Feature test files, 40 Unit test files (~1,000+ cases), all Pest, all repeatedly independently *re-executed* by reviewers across review cycles with exact-match pass/assertion counts — a genuine sign of suite determinism and reviewer rigor, not just implementer-reported numbers. Browser/E2E coverage lives entirely in 16 standalone Playwright specs under `verification/`, run manually per task, **not wired into the routine `php artisan test` run** — meaning UI-level regressions (the historical V4-08 flake, the V4-14/V4-18 Alpine scoping bug) are the category most likely to go undetected unless the migration explicitly budgets time to re-run these harnesses against the Linux target.

Legitimate environment-substitute-only coverage, correctly self-skipping (not silently passing):
- `tests/Feature/Deployment/RedisPostureTest.php` — live-capture assertion skips without reachable Redis (posture-logic unit tests still run).
- `tests/Feature/Queue/RedisQueueIntegrationTest.php` — entire test skips without reachable Redis.
- `tests/Feature/MediaMetadataProbeServiceTest.php` — the one real-ffprobe-extraction test skips without ffprobe on PATH.

**Structurally SQLite-only concurrency tests** (`RevisionAppendRaceTest`, `StagingClaimCasProtocolTest`, `TranscriptionClaimConcurrencyTest`, `TranslationClaimConcurrencyTest`, and others) fork real OS processes racing a shared SQLite file to prove CAS invariants — this proves the application-level logic under SQLite's serialized-writer semantics, and does **not** by itself prove the same invariants under Postgres MVCC (this is the same gap as §F's H-2/H-1 findings, restated from the test-credibility angle). `tests/Feature/Database/MigrationPgAuditTest.php` and siblings exist specifically to gate the cutover — reinforcing that the project is aware of this gap but has not yet closed it with real Postgres execution.

**Deleted test** (`tests/Unit/Transcription/ProcessTranscriptionPayloadTest.php`, flagged in `git status`): **confirmed safely superseded, not a coverage gap.** It was relocated Unit → Feature (`tests/Feature/Transcription/ProcessTranscriptionPayloadTest.php` exists and is confirmed on disk) because P7-003 gave the job constructor a config dependency that pushed it out of pure-Unit scope, per `reviews/P7-003-BUILDER-REPORT.md`.

Do not equate this green Windows/SQLite suite with production readiness — it's a strong, well-governed suite for what it tests, but it has never touched Postgres, never run multiple concurrent Redis workers, and its browser-level evidence is not continuously re-run.

---

## P. Pre-Linux blocking findings

Findings in this section are things to fix on the **current** development environment — they do not require Linux to diagnose or resolve.

| Severity | Finding | Where |
|---|---|---|
| **BLOCKER** | **The entire Phase 7 governance record (all waves, decisions, ADR-026, P6-008/009/010, P7-011 closure, P7-009 Phase A) exists only in the uncommitted working tree.** Git HEAD (`4d90d5b`) stops at "docs(governance): close Phase 6." If this working tree were lost, the project's own durable-memory principle would be violated retroactively — none of Phase 7 would have "happened" from git's perspective. | `git log`, `git status`, `git show --stat HEAD` |
| **BLOCKER** | `BackupManager::run('pgsql')` unconditionally refuses to execute. There is currently no working backup mechanism for the datastore this project intends to run in production. | `app/Backup/BackupManager.php:62-69`; `app/Console/Commands/BackupPreMigrate.php:22` |
| **HIGH** | P7-002's most important acceptance criteria (dual-driver suite, migration rehearsal, Phase 6 regression, two-process claim-fencing — all on pgsql) were closed under an environmental exception rather than executed. This should be re-attempted for real (e.g., via a local Postgres container) before, not after, provisioning Linux, since a Postgres instance is trivially obtainable without a production VPS. | `tasks/P7-002-production-datastore-migration.md:5-27` |
| **HIGH** | Several race-harness commands feeding the AC6 Postgres claim-fencing proof hardcode SQLite-only `PRAGMA` syntax that will throw under Postgres rather than skip cleanly. | `app/Console/Commands/TranslationRetryRaceWorker.php:86` and 5 siblings; `tests/Feature/Translation/TranslationOperationalPreflightTest.php:82-86` |
| **HIGH** | TD-008 (order-dependent full-suite flakiness) is explicitly registered as a hard pre-P7-012 prerequisite with **no owning task created**. | `docs/TECHNICAL_DEBT_REGISTER.md` (TD-008 entry); `DECISION-TD-008-REPRIORITIZATION-001` |
| **MEDIUM** | TD-014 (retention staging-claim crash-recovery gap) is a pre-P7-012 follow-up with no owning task. | `docs/TECHNICAL_DEBT_REGISTER.md` (TD-014 entry) |
| **MEDIUM** | PHP extensions required by this app (`gd`, `zip`, `dom`/`xml`, `pdo_sqlite`) are unpinned in `composer.json` and in CI's `setup-php` step — a green CI run does not prove a bare Linux box has them. | `composer.json`; `.github/workflows/tests.yml:20-24` |
| **MEDIUM** | Media streaming/download has no X-Accel-Redirect path; every download ties up a PHP-FPM worker for the full transfer duration. | `app/Http/Controllers/MediaActionController.php:51-169` |
| **LOW/INFO** | `observability:diagnostics` and `media:cleanup-staging` are described as operationally relevant but have no `Schedule::` entry or documented external-poll wrapper anywhere in-repo. | `routes/console.php`; `docs/QUEUE-WORKER-SUPERVISION.md` |
| **LOW/INFO** | AGENTS.md contains one internally stale sentence contradicting its own later P7-011-closure paragraph; `CURRENT_STATE.md`'s date header and P6-009 narrative are stale relative to later, authoritative records. | `AGENTS.md`; `CURRENT_STATE.md` |
| **LOW/INFO** | No formal CPU-vs-GPU decision recorded for model-worker deployment. | `PHASE7-PLANNING.md` / decision register (absence noted, not invented) |
| **LOW/INFO** | `wmic`-based Windows capacity-metadata branch is stale/deprecated dead code in production; harmless. | `app/Capacity/EnvironmentMetadata.php:99-104,124-128` |

---

## Q. Linux-only validation checklist

These are not defects now — they are things that cannot be proven without a real Linux target and must be run there:

- PostgreSQL execution of P7-002 AC1/2/5/6 against the actual production Postgres (in addition to, not instead of, a pre-Linux local-Postgres pass — see §P HIGH).
- Multiple concurrently-supervised Redis `queue:work` workers under systemd, including real SIGTERM graceful-drain (never executed even on Windows) and sustained drain/rollback under load.
- `schedule:run` under an actual installed systemd timer/cron (not just the in-process `Schedule` facade reflection `observability:diagnostics` currently performs).
- Reboot/service-recovery checks (`Restart=always` behavior for queue workers).
- Real model-worker connectivity (faster-whisper large-v3, NLLB) from the Linux host, including the CPU-vs-GPU decision once made.
- Actual 500 MiB (600 MiB-floor) upload through the real Nginx/PHP-FPM stack, including `max_execution_time`/`proxy_read_timeout` reconciliation.
- Disk-pressure and capacity behavior — P7-009 Phase B, currently NOT AUTHORIZED and blocked by target-host readiness.
- The P7-007 backup/restore drill against live Postgres, under separate HPO drill authorization.
- Re-running the Playwright/`verification/` browser harnesses against the deployed target, not just the PHP suite.

---

## R. Ordered Linux provisioning plan

1. Resolve this audit's pre-Linux BLOCKER/HIGH findings (§P) on the current dev environment — commit the Phase 7 governance record, fix `BackupManager` pgsql refusal or explicitly re-scope it, run a local-Postgres pass of P7-002's AC1/2/5/6, fix the SQLite-`PRAGMA` race-harness commands, assign owning tasks to TD-008/TD-014.
2. Linux host/image selection and provisioning (resolve the CPU-vs-GPU model-worker decision first if it affects host sizing).
3. PHP runtime install with the explicit, now-pinned extension list (§J).
4. PostgreSQL install/configuration.
5. Redis install/configuration (password, network exposure per TD-004 resolution).
6. Application deployment.
7. Storage/permissions setup (private disk root, quarantine, staging, backup directories).
8. Run migrations against the real Postgres instance.
9. Install and enable queue-worker systemd services (`Restart=always`).
10. Install and enable the `schedule:run` systemd timer/cron.
11. Install FFmpeg/ffprobe.
12. Confirm/point model workers (per the CPU-vs-GPU decision).
13. Set environment configuration (`ProductionConfigGuard` must pass with zero violations).
14. Configure upload/runtime limits (600 MiB floor, execution/proxy timeouts) at PHP-FPM and web-server layers.
15. Run `deployment:verify --strict`.
16. Run `observability:diagnostics --probe-worker --strict`.
17. Execute PostgreSQL parity tests for real (the P7-002 AC1/2/5/6 re-run, this time genuinely on the target).
18. Execute Redis concurrency tests with multiple real supervised workers.
19. Reboot/recovery checks.
20. Re-run target-host readiness (`P7-009-TARGET-HOST-READINESS` equivalent) — must return `TARGET_HOST_READY` before proceeding.
21. Authorize and execute P7-009 Phase B.
22. Obtain separate HPO drill authorization and execute the P7-007 final backup/restore drill.
23. Close remaining OPEN technical debt gated by G-01..G-13 (§E), then run P7-012 (final production-readiness gate).

---

## S. Remaining Phase 7/release path

Sequencing, given current state: (1) commit the Phase 7 governance record now — this has zero dependency on anything else and is the cheapest, highest-leverage fix available; (2) close TD-008 and TD-014 by assigning owning tasks; (3) resolve the PostgreSQL execution gap on a local/dev Postgres instance (does not require Linux); (4) fix the `BackupManager` pgsql refusal and the SQLite-`PRAGMA` race-harness commands; (5) only then provision the Linux target per §R; (6) authorize and run P7-009 Phase B on the ready target; (7) obtain HPO drill authorization and run the P7-007 final drill; (8) confirm all G-01..G-13 gate conditions are met; (9) run P7-012; (10) Phase 7 HPO closure. Release is not complete during or after this audit — this audit only clears the way to Linux provisioning, contingent on the pre-Linux fixes in §P.

---

## T. Final verdict

**`PRE_LINUX_CHANGES_REQUIRED`**

Not because Phases 1–6 are unsound (they are coherently closed with real independent-review evidence and well-frozen invariants), and not because Phase 7's engineering is careless (it is deliberate and mostly well-instrumented, and honest about its own environmental exceptions). The verdict is driven by four items that are fixable right now, on the current Windows dev box, at zero cost of a VPS: an uncommitted governance record for an entire phase of work, a backup mechanism that refuses to run on the very database engine production will use, a claim-fencing test suite that will throw (not skip) the moment anyone points it at Postgres, and two technical-debt items gating the final release gate with no one assigned to close them. Fix these, then re-run this audit's checklist once more before spending money on the Linux target.

---

## U. Exact next legal action

Per `PRE_LINUX_CHANGES_REQUIRED`: **correct only the identified pre-Linux blockers (§P) on the current development environment, have those corrections independently reviewed, then repeat this pre-Linux audit before provisioning the target.** Do not provision the Linux VPS yet. Do not authorize P7-009 Phase B. Do not execute the P7-007 final drill. Do not begin P7-012. The Human Product Owner should additionally decide: (a) who owns TD-008 and TD-014, (b) the CPU-vs-GPU model-worker deployment decision, and (c) whether committing the Phase 7 working tree happens as one governance-only commit or is folded into the next task-closure commit — this is a product/process decision, not one this reviewer will make.
