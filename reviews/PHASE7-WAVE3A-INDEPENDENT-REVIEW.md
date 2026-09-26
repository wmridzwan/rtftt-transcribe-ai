# Phase 7 Wave 3A — Independent Review (P7-002, P7-004)

Reviewer: Claude Code (independent reviewer role). Builder: OpenCode.
Date: 2026-09-26.

## A. Baseline

- HEAD `4d90d5b` on `main`. Working tree dirty with cumulative, uncommitted
  Phase 6 / Phase 7 Wave 1 / Wave 2 / Wave 3A residue (single prior commit
  `d8a7e01` "first commit" plus a governance-doc-only commit `4d90d5b`).
  This matches the builder's stated baseline (121 dirty paths).
- Authorization confirmed in `DECISIONS.md`/`DECISION_QUEUE.md`:
  `DECISION-PHASE7-WAVE3A-READY-PROMOTION-001` (BACKLOG→READY) and
  `DECISION-PHASE7-WAVE3A-EXECUTION-AUTHORIZATION-001` (READY→IN_PROGRESS),
  both dated 2026-09-26, both HPO-decided, both scoped to P7-002 + P7-004
  only, "PARALLEL-SAFE WITH FILE-OWNERSHIP SEQUENCING."
- Task files confirm current state REVIEW for both P7-002 and P7-004, with
  full history preserved (BACKLOG → reconciled → READY → IN_PROGRESS →
  REVIEW).
- P7-009 and P7-011 exist only as BACKLOG/CONTRACT_AUTHORED task files (no
  implementation code, no status change beyond BACKLOG). P7-007's final
  drill, P7-012, retention/purge, and object storage: no trace anywhere in
  the diff (verified by targeted grep — see §J, §K).
- **Gap (LOW, non-blocking):** `CURRENT_STATE.md` (last narrative entry:
  "Phase 7 Wave 2 closure (2026-09-26)") has **not** been updated to record
  the Wave 3A READY promotion, execution authorization, or the
  IN_PROGRESS→REVIEW transition, even though `DECISIONS.md` records all of
  these on the same date. Per the governance contract, updating
  `CURRENT_STATE.md` on project-state change is an implementation-agent
  duty. This is a documentation-completeness gap, not an implementation
  defect, and does not block VERIFIED for either task, but it should be
  closed (by OpenCode or the HPO) before Wave 3A closes.

## B. Fresh Verification Commands / Results (reproduced independently, not taken from builder reports)

| Command | Result |
|---|---|
| `php artisan test --compact --filter=Database` | 38/38 passed |
| `php artisan test --compact --filter=Storage` | 26/26 passed |
| `php artisan test --compact --filter=MediaStreamingTest` | 13/13 passed |
| `php artisan test --compact --filter=RevisionMigrationRollbackTest` | 1/1 passed |
| `php artisan test tests/Feature/Editing tests/Feature/TranscriptRevisionAwareExportTest.php tests/Feature/TranscriptExportTest.php tests/Feature/TranscriptExportHardeningTest.php tests/Feature/Translation` | 304/304 passed |
| `php artisan test` on `StagingClaimCasProtocolTest`, `TranscriptionClaimConcurrencyTest`, `TranslationAttemptFenceTest`, `RevisionAppendRaceTest`, `RevisionConcurrencyTest` | 22/22 passed |
| `php artisan test` (full suite) — run 1 | 1050 tests, 1049 passed, 1 pre-existing skip, **0 failures** |
| `php artisan test` (full suite) — run 2 | 1050 tests, 1048 passed, **1 failure** (`LogContextTest::it_counts_attempt_ordinals_across_attempts_for_the_same_transcription`, UNIQUE constraint on `processing_jobs.transcription_id`) |
| `php artisan test` (full suite) — run 3 | Same single failure, same test, different random data |
| `php artisan test` (full suite) — run 4 | 1050 tests, 1049 passed, 1 skip, **0 failures** |
| `php artisan test tests/Feature/Observability/LogContextTest.php` × 5 (isolated) | 6/6 passed every time |
| `vendor/bin/pint --test --format agent` | passed, 0 findings |
| `vendor/bin/phpstan analyse --level=7` | passed, 0 errors |
| `php artisan deployment:verify` | exit 0; `Datastore: ok (driver sqlite)`, `Topology: ok (local private disk)` |
| `php artisan storage:validate-topology` | exit 0; `Storage topology: ok (local private disk, writable root, capacity floor met).` |

