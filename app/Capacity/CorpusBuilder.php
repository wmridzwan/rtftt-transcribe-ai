<?php

namespace App\Capacity;

/**
 * Deterministic synthetic corpus builder for the P7-009 harness.
 *
 * All inputs are generated from an explicit integer seed via a seeded
 * PRNG, so every rehearsal corpus is reproducible and contains no
 * customer or production media. Each corpus item records its
 * characteristics (format, size, duration, language, workload class,
 * concurrency/configuration) plus a SHA-256 digest for integrity
 * verification.
 *
 * Rehearsal scale is deliberately small (KiB-range blobs, tens of
 * segments) so the harness stays safe on substitute infrastructure
 * with 512 MiB environment limits. Corpus scale is evidence metadata,
 * never a capacity claim.
 */
final class CorpusBuilder
{
    /**
     * @return array{seed: int, items: list<array<string, mixed>>, sha256: string}
     */
    public static function build(int $seed, int $blobBytes = 262_144, int $segmentCount = 60): array
    {
        mt_srand($seed);

        $audioBytes = self::syntheticWav($seed);
        $blobBytes = max(1024, $blobBytes);
        $blob = self::seededBytes($blobBytes);
        $segmentCount = max(1, min(500, $segmentCount));
        $segments = self::syntheticSegments($segmentCount);

        $items = [
            [
                'name' => 'synthetic-audio-wav',
                'format' => 'wav (synthetic PCM, 8 kHz mono 16-bit)',
                'size_bytes' => strlen($audioBytes),
                'duration_seconds' => 5,
                'language_characteristics' => 'n/a (synthetic tone, no speech)',
                'workload_class' => 'probe',
                'concurrency_configuration' => 'single',
                'payload' => base64_encode($audioBytes),
                'sha256' => hash('sha256', $audioBytes),
            ],
            [
                'name' => 'synthetic-upload-blob',
                'format' => 'application/octet-stream (seeded PRNG bytes)',
                'size_bytes' => strlen($blob),
                'duration_seconds' => null,
                'language_characteristics' => 'n/a (binary)',
                'workload_class' => 'upload/streaming',
                'concurrency_configuration' => 'single',
                'payload' => base64_encode($blob),
                'sha256' => hash('sha256', $blob),
            ],
            [
                'name' => 'synthetic-transcript',
                'format' => 'in-memory segment rows (synthetic ms/en/zh/ta mix)',
                'size_bytes' => strlen(json_encode($segments) ?: ''),
                'duration_seconds' => 300,
                'language_characteristics' => 'synthetic code-switch mix (ms/en/zh/ta), deterministic fixture text',
                'workload_class' => 'transcription/translation/render/export',
                'concurrency_configuration' => 'single',
                'payload' => base64_encode(json_encode($segments) ?: ''),
                'sha256' => hash('sha256', json_encode($segments) ?: ''),
            ],
        ];

        $manifest = [
            'seed' => $seed,
            'items' => $items,
            'sha256' => hash('sha256', json_encode($items) ?: ''),
        ];

        mt_srand();

        return $manifest;
    }

    /**
     * Deterministic PRNG bytes (mt_rand is seeded by the caller).
     */
    private static function seededBytes(int $length): string
    {
        $out = '';

        while (strlen($out) < $length) {
            $out .= pack('N', mt_rand(0, 0x7FFFFFFF));
        }

        return substr($out, 0, $length);
    }

    /**
     * A tiny valid WAV: 1s…5s 8 kHz mono 16-bit 440 Hz tone. Real
     * container bytes (so the probe path exercises a genuine file),
     * synthetic signal (so there is no speech content to misread).
     */
    private static function syntheticWav(int $seed): string
    {
        $sampleRate = 8000;
        $seconds = 5;
        $frames = $sampleRate * $seconds;
        $data = '';

        for ($i = 0; $i < $frames; $i++) {
            $sample = (int) (10_000 * sin(2 * M_PI * 440 * $i / $sampleRate));
            // Deterministic dither from the seed keeps the file non-trivial.
            $sample += ($seed + $i) % 7 - 3;
            $data .= pack('v', $sample & 0xFFFF);
        }

        $dataSize = strlen($data);

        return 'RIFF'.pack('V', 36 + $dataSize).'WAVE'
            .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1)
            .pack('V', $sampleRate).pack('V', $sampleRate * 2)
            .pack('v', 2).pack('v', 16)
            .'data'.pack('V', $dataSize).$data;
    }

    /**
     * @return list<array{index: int, start: float, end: float, text: string, language: string}>
     */
    private static function syntheticSegments(int $count): array
    {
        $languages = ['ms', 'en', 'zh', 'ta'];
        $words = ['pagi', 'morning', 'selamat', 'transkrip', 'mesyuarat', 'report', 'nota', 'bengkel', 'audio', 'rakaman', 'sistem', 'data'];
        $segments = [];
        $cursor = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $length = 2.0 + (mt_rand(0, 60) / 10);
            $text = 'seg-'.$i.' '.implode(' ', [
                $words[mt_rand(0, count($words) - 1)],
                $words[mt_rand(0, count($words) - 1)],
                $words[mt_rand(0, count($words) - 1)],
            ]);
            $segments[] = [
                'index' => $i,
                'start' => round($cursor, 3),
                'end' => round($cursor + $length, 3),
                'text' => $text,
                'language' => $languages[$i % count($languages)],
            ];
            $cursor += $length + 0.25;
        }

        return $segments;
    }
}
