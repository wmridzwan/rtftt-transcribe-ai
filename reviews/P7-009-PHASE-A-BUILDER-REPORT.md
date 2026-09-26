# P7-009 Phase A Builder Report — Harness + Rehearsal Only

Task: `tasks/P7-009-performance-load-validation.md` (READY → IN_PROGRESS 2026-09-26, authorized `DECISION-P7-009-PHASE-A-EXECUTION-AUTHORIZATION-001`).
Builder: OpenCode. No self-verification; no VERIFIED/DONE claim. Phase B (final production-shaped capacity run) is NOT AUTHORIZED.

## Baseline

- HEAD: `4d90d5b`; branch `main`; heavily dirty tree at start (prior-wave residue preserved; no reset/clean/stash).
- Governance recorded first: Phase-A-only execution authorization (`DECISION-P7-009-PHASE-A-EXECUTION-AUTHORIZATION-001` in `DECISION_QUEUE.md` + `DECISIONS.md`), lifecycle transition to IN_PROGRESS in the task file.

## Files changed (new unless noted)

- `app/Capacity/CapacityTimer.php` (new) — monotonic hrtime boundaries; null (never fabricated zero) on incomplete boundaries.
- `app/Capacity/CapacityEnvelope.php` (new) — D7-08/A line items for exactly the 9 contract-approved workloads (upload, probe, transcription, translation, queue, saturation, streaming, render, export); measure-and-report, no thresholds, no verdicts; `toMarkdown()` carries the REHEARSAL/SUBSTITUTE banner.
- `app/Capacity/CorpusBuilder.php` (new) — seeded deterministic synthetic corpus (WAV PCM tone, PRNG blob, code-switch segment fixture); characteristics + SHA-256 per item; no customer media.
- `app/Capacity/EnvironmentMetadata.php` (new) — commit/OS/CPU/memory/PHP/DB/Redis/queue/storage/model/app-config capture; `environment_classification` = `REHEARSAL / SUBSTITUTE` in Phase A.
- `app/Capacity/CompletionLedger.php` (new) — dispatched/completed/failed/skipped per workload + `reconcile()` (readiness-review LOW finding addressed).
- `app/Capacity/WorkloadResult.php` (new) — per-repeat value object: boundaries, elapsed, concurrency config, outcome, correctness, failure preservation fields.
- `app/Capacity/CapacityStats.php` (new) — count/min/max/mean/median/stddev over retained raw values; outliers never discarded (exclusion notes supported, none used).
- `app/Capacity/WorkloadRunner.php` (new) — executes all 9 workloads against real in-app paths at rehearsal scale with correctness-checked timing and failure preservation (details in § Workload honesty).
- `app/Capacity/CapacityReport.php` (new) — rehearsal report renderer; banner + no-verdict boundary in every report.
- `app/Jobs/CapacityProbeJob.php` (new) — deterministic probe job (fixed SHA-256 slice + cache marker) for dispatch → execution → completion proof on any driver.
- `app/Console/Commands/CapacityValidate.php` (new) — `capacity:validate [--repeat] [--concurrency] [--seed] [--blob-bytes] [--segments] [--output]`; writes the full evidence bundle; exit 0 iff harness executed AND accounting reconciles (app degradations are reported, never fail the run).
- `tests/Feature/Capacity/CapacityHarnessTest.php` (new) — 8 self-tests: timer math, incomplete-boundary honesty, corpus reproducibility/integrity, ledger reconcile/mismatch, increment accounting, stats + raw preservation, empty-summary honesty, envelope category fidelity.
- `tests/Feature/Capacity/CapacityValidateCommandTest.php` (new) — end-to-end rehearsal: exit 0, 9 evidence files, REHEARSAL banner + no-verdict + Phase-B-withheld strings, accounting reconciled per workload, 9 raw results with required keys, classification field, storage hygiene (no `capacity/` residue).
- `verification/p7-009/CAPACITY-ENVELOPE.md` (new) — canonical envelope (run id CANONICAL).
- `verification/p7-009/p7-009-20260926-165338-a5dbaf19/` (new) — rehearsal run 1 bundle (9 files).
- `verification/p7-009/p7-009-20260926-165451-1d8e17a7/` (new) — rehearsal run 2 bundle (9 files; final evidence after the INFO-level metadata-key correction below).
- `verification/p7-009/p7-009-phase-a-verify-output.txt`, `p7-009-phase-a-diagnostics-output.txt` (new) — deployment/diagnostics gate evidence.

No P7-007 drill, P7-012, TD-007/TD-008/TD-014, or unrelated content. No other owner's file touched.

## Workload honesty (what each workload actually measures)