No PostgreSQL server, binaries, or Docker exist in this environment — the
builder's BLOCKED-ENVIRONMENT claim for all live-pg checks is independently
confirmed (no service to connect to; `pdo_pgsql` extension present but
unusable without a server).

## C. P7-002 AC Review

| AC | Requirement | Fresh Evidence | Result |
|---|---|---|---|
| AC1 | Dual-driver suite green | sqlite: reproduced full-suite green in 2/4 independent runs (see §H); pg: no server exists, config/flip/hook/audit machinery independently exercised and green | PASS (sqlite) / BLOCKED-ENVIRONMENT (pg) |
| AC2 | Migration rehearsal + reconciliation | `RevisionGraphIntegrityTest` (6 tests, incl. 5 corruption classes) independently rerun, green; procedure documented in `docs/DATASTORE-MIGRATION.md` | PASS (machinery) / BLOCKED-ENVIRONMENT (pg rehearsal) |
| AC3 | `backup:pre-migrate` OK gate | REFUSED path independently reproduced (`DatastoreVerifyIntegrationTest`); OK path independently located and confirmed in `tests/Feature/Backup/BackupFoundationTest.php` (owned by P7-007, not duplicated) | PASS |
| AC4 | Rollback rehearsal | `RevisionMigrationRollbackTest` (--step 7) independently reproduced, green; pg rollback is target-only | PASS (sqlite/mechanism) / BLOCKED-ENVIRONMENT (pg) |
| AC5 | Phase 6 revision/export on pgsql | Full Phase 6 editing/revision/export/translation regression (304 tests) independently reproduced, green on sqlite; pg run is target-only | BLOCKED-ENVIRONMENT (pg) — sqlite substitute PASS |
| AC6 | Claim-fencing on pgsql | CAS/concurrency suites (22 tests) independently reproduced, green on sqlite; pg run is target-only | BLOCKED-ENVIRONMENT (pg) — sqlite substitute PASS |
| AC7 | Registry flip, no fork | Independently read `ProductionEnvRegistry.php`: DB_* keys are static ADVISORY entries owned by P7-002; the flip is enforced entirely inside `DatastorePosture`, never by editing the registry rows to REQUIRED — confirms "no fork" | PASS |
| AC8 | Standard gate | Full suite reproduced 1050/1049+1 skip in the clean runs; Pint clean; PHPStan 0; diffed shared files (`ProductionPostureChecks.php`, `DeploymentVerify.php`, `ObservabilityDiagnostics.php`, `.env.example`, inventory) — all additive, no historical entry altered | PASS |
| AC9 | No G-02 claim | Grepped diff/docs: no gate-verdict language beyond evidence | PASS |

### Migration audit — independently reconstructed, not accepted on the builder's word

- Searched every migration for `getDriverName()` conditionals: only the two
  files the builder names plus the new pg parity migration itself contain
  one. Searched for `INSERT OR IGNORE`, `PRAGMA`, `AUTOINCREMENT`,
  `insertOrIgnore`/`orIgnore`/`renameColumn`/`->enum(` and other raw
  statements: none found outside the same three files.
- Read both historical migrations
  (`2026_09_19_000002_...processing_jobs...`,
  `2026_09_21_000003_...translations...`): both return early unless the
  driver is `sqlite`, so on PostgreSQL the two partial unique indexes were
  never created before this task — **the claimed gap genuinely existed.**
