# P7-009 Capacity Envelope (D7-08/A)

> REHEARSAL / SUBSTITUTE EVIDENCE — NOT TARGET CAPACITY EVIDENCE
>
> Run: `CANONICAL`. Measure-and-report semantics: this document
> defines targets and methods. It states NO pass/fail thresholds
> and NO gate verdict; P7-012 decides.

## upload — Upload (500 MiB product boundary, G-01)

- Target: Real 500 MiB multipart upload through the deployed HTTP stack (Phase B only).
- Method: Phase B: full limit/timeout/capacity chain evidenced. Phase A rehearses the durable-write path (storage write + checksum round-trip) at safe rehearsal scale only.
- Phase A scope: Synthetic-blob storage write + SHA-256 round-trip verification. Explicitly NOT the G-01 proof.

## probe — Media probing (FFprobe on large media)

- Target: FFprobe metadata extraction latency on target hardware.
- Method: Time MediaMetadataProbeService::probe boundaries on a synthetic fixture; record outcome including ffprobe-unavailable skips honestly.
- Phase A scope: Probe-path timing on a synthetic WAV; unavailable-ffprobe outcomes recorded as skipped, never as zero-time passes.

## transcription — Transcription throughput / RTF (canonical large-v3)

- Target: Real-time factor measured with faster-whisper large-v3 on target hardware (Phase B only).
- Method: Phase B: end-to-end inference timing vs media duration. Phase A rehearses the transcript persistence path (segment bulk write + read-back) with a declared synthetic duration; the RTF figure is synthetic-path only.
- Phase A scope: Segment persistence-path timing + synthetic RTF vs declared synthetic duration. NOT large-v3 inference evidence.

## translation — Translation latency / throughput (canonical NLLB runtime)

- Target: NLLB translation latency/throughput on target hardware (Phase B only).
- Method: Phase B: end-to-end provider timing. Phase A rehearses the segment-alignment transform path (index/timestamp preservation) in-app.
- Phase A scope: In-app alignment-transform timing + correctness. NOT NLLB provider evidence.

## queue — Queue / concurrency behavior

- Target: Concurrent-job behavior under the supervised-queue posture (P7-003) on the target broker.
- Method: Dispatch CapacityProbeJob batches on the configured driver; verify completion markers; record driver posture and queue depth. True broker concurrency is target-only.
- Phase A scope: Small deterministic batches on the available driver with dispatched/completed/failed accounting. Reconciled counts required.

## saturation — Saturation / degradation observation

- Target: Worker/job/storage/database limits observed, not exceeded destructively (Phase B on target).
- Method: Ramp batch sizes and record queue buildup, latency/throughput change, rejections, errors, timeouts, host pressure.
- Phase A scope: Safe-limit ramp only (tiny batches). The dev-machine saturation point is NOT application target capacity.

## streaming — Streaming under load

- Target: Bounded range delivery behavior under load per the P7-004 streaming contract.
- Method: Time full-object reads and bounded range-slice reads; verify slice bytes; correctness-checked alongside timing.
- Phase A scope: Single-object read + 64 KiB range-slice timing with byte correctness. Load dimension deferred to target.

## render — Long-transcript browser render

- Target: Browser render timing for long transcripts (Playwright, target hardware, Phase B).
- Method: Phase B: Playwright timing spec. Phase A rehearses the server-side composition path (TranscriptCopy over synthetic segments); the browser-timing method is retained, not executed.
- Phase A scope: Server-side composition timing only. No browser-timing claim.

## export — Export generation timings

- Target: TXT/SRT export generation timings for long transcripts.
- Method: Time export-text assembly (mirroring TranscriptionExportController row ordering) plus SRT timestamp formatting; verify first/last rows and numbering.
- Phase A scope: In-app assembly timing with content correctness. HTTP-download timing deferred to target.