- upload: storage write + SHA-256 round-trip read at 256 KiB rehearsal scale. NOT G-01.
- probe: real `MediaMetadataProbeService::probe` on the synthetic WAV; real ffprobe on this box returned duration 5s / pcm_s16le / 8 kHz / mono — matching the generated fixture (genuine end-to-path proof, not a stub).
- transcription: segment bulk-insert + ordered read-back under a real parent row inside a rolled-back transaction (FK-safe, zero residue); synthetic RTF vs declared 300 s synthetic duration. NOT large-v3 inference.
- translation: in-memory alignment transform (index/timestamp/language copy) mirroring the writer guarantee. NOT NLLB.
- queue: CapacityProbeJob batches on the configured `database` driver with bounded `queue:work --once` passes; cache markers verified byte-equal. True broker concurrency is target-only.
- saturation: sequential ramp at batch levels [1,3,5]; per-level latency/throughput recorded; no degradation observed at rehearsal scale (reported, not hidden; dev-machine point is NOT target capacity).
- streaming: full-object read + 64 KiB range-slice read with byte correctness (P7-004 bounded-delivery discipline at rehearsal scale).
- render: server-side `TranscriptCopy::fullText` composition over hydrated segment models; browser timing method retained, not executed.
- export: TXT assembly mirroring the controller + SRT rows via canonical `SegmentTimestamp::srt()`; first/last/numbering verified. HTTP-download timing deferred.

## AC results (Phase A scope)

| AC | Verdict | Evidence |
|---|---|---|
| AC1 envelope doc | PASS (rehearsal) | Canonical + per-run `CAPACITY-ENVELOPE.md`; 9/9 contract categories, no thresholds |
| AC2 G-01 artifact | DEFERRED (Phase B) | Not attempted; durable-write path rehearsed only; no 500 MiB claim anywhere |
| AC3 RTF + translation | PATH-ONLY (Phase B for models) | Persistence + alignment timing with correctness; no inference claim |
| AC4 saturation/degradation | PASS (rehearsal scale) | Levels [1,3,5] with latency/throughput; no degradation observed, honestly reported |
| AC5 streaming/render/export | PASS (rehearsal scope) | Byte/line/numbering correctness alongside timing; browser + HTTP dimensions deferred |
| AC6 no finding hidden | PASS | 0 failures across 54 repeats; variance visible (queue 168 vs 356 ms across runs); run-1 metadata defect disclosed below |
| AC7 standard gate | PASS | Full suite 1086/1085+1 pre-existing skip; Pint clean; PHPStan L7 0 errors |
| AC8 no gate verdict claimed | PASS | No verdict in code/docs/evidence; reports state NONE explicitly |

## Test / verification matrix

- New P7-009 suites: 9/9 green, 169 assertions (8 harness self-tests + 1 end-to-end command test).
- Full suite: 1086 tests, 1085 passed, 1 pre-existing skip, 0 failures, 4235 assertions; 4 pre-existing warnings. TD-008: zero flakes observed — stays OPEN regardless.
- Pint clean; PHPStan level 7: 0 errors (4 genuine findings fixed properly: iterable value types, two always-true comparisons replaced with count()/direct access, redundant method_exists removed).
- Builder-corrected defects (in-scope, reported not hidden): (1) transcription workload initially used FK-violating synthetic parent id — reworked to real factory parent in a rolled-back transaction (zero residue); (2) environment model keys read non-existent `transcription.worker.*` config — corrected to `transcription.model` + `worker_url` (run 1 retains the `unknown (unknown)` values as superseded evidence; run 2 is the final bundle); (3) canonical envelope first written via tinker redirect captured 82 garbage bytes — regenerated via a booted script (content verified).
- Live evidence: two rehearsal runs (run 1 `a5dbaf19`, run 2 `1d8e17a7`), each 27/27 repeats completed, accounting reconciled, 0 failures; `deployment:verify` passed (honest advisories: post_max_size 520M < 600M floor, no backup sets, target-evidence PENDING — all pre-existing substitute-infrastructure facts, unchanged by this task); `observability:diagnostics` complete.

## TD mapping

- TD-001/TD-002: untouched, stay OPEN (evidence supplied only at Phase B; closure at P7-012).
- TD-007/TD-008: untouched (no drill, no modification). TD-014: not implemented.
- Readiness-review LOW (completion accounting): addressed — ledger + reconcile + command exit-code wiring + per-report accounting section.

## Known limitations (for reviewer)

1. All timing figures are substitute-infrastructure rehearsal values (SQLite, database queue, Windows dev box, PHP 128M). They prove harness executability, nothing about target capacity.
2. Probe completed (not skipped) because this box has a real ffprobe; on hosts without it the workload records `skipped` with reason — both paths are tested by design, only the completed path was exercised live.
3. Queue workload batch default 3 (cap 10); saturation levels [1,3,5] are hardcoded rehearsal-safe constants, configurable only via code for the target run.
4. Corpus WAV is a 440 Hz tone (no speech) — probe-path fixture only; never presented as transcription input.

## State

IN_PROGRESS — Phase A complete; Phase B target run NOT AUTHORIZED. No VERIFIED/DONE claimed.