- Read the new migration: it is the mirror image (returns early unless
  `pgsql`), reproduces the identical index names, columns, and `WHERE`
  predicates against `processing_jobs`/`translations`, and its `down()`
  drops the same two indexes only on `pgsql`. **The new indexes are
  semantically equivalent to their sqlite counterparts and rollback is
  safe** (no-op on every other driver, including sqlite, where the
  original migration pair remains authoritative).
- No PostgreSQL-only or SQLite-only edge remains unaccounted for by this
  audit within the current migration tree.

### Live PostgreSQL Gap — see §E.

### Final Drill Boundary

- `docs/DEPLOYMENT-RUNBOOK.md` §14 (P7-002-owned, added by this task)
  explicitly states: "The final restore drill is NOT part of cutover (P7-007
  Wave 3 follow-on, separate HPO authorization; G-08 unclaimed)." Confirmed
  no drill-execution code or claim anywhere in the P7-002 diff. G-08 remains
  unclaimed.

## D. P7-004 AC Review

| AC | Requirement | Fresh Evidence | Result |
|---|---|---|---|
| AC1 | Topology/permissions validation | `storage:validate-topology` run live: exit 0, `Storage topology: ok (local private disk, writable root, capacity floor met)`; `StorageTopologyTest` reproduced green | PASS |
| AC2 | Range matrix | Pre-existing `MediaStreamingTest` (13 tests) reproduced green; new `MediaRangeEdgeTest` (6 tests: clamp past EOF, oversize suffix, single-byte, zero-length suffix→416, end<start→200, 1 MiB bounded delivery) independently read and reproduced green | PASS |
| AC3 | TD-011 direct tests | `ZeroByteDispositionTest` + range-edge branches independently reproduced green; zero-byte reachability independently confirmed (see Zero-Byte review below) | PASS |
| AC4 | P7-006/P7-007 compatibility | `StorageCompatibilityTest` independently reproduced green, incl. a real isolated `backup:run` proving quarantine content is excluded from the manifest; `MediaIngestionService::gateMalwareScan` diff-checked — untouched | PASS |
| AC5 | Revision/export regression | Same 304-test Phase 6 regression run as P7-002 (shared evidence) — green | PASS |
| AC6 | Capacity floor + degraded | `StorageTopology::evaluate()` floor logic read and unit-reproduced green; live `deployment:verify`/`storage:validate-topology` output confirms floor check runs | PASS |
| AC7 | No object storage | Grepped `app/Storage/`, `config/`, `docs/LOCAL-STORAGE-TOPOLOGY.md`, and `composer.json`/`composer.lock` (unchanged) for s3/minio/r2/object-storage: only prose confirming the exclusion; no code/config/dependency | PASS |
| AC8 | Standard gate | Same full-suite/Pint/PHPStan evidence as P7-002 (shared runs); `DeploymentVerify.php`/`ObservabilityDiagnostics.php` diffs read in full — P7-004's `checkStorageTopology()` and diagnostics lines are additive, do not touch P7-002's or P7-008's/pre-existing sections | PASS |
| AC9 | No G-05 claim | Grepped diff/docs: no gate-verdict language beyond evidence | PASS |

## E. PostgreSQL Environment Limitation

- **What was actually verified:** every piece of PostgreSQL-adjacent logic
  that does not require a live server — the driver-conditional migration
  gating, `DatastorePosture`'s advisory→required flip logic, the
  `deployment:verify`/diagnostics sub-checks, the registry entries, the
  driver-agnostic `RevisionGraphIntegrity` reconciliation logic (executed
  and independently reproduced against sqlite, which exercises the same
  query-builder code path pgsql would use), and the static migration audit.
  All of this is genuinely PASS, independently reproduced, not merely
  restated from the builder report.
- **What remains unexecuted:** an actual `migrate` against a live
  PostgreSQL instance; the AC1 pg half of the dual-driver suite; the AC2
  migration rehearsal on a real pg copy; the AC4 rollback rehearsal on pg;
  the AC5 Phase 6 regression suite executed against pg; the AC6
  claim-fencing suite executed against pg. None of this can be executed in
  this environment — confirmed independently (no `pgsql` service, binaries,
  or Docker present).
