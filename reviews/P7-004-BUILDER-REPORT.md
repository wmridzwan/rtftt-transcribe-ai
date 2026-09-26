# P7-004 Builder Report — Storage Strategy + Streaming Hardening

Task: `tasks/P7-004-storage-streaming-hardening.md` (READY → IN_PROGRESS 2026-09-26, authorized `DECISION-PHASE7-WAVE3A-EXECUTION-AUTHORIZATION-001`).
Builder: OpenCode. No self-verification; submitted for independent review.

## Baseline

- HEAD: `4d90d5b`; branch `main`; shared dirty tree with P7-002 (file-ownership sequencing honored: no DB/env/inventory key touched by P7-004).
- No object-storage surface exists or was added; no S3/MinIO/R2/test doubles for them.

## Files changed (new unless noted)

- `app/Storage/StorageTopology.php` (new) — D7-03 truth: local-driver guard (fails closed on s3/minio/r2/any non-local), root existence/writability, free-space floor vs `deployment.min_free_bytes`. Pure logic with seams.
- `app/Console/Commands/StorageValidateTopology.php` (new) — `storage:validate-topology`, exit 0/1 with retained output.
- `app/Console/Commands/DeploymentVerify.php` (edit, additive) — `checkStorageTopology()` sub-check (advisory outside production); pre-existing `checkStorage` untouched. (One self-inflicted whitespace join on an import line + one collapsed brace were caught by inspection and repaired; `php -l` + suite green after.)
- `app/Console/Commands/ObservabilityDiagnostics.php` (edit, additive) — `Storage disk:`/`Storage root:` lines; no P7-005 format altered.
- `app/Http/Controllers/MediaActionController.php` (edit, narrow) — explicit zero-byte branch: empty object → honest empty 200 (`Content-Length: 0`); any Range on empty → 416 `Content-Range: bytes */0`. Fixes a real defect (generic path claimed length 1 for 0 bytes). No other behavior changed.
- `docs/LOCAL-STORAGE-TOPOLOGY.md` (new) — topology, streaming contract, durability limits, capacity/health, revision/export compatibility, P7-009 handoff.
- `docs/DEPLOYMENT-RUNBOOK.md` (edit, append §15 by reference; P7-008 owns file).
- Retained `verification/p7-004-topology-output.txt`, `p7-004-verify-output.txt`, `p7-004-diagnostics-output.txt`.
- Tests: `tests/Feature/Storage/` 4 files, 18 tests — topology matrix (6), range edges incl. 1 MiB bounded delivery (6), zero-byte disposition incl. ingestion reachability (2), compat: disk-local/private + quarantine-out-of-manifest + verify/topology commands (4).
- No `.env.example` change (no storage key required; `RTFTT_MEDIA_DISK` already registered). No migration. No inventory change.

No P7-002 datastore content. No P7-009/P7-011/drill/P7-012 content. No object storage anywhere.

## AC results

| AC | Verdict | Evidence |
|---|---|---|
| AC1 topology/permissions validation | PASS | `storage:validate-topology` exit 0 live (local, writable, floor met); unit matrix green |
| AC2 range matrix | PASS | Pre-existing matrix untouched-green + 6 new edge tests (clamp, oversize suffix, single-byte, `-0`→416, end<start→200, 1 MiB bounded delivery) |
| AC3 TD-011 direct tests | PASS | Bounded-stream, range edges, zero-byte disposition (reachable-via-ingestion proven, handled explicitly) |
| AC4 P7-006/P7-007 compatibility | PASS | Disk-local + private assertion; real isolated `backup:run` proves quarantine excluded from manifest; scan path unmodified (P7-006 suites green in full runs) |
| AC5 revision/export regression | PASS | Full-suite Phase 6 suites green (runs 2–3) |
| AC6 capacity floor + degraded | PASS | Floor unit tests + live topology output; degraded behavior documented |
| AC7 no object storage | PASS | Driver guard test (s3/minio/r2 rejected); tree grep clean |
| AC8 standard gate | PASS | Full suite 1050/1049+1 skip; Pint clean; PHPStan 0; no Wave 2 semantic alteration |
| AC9 no G-05 claim | PASS | No gate verdict in diff/docs |

## Test / verification matrix

- New P7-004 suites: 18/18. Pre-existing `MediaStreamingTest` (13) unbroken.
- Full suite shared with P7-002 batch (runs 1–3 above); no P7-004-attributable failure in any run.
- No new Playwright run (no UI changed); media playback regression = streaming/range suites green.
- Pint clean; PHPStan 0.

## TD mapping

- TD-011 (LOW): verification delivered (direct tests replace code-review-only coverage); closes at P7-012 G-05 consumption, not here.
- TD-007/Option D: untouched. TD-002 capacity share: floor evidenced; G-01 proof stays with P7-009.
- TD-008: no flakes observed; stays OPEN/MEDIUM/pre-P7-012.
- Nothing marked closed.

## Known limitations (for reviewer)

1. Free-space floor uses the dev volume live; production-mount numbers belong to the target run (P7-009 consumes).
2. The zero-byte ingestion acceptance (empty file ingests as a 0-byte row) is preserved P2 behavior, not changed — only the serving edge was made honest.

## State

IN_PROGRESS → REVIEW on handoff. No VERIFIED/DONE claimed.
