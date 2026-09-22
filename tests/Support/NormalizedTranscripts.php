<?php

namespace Tests\Support;

use App\Transcription\LanguageIdentifier;
use App\Transcription\NormalizedTranscript;
use App\Transcription\TranscriptSegmentData;

/**
 * Realistic provider-neutral normalized results for Batch 2 integration tests.
 */
final class NormalizedTranscripts
{
    public static function multilingual(): NormalizedTranscript
    {
        return new NormalizedTranscript(
            text: 'Selamat datang. Welcome. 欢迎. வணக்கம்.',
            detectedLanguage: LanguageIdentifier::Malay,
            durationSeconds: 12.0,
            speechDetected: true,
            segments: [
                new TranscriptSegmentData(0, 0.0, 3.0, 'Selamat datang.', LanguageIdentifier::Malay),
                new TranscriptSegmentData(1, 3.0, 6.0, 'Welcome.', LanguageIdentifier::English),
                new TranscriptSegmentData(2, 6.0, 9.0, '欢迎.', LanguageIdentifier::Chinese),
                new TranscriptSegmentData(3, 9.0, 12.0, 'வணக்கம்.', LanguageIdentifier::Tamil),
            ],
        );
    }
}