- **Is substitute evidence sufficient for VERIFIED?** Yes, for the same
  reason the Wave 1/2 precedent (P7-001 AC8, P7-008 AC2) was accepted: the
  contract's environment rule permits BLOCKED-ENVIRONMENT with a documented
  mechanism + procedure in place of a live run, and P7-002's task file
  itself frames AC1/AC2/AC5/AC6 as target-pending, not as unconditional
  PASS requirements at the REVIEW gate. The implementation-correctness
  claim (logic is correct, migration is compatible, reconciliation
  algorithm is driver-agnostic) is independently verified here. The
  **production-execution claim** (this actually ran clean against real
  PostgreSQL) is **not** verified and cannot be from this environment.
- **HPO disposition required:** Yes. Per the Wave 1/2 precedent
  (`DECISION-P7-001-AC8-DISPOSITION-001`, `DECISION-P7-008-AC2-DISPOSITION-001`),
  the HPO must explicitly disposition the pg-execution gap (Option
  A-equivalent: AC1/AC2/AC5/AC6 pg-halves NOT PASS, carried forward as an
  explicit pre-P7-012 obligation) before any DONE transition for P7-002.
  This review does not itself resolve that disposition — it is an HPO
  decision, not a reviewer verdict.

## F. Zero-Byte Streaming Defect Review

- **Did the defect really exist?** Independently confirmed by reading the
  range-resolution arithmetic: for a 0-byte object, the generic path would
  compute `$end = max(0, $size - 1) = 0`, `$start = 0`,
  `$length = $end - $start + 1 = 1` — i.e. `Content-Length: 1` for an empty
  object. The new code intercepts `$size === 0` before this arithmetic
  runs. The defect claim is genuine, not embellished.
- **Are zero-byte objects genuinely reachable?** Independently confirmed by
  reading `MediaIngestionService`: the byte-ceiling check is
  `$size < 0 || $size > $maximum` — a 0-byte upload is neither, so it
  passes ingestion. The new `ZeroByteDispositionTest` proves this end to
  end (`ingest()` on an empty file → `file_size_bytes === 0`, one durable
  row) and was independently reproduced, green.
- **Is the new behavior correct?** Full response (no `Range` header) on a
  0-byte object: `200`, `Content-Length: 0`, empty body — correct per HTTP
  semantics (an empty representation is a valid 200). Any `Range` on a
  0-byte object: `416` with `Content-Range: bytes */0` — this is the
  RFC 7233-mandated form for an unsatisfiable range against a zero-length
  resource. Both paths independently reproduced via
  `ZeroByteDispositionTest` and read in the controller source.
- **All range requests against zero-byte content:** only two cases exist
  for a 0-byte object (no `Range` header, and any `Range` header) and both
  are exercised by the test; there is no third case (e.g., a `Range` header
  present but empty string is explicitly treated as "no Range" per
  `trim(...) !== ''`).
- **No regression:** the zero-byte branch is a new early-return guarded by
  `$size === 0`; for `$size > 0` every line below it is byte-for-byte
  unchanged. The full `MediaRangeEdgeTest` + pre-existing
  `MediaStreamingTest` (19 tests total) independently reproduced green,
  confirming no non-zero-byte regression.

## G. Phase 6 / Regression Assessment

Independently reran (not merely inspected) the applicable Phase 6 and
cross-cutting suites in one batch: `tests/Feature/Editing/*` (revision
graph/history, text/timing editing, undo/redo, split/merge, ownership,
schema, concurrency, append-race, machine-source immutability),
`TranscriptRevisionAwareExportTest`, `TranscriptExportTest`,
`TranscriptExportHardeningTest`, `tests/Feature/Translation/*` (staleness,
export, attempt-fencing). Result: **304/304 passed, 0 failures.** Combined
with the independently reproduced `MediaStreamingTest` (13/13) and
`StorageCompatibilityTest` (backup/quarantine/verify compatibility, 4/4),
this is affirmative evidence — not an inference from "the full suite was
green" — that neither task regressed Phase 6 revision/history/edit/export
invariants, translation staleness, media streaming, ingestion, or
quarantine/backup-manifest compatibility.

