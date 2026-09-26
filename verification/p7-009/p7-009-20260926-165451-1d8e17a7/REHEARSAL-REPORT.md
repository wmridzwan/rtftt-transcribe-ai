# P7-009 Phase A Rehearsal Report

> REHEARSAL / SUBSTITUTE EVIDENCE — NOT TARGET CAPACITY EVIDENCE
>
> Run: `p7-009-20260926-165451-1d8e17a7`. This report is harness-executability
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

- min/max: 9.48 ms / 19.05 ms
- mean/median: 12.73 ms / 9.65 ms
- stddev: 4.47 ms
- raw values (ms): 9.48, 9.65, 19.05

### `probe` (n=3)

- min/max: 226.35 ms / 243.88 ms
- mean/median: 237.54 ms / 242.39 ms
- stddev: 7.94 ms
- raw values (ms): 226.35, 242.39, 243.88

### `transcription` (n=3)

- min/max: 6.75 ms / 315.68 ms
- mean/median: 110.37 ms / 8.69 ms
- stddev: 145.18 ms
- raw values (ms): 6.75, 8.69, 315.68

### `translation` (n=3)

- min/max: 0.20 ms / 0.23 ms
- mean/median: 0.22 ms / 0.22 ms
- stddev: 0.01 ms
- raw values (ms): 0.20, 0.22, 0.23

### `queue` (n=3)

- min/max: 162.43 ms / 179.20 ms
- mean/median: 168.40 ms / 163.57 ms
- stddev: 7.65 ms
- raw values (ms): 162.43, 163.57, 179.20

### `saturation` (n=3)

- min/max: 190.19 ms / 266.82 ms
- mean/median: 218.44 ms / 198.32 ms
- stddev: 34.37 ms
- raw values (ms): 190.19, 198.32, 266.82

### `streaming` (n=3)

- min/max: 12.78 ms / 14.25 ms
- mean/median: 13.73 ms / 14.15 ms
- stddev: 0.67 ms
- raw values (ms): 12.78, 14.15, 14.25

### `render` (n=3)

- min/max: 3.95 ms / 6.80 ms
- mean/median: 4.98 ms / 4.19 ms
- stddev: 1.29 ms
- raw values (ms): 3.95, 4.19, 6.80

### `export` (n=3)

- min/max: 0.47 ms / 1.00 ms
- mean/median: 0.64 ms / 0.47 ms
- stddev: 0.25 ms
- raw values (ms): 0.47, 0.47, 1.00

## Failure records

No failed repeats in this run.

## Rehearsal environment (substitute, not target)

- classification: `REHEARSAL / SUBSTITUTE`
- os: Windows / Windows NT DESKTOP-V7TOPE7 10.0 build 26200 (Windows 11) AMD64
- commit: 4d90d5b914c0928574cff7e0f1d1d4a463bb4350 (refs/heads/main)
- php: 8.4.24
- database: sqlite
- queue: {"default_connection":"database","database_queue_table":"jobs","queue_depth_jobs":0}
- storage: {"media_disk":"local","driver":"local","root":"C:\\Users\\Admin\\Herd\\rtftt-transcribe-ai\\storage\\app\/private","free_bytes":7635865600}
- models: {"transcription":"large-v3 (http:\/\/127.0.0.1:8000)","translation":"self-hosted \/ self-hosted-default","note":"Phase A rehearsal does not invoke real inference; model identifiers are recorded for target-run comparability only."}

Full metadata: `environment.json` in this run directory.

## Interpretation boundary

These numbers describe rehearsal-harness executability on
substitute infrastructure. They MUST NOT be read as
production capacity, target-hardware RTF/latency, G-01 proof,
or release fitness. Phase B remains NOT AUTHORIZED.
