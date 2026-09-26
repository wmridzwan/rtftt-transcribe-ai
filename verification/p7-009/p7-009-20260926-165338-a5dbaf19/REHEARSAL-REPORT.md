# P7-009 Phase A Rehearsal Report

> REHEARSAL / SUBSTITUTE EVIDENCE — NOT TARGET CAPACITY EVIDENCE
>
> Run: `p7-009-20260926-165338-a5dbaf19`. This report is harness-executability
> evidence only. It states NO capacity verdict, NO production
> capacity, and NO release fitness. The final
> production-shaped capacity run (Phase B) is NOT AUTHORIZED.

## Completion accounting (readiness LOW finding addressed)

Reconciled: YES. Repeats total: 27 (completed 27, failed 0, skipped 0).

- `upload`: dispatched 3, completed 3, failed 0, skipped 0 — reconciled
- `probe`: dispatched 3, completed 3, failed 0, skipped 0 — reconciled
- `transcription`: dispatched 3, completed 3, failed 0, skipped 0 — reconciled
- `translation`: dispatched 3, completed 3, failed 0, skipped 0 — reconciled
- `queue`: dispatched 3, completed 3, failed 0, skipped 0 — reconciled
- `saturation`: dispatched 3, completed 3, failed 0, skipped 0 — reconciled
- `streaming`: dispatched 3, completed 3, failed 0, skipped 0 — reconciled
- `render`: dispatched 3, completed 3, failed 0, skipped 0 — reconciled
- `export`: dispatched 3, completed 3, failed 0, skipped 0 — reconciled

## Per-workload timing summaries (rehearsal scale)

### `upload` (n=3)

- min/max: 9.52 ms / 19.18 ms
- mean/median: 13.09 ms / 10.59 ms
- stddev: 4.32 ms
- raw values (ms): 9.52, 10.59, 19.18

### `probe` (n=3)

- min/max: 225.85 ms / 252.39 ms
- mean/median: 241.10 ms / 245.05 ms
- stddev: 11.19 ms
- raw values (ms): 225.85, 245.05, 252.39

### `transcription` (n=3)

- min/max: 10.11 ms / 297.65 ms
- mean/median: 106.28 ms / 11.09 ms
- stddev: 135.32 ms
- raw values (ms): 10.11, 11.09, 297.65

### `translation` (n=3)

- min/max: 0.18 ms / 0.21 ms
- mean/median: 0.20 ms / 0.21 ms
- stddev: 0.02 ms
- raw values (ms): 0.18, 0.21, 0.21

### `queue` (n=3)

- min/max: 163.86 ms / 726.50 ms
- mean/median: 356.23 ms / 178.33 ms
- stddev: 261.89 ms
- raw values (ms): 163.86, 178.33, 726.50

### `saturation` (n=3)

- min/max: 204.37 ms / 241.41 ms
- mean/median: 227.46 ms / 236.59 ms
- stddev: 16.44 ms
- raw values (ms): 204.37, 236.59, 241.41

### `streaming` (n=3)

- min/max: 13.08 ms / 16.61 ms
- mean/median: 14.78 ms / 14.66 ms
- stddev: 1.44 ms
- raw values (ms): 13.08, 14.66, 16.61

### `render` (n=3)

- min/max: 4.50 ms / 8.36 ms
- mean/median: 5.81 ms / 4.56 ms
- stddev: 1.81 ms
- raw values (ms): 4.50, 4.56, 8.36

### `export` (n=3)

- min/max: 0.59 ms / 1.13 ms
- mean/median: 0.77 ms / 0.59 ms
- stddev: 0.25 ms
- raw values (ms): 0.59, 0.59, 1.13

## Failure records

No failed repeats in this run.

## Rehearsal environment (substitute, not target)

- classification: `REHEARSAL / SUBSTITUTE`
- os: Windows / Windows NT DESKTOP-V7TOPE7 10.0 build 26200 (Windows 11) AMD64
- commit: 4d90d5b914c0928574cff7e0f1d1d4a463bb4350 (refs/heads/main)
- php: 8.4.24
- database: sqlite
- queue: {"default_connection":"database","database_queue_table":"jobs","queue_depth_jobs":0}
- storage: {"media_disk":"local","driver":"local","root":"C:\\Users\\Admin\\Herd\\rtftt-transcribe-ai\\storage\\app\/private","free_bytes":7652737024}
- models: {"transcription":"unknown (unknown)","translation":"self-hosted \/ self-hosted-default","note":"Phase A rehearsal does not invoke real inference; model identifiers are recorded for target-run comparability only."}

Full metadata: `environment.json` in this run directory.

## Interpretation boundary

These numbers describe rehearsal-harness executability on
substitute infrastructure. They MUST NOT be read as
production capacity, target-hardware RTF/latency, G-01 proof,
or release fitness. Phase B remains NOT AUTHORIZED.