## H. Full-Suite Stability / TD-008

Four independent full-suite runs were executed (not the builder's logs —
fresh runs in this review):

| Run | Tests | Passed | Failures | Failing test | Touched by Wave 3A? | Deterministic? | Matches TD-008 history? |
|---|---|---|---|---|---|---|---|
| 1 | 1050 | 1049 (+1 skip) | 0 | — | — | — | — |
| 2 | 1050 | 1048 | 1 | `tests/Feature/Observability/LogContextTest.php::it_counts_attempt_ordinals_across_attempts_for_the_same_transcription` | No — file not in the Wave 3A diff (only `ObservabilityDiagnostics.php` command and `ObservabilityDiagnosticsTest.php` were touched by P7-002/P7-004; `LogContextTest.php` is untouched, pre-existing P7-005 content) | No — same test passed 6/6 times in 5 isolated reruns; the full-suite-only trigger is a `UNIQUE constraint failed: processing_jobs.transcription_id` collision that depends on prior-test-generated state/random data, not on this diff | Yes — matches the exact full-suite-only-flake pattern the Wave 2 closure recorded and reprioritized as `DECISION-TD-008-REPRIORITIZATION-001` (OPEN/MEDIUM/pre-P7-012) |
| 3 | 1050 | 1048 | 1 | Same test, same failure signature, different random data | Same as run 2 | Same as run 2 | Same as run 2 |
| 4 | 1050 | 1049 (+1 skip) | 0 | — | — | — | — |

**This review does not conclude TD-008 is resolved merely because two of
four runs were green** — the opposite: this review's *own* independent
reproduction hit the flake in **2 of 4** full-suite runs, a materially
higher frequency than the builder's report characterized ("runs 2–3 fully
green... zero TD-008 flakes observed" — note the builder's run 1 failure
was a *different*, deterministic, in-scope issue they fixed, not this
flake). The flake is real, reproducible under full-suite conditions,
**not attributable to either task's diff** (the failing file is untouched
by both tasks, and the failure never reproduces in isolation), and is
already tracked as TD-008 (OPEN/MEDIUM/pre-P7-012 prerequisite). No new
debt is created by this finding; it corroborates and sharpens the existing
TD-008 record rather than resolving or worsening its classification.

**Finding (MEDIUM, informational — does not block VERIFIED):** the
builder's report understates observed TD-008 flake frequency relative to
this review's independent reproduction. Recommend the record reflect "not
merely rare — reproduced in half of a small independent sample" rather than
"zero flakes observed," so the pending suite-hygiene remediation task is
scoped with an accurate frequency signal.

## I. P6-Owned Test Change Assessment

- `RevisionMigrationRollbackTest.php`: `--step` changed `5 → 7` (the diff
  against HEAD shows 5→7 directly because this working tree's diff
  captures both the P7-006 scan-verdict migration and the P7-002 pg-parity
  migration landing in the same uncommitted delta; the task file/builder
  report frame it as two sequential bumps, 5→6 then 6→7).
