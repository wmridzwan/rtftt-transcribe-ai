<?php

namespace App\Capacity;

/**
 * Capacity-envelope definition for D7-08/A (P7-009 §6.1).
 *
 * Measure-and-report semantics: each line item carries a target
 * description and a measurement method, but NO pass/fail threshold.
 * Over-limit or degraded outcomes are findings, not task failures;
 * the P7-012 gate decides. The harness must therefore never invent a
 * verdict — it records numbers alongside outcome correctness.
 *
 * Workload categories are exactly the contract-approved set; no new
 * product workload category may be added here without an HPO decision.
 */
final class CapacityEnvelope
{
    public const CLASSIFICATION = 'REHEARSAL / SUBSTITUTE EVIDENCE — NOT TARGET CAPACITY EVIDENCE';

    /**
     * @return list<array{key: string, title: string, target: string, method: string, phase_a_scope: string}>
     */
    public static function items(): array
    {
        return [
            [
                'key' => 'upload',
                'title' => 'Upload (500 MiB product boundary, G-01)',
                'target' => 'Real 500 MiB multipart upload through the deployed HTTP stack (Phase B only).',
                'method' => 'Phase B: full limit/timeout/capacity chain evidenced. Phase A rehearses the durable-write path (storage write + checksum round-trip) at safe rehearsal scale only.',
                'phase_a_scope' => 'Synthetic-blob storage write + SHA-256 round-trip verification. Explicitly NOT the G-01 proof.',
            ],
            [
                'key' => 'probe',
                'title' => 'Media probing (FFprobe on large media)',
                'target' => 'FFprobe metadata extraction latency on target hardware.',
                'method' => 'Time MediaMetadataProbeService::probe boundaries on a synthetic fixture; record outcome including ffprobe-unavailable skips honestly.',
                'phase_a_scope' => 'Probe-path timing on a synthetic WAV; unavailable-ffprobe outcomes recorded as skipped, never as zero-time passes.',
            ],
            [
                'key' => 'transcription',
                'title' => 'Transcription throughput / RTF (canonical large-v3)',
                'target' => 'Real-time factor measured with faster-whisper large-v3 on target hardware (Phase B only).',
                'method' => 'Phase B: end-to-end inference timing vs media duration. Phase A rehearses the transcript persistence path (segment bulk write + read-back) with a declared synthetic duration; the RTF figure is synthetic-path only.',
                'phase_a_scope' => 'Segment persistence-path timing + synthetic RTF vs declared synthetic duration. NOT large-v3 inference evidence.',
            ],
            [
                'key' => 'translation',
                'title' => 'Translation latency / throughput (canonical NLLB runtime)',
                'target' => 'NLLB translation latency/throughput on target hardware (Phase B only).',
                'method' => 'Phase B: end-to-end provider timing. Phase A rehearses the segment-alignment transform path (index/timestamp preservation) in-app.',
                'phase_a_scope' => 'In-app alignment-transform timing + correctness. NOT NLLB provider evidence.',
            ],
            [
                'key' => 'queue',
                'title' => 'Queue / concurrency behavior',
                'target' => 'Concurrent-job behavior under the supervised-queue posture (P7-003) on the target broker.',
                'method' => 'Dispatch CapacityProbeJob batches on the configured driver; verify completion markers; record driver posture and queue depth. True broker concurrency is target-only.',
                'phase_a_scope' => 'Small deterministic batches on the available driver with dispatched/completed/failed accounting. Reconciled counts required.',
            ],
            [
                'key' => 'saturation',
                'title' => 'Saturation / degradation observation',
                'target' => 'Worker/job/storage/database limits observed, not exceeded destructively (Phase B on target).',
                'method' => 'Ramp batch sizes and record queue buildup, latency/throughput change, rejections, errors, timeouts, host pressure.',
                'phase_a_scope' => 'Safe-limit ramp only (tiny batches). The dev-machine saturation point is NOT application target capacity.',
            ],
            [
                'key' => 'streaming',
                'title' => 'Streaming under load',
                'target' => 'Bounded range delivery behavior under load per the P7-004 streaming contract.',
                'method' => 'Time full-object reads and bounded range-slice reads; verify slice bytes; correctness-checked alongside timing.',
                'phase_a_scope' => 'Single-object read + 64 KiB range-slice timing with byte correctness. Load dimension deferred to target.',
            ],
            [
                'key' => 'render',
                'title' => 'Long-transcript browser render',
                'target' => 'Browser render timing for long transcripts (Playwright, target hardware, Phase B).',
                'method' => 'Phase B: Playwright timing spec. Phase A rehearses the server-side composition path (TranscriptCopy over synthetic segments); the browser-timing method is retained, not executed.',
                'phase_a_scope' => 'Server-side composition timing only. No browser-timing claim.',
            ],
            [
                'key' => 'export',
                'title' => 'Export generation timings',
                'target' => 'TXT/SRT export generation timings for long transcripts.',
                'method' => 'Time export-text assembly (mirroring TranscriptionExportController row ordering) plus SRT timestamp formatting; verify first/last rows and numbering.',
                'phase_a_scope' => 'In-app assembly timing with content correctness. HTTP-download timing deferred to target.',
            ],
        ];
    }

    /**
     * Render the envelope as a Markdown document for the evidence bundle.
     */
    public static function toMarkdown(string $runId): string
    {
        $lines = [
            '# P7-009 Capacity Envelope (D7-08/A)',
            '',
            '> '.self::CLASSIFICATION,
            '>',
            '> Run: `'.$runId.'`. Measure-and-report semantics: this document',
            '> defines targets and methods. It states NO pass/fail thresholds',
            '> and NO gate verdict; P7-012 decides.',
            '',
        ];

        foreach (self::items() as $item) {
            $lines[] = '## '.$item['key'].' — '.$item['title'];
            $lines[] = '';
            $lines[] = '- Target: '.$item['target'];
            $lines[] = '- Method: '.$item['method'];
            $lines[] = '- Phase A scope: '.$item['phase_a_scope'];
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