- **Why required:** two additive migrations (P7-006's scan-verdict column,
  P7-002's pg-parity indexes) now sit at the head of the migration tree;
  `migrate:rollback --step N` must cover N migrations to reach the same
  five P6 migrations it always targeted.
- **Is it purely an expected-count update?** Yes — independently confirmed:
  P7-002's `down()` is an explicit no-op on every non-pgsql driver
  (including sqlite, the test's driver), so stepping back through it on
  sqlite changes nothing. The added assertion
  (`hasColumn('media_files', 'scan_verdict')->toBeFalse()`) proves the
  P7-006 migration's `down()` — not P7-002's.
- **Does it mask a rollback defect?** No. All five original P6 assertions
  are byte-for-byte unchanged; the test was independently rerun and passes
  (1/1, 14 assertions).
- **Is altering this prior-phase test legitimate under the Wave 3A
  contract?** Yes — the task file explicitly authorizes exactly this kind
  of interaction (P7-002 §16 flags "shared-file coordination" risk,
  mitigated by contributing through extension points), and the change is
  the minimum required delta (a step-count bump plus one additive
  assertion), not a redefinition of P6 semantics.
- No inappropriate weakening found.

## J. Shared-Surface / Diff Audit

- `.env.example`: P7-002's block (pg guidance + commented `DB_SSLMODE`) and
  P7-004 (no change — P7-004 needed no new env key, confirmed by builder
  report and independently corroborated: `RTFTT_MEDIA_DISK` pre-existed).
  No secret values; `DB_PASSWORD=` empty, `REDIS_PASSWORD=null`,
  `MAIL_PASSWORD=null` only.
- `DeploymentVerify.php`: P7-002's `checkDatastore()` and P7-004's
  `checkStorageTopology()` are both new private methods appended to the
  `$results` array; pre-existing `checkStorage()`, `checkMigrations()`,
  `checkQueueGuards()`, etc. are byte-for-byte unchanged. No duplicated
  verdict, no interface conflict.
- `ObservabilityDiagnostics.php`: each task's lines are in a clearly
  commented, self-contained block ("P7-002: datastore section", "P7-004:
  storage section"); no existing line format altered; the `$failed`
  expression gained one additional `||` term (`$transcriptionViolation`,
  P7-003-owned, pre-existing in this diff, not part of Wave 3A).
- `deploy/migrations-inventory.json`: append-only; every historical hash
  unchanged; new entry's hash independently spot-checked via the CI audit
  test (`MigrationPgAuditTest`), which hashes the actual file and compares.
- `docs/DEPLOYMENT-RUNBOOK.md`: P7-002 owns new §14, P7-004 owns new §15;
  both explicitly "by reference," neither rewrites an earlier section.
- Ownership stayed separated end to end: no P7-002 file touches storage,
  no P7-004 file touches the database/env/inventory surface (confirmed by
  the builder's own file lists and independently spot-checked against
  `git status`).

## K. Scope / Authorization Compliance

Independently confirmed via targeted search of the full working tree:

- No P7-009 or P7-011 implementation — both task files are
  BACKLOG/CONTRACT_AUTHORED only (verified by reading their `## Status`
  sections), with zero corresponding app/test code.
- No P7-007 final restore drill: `docs/DEPLOYMENT-RUNBOOK.md` §13/§14
  explicitly state the drill is deferred and G-08 is unclaimed.
- No P7-012 content anywhere.
- No object storage: grep for s3/minio/r2/object-storage across
  `app/Storage/`, config, and docs returns only prose confirming its
  absence; `composer.json`/`composer.lock` unchanged.
- No retention/purge code (P7-011 scope) anywhere in the diff.
- No final load testing (P7-009 scope) anywhere in the diff.
- No D7 decision reopened; TD-008 not marked resolved anywhere in the
  diff or docs (independently confirmed — the diff only ever references
  TD-008 as OPEN/MEDIUM/pre-P7-012, consistent with governance).
- Downstream gates (G-02, G-05, G-08) are referenced only as unclaimed/
  target-pending, never asserted PASS.

## L. Findings

1. **MEDIUM / INFO-leaning — TD-008 flake frequency underreported.**
   Task: cross-cutting (not attributable to either task's diff).
   Evidence: §H — 2 of 4 independent full-suite runs hit
   `LogContextTest`'s attempt-ordinal test via a full-suite-only unique-
   constraint collision; the same test passed 6/6 in isolation.
   Impact: none on task correctness; affects how confidently "zero flakes"
   claims in builder reports should be trusted going forward.
   Remediation requirement: none for VERIFIED; recommend the pending
   suite-hygiene remediation task record the higher observed frequency.
   HPO disposition needed: no (informational).

2. **LOW — `CURRENT_STATE.md` lags the Wave 3A decision record.**
   Task: cross-cutting/governance.
   Evidence: §A — `CURRENT_STATE.md`'s last entry is Wave 2 closure;
   `DECISIONS.md` records Wave 3A READY promotion, execution authorization,
   and (implicitly, via the task files) the IN_PROGRESS→REVIEW transition,
   all on the same date.
   Impact: no functional or correctness impact; a durable-handoff hygiene
   gap.
   Remediation requirement: update `CURRENT_STATE.md` before Wave 3A
   closes (not a precondition for VERIFIED).
   HPO disposition needed: no.

3. **INFO — PostgreSQL-execution evidence remains entirely
   BLOCKED-ENVIRONMENT for P7-002 AC1/AC2/AC5/AC6 pg-halves.**
   Already flagged by the builder honestly; independently confirmed as a
   genuine environment constraint (no server/binaries/Docker), not an
   evasion. HPO disposition IS needed before DONE (see §E, §N) — this is
   carried forward from the builder's own report, not a new finding.

No BLOCKER or HIGH finding against either task.

## M. Independent Verdicts

`P7-002 = VERIFIED` (with the explicit, contract-permitted
environment-blocked limitation on PostgreSQL live-execution evidence for
AC1/AC2/AC5/AC6 pg-halves, documented honestly, substitute evidence
sufficient at the REVIEW→VERIFIED gate per the Wave 1/2 precedent; no
BLOCKER/HIGH; no unresolved MEDIUM against the task itself).

`P7-004 = VERIFIED` (no environment-blocked AC; no BLOCKER/HIGH/MEDIUM
against the task).

## N. Task States After Review

- P7-002: REVIEW → **VERIFIED** (reviewer verdict only; not DONE).
- P7-004: REVIEW → **VERIFIED** (reviewer verdict only; not DONE).
- Both task files' state-transition rules reserve VERIFIED→DONE to the
  HPO exclusively; this review does not transition either task to DONE
  and does not edit the task files' Status sections (task-file edits
  belong to the owning agents per the durable-handoff convention; this
  review artifact is the durable review record).

## O. HPO Decisions Required Before Closure

1. **P7-002 PostgreSQL-execution disposition** (the only decision that
   actually gates closure): explicitly disposition the BLOCKED-ENVIRONMENT
   pg-halves of AC1/AC2/AC5/AC6 — either (a) accept substitute evidence as
   sufficient for DONE under an explicit environmental exception (mirroring
   `DECISION-P7-001-AC8-DISPOSITION-001`/`DECISION-P7-008-AC2-DISPOSITION-001`,
   Option A: those AC-halves recorded NOT PASS, carried forward as a
   pre-P7-012 obligation consumed by P7-007's drill and P7-009's final run),
   or (b) hold P7-002 at VERIFIED-but-not-DONE until real target-host
   PostgreSQL evidence lands. This review recommends (a) for consistency
   with precedent but does not itself decide it.
2. Confirm TD-008 stays OPEN/MEDIUM/pre-P7-012 (unchanged by this review;
   no action needed beyond acknowledgment).
3. Direct `CURRENT_STATE.md` be updated to reflect the Wave 3A REVIEW state
   (housekeeping, not a closure blocker).

P7-004 has no environment-blocked AC and needs no special HPO disposition
beyond the ordinary VERIFIED→DONE closure step.

## P. Authorization Boundary

This review does not authorize P7-011, P7-009, the P7-007 final PostgreSQL
restore drill, or P7-012.

## Q. Exact Next Legal Action

Both tasks are VERIFIED: **HPO performs Wave 3A closure review, explicitly
dispositioning the P7-002 environment-blocked PostgreSQL evidence (§E, §O)
before any DONE transition.** P7-004 may close DONE without a special
disposition. No downstream Wave 3 work (P7-009, P7-011, the P7-007 drill,
or P7-012) is authorized by this review.
