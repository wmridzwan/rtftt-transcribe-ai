<?php

use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Models\Translation;
use App\Transcription\LanguageIdentifier;
use App\Translation\TranslationResult;
use App\Translation\TranslationResultWriter;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;

/*
 * Shared translation test fixtures (P5-004B R-3).
 *
 * Loaded once from tests/Pest.php so any translation test file can be run in
 * isolation, not only as part of the whole directory/suite.
 */

if (! function_exists('translationSource')) {
    function translationSource(): Transcription
    {
        $transcription = Transcription::factory()->completed()->create([
            'detected_language' => 'en',
            'full_text' => 'Hello Welcome',
        ]);

        TranscriptionSegment::factory()->create([
            'transcription_id' => $transcription->getKey(),
            'segment_index' => 0,
            'start_seconds' => 0,
            'end_seconds' => 5,
            'text' => 'Hello',
            'language' => 'en',
        ]);

        TranscriptionSegment::factory()->create([
            'transcription_id' => $transcription->getKey(),
            'segment_index' => 1,
            'start_seconds' => 5,
            'end_seconds' => 10,
            'text' => 'Welcome',
            'language' => 'en',
        ]);

        return $transcription;
    }
}

if (! function_exists('alignedTranslationResult')) {
    function alignedTranslationResult(): TranslationResult
    {
        return new TranslationResult(
            targetLanguage: TranslationTarget::Malay,
            fullText: 'Hai Selamat datang',
            segments: [
                new TranslationSegmentData(0, 0.0, 5.0, 'Hai', LanguageIdentifier::English),
                new TranslationSegmentData(1, 5.0, 10.0, 'Selamat datang', LanguageIdentifier::English),
            ],
            provider: 'self-hosted',
            model: 'self-hosted-default',
        );
    }
}

if (! function_exists('writerSource')) {
    function writerSource(): Transcription
    {
        $transcription = Transcription::factory()->completed()->create([
            'detected_language' => 'en',
            'full_text' => 'Original source text',
        ]);

        TranscriptionSegment::factory()->create([
            'transcription_id' => $transcription->getKey(),
            'segment_index' => 0,
            'start_seconds' => 0,
            'end_seconds' => 4.999,
            'text' => 'Hello',
            'language' => 'en',
        ]);

        TranscriptionSegment::factory()->create([
            'transcription_id' => $transcription->getKey(),
            'segment_index' => 1,
            'start_seconds' => 4.999,
            'end_seconds' => 9.5,
            'text' => 'Welcome',
            'language' => 'en',
        ]);

        return $transcription;
    }
}

if (! function_exists('writerTranslation')) {
    function writerTranslation(Transcription $transcription, string $status = 'translating', string $target = 'ms'): Translation
    {
        return Translation::factory()->create([
            'transcription_id' => $transcription->getKey(),
            'target_language' => $target,
            'status' => $status,
        ]);
    }
}

if (! function_exists('writerResult')) {
    function writerResult(
        TranslationTarget $target = TranslationTarget::Malay,
        string $fullText = 'Hai semua',
        LanguageIdentifier $sourceLanguage = LanguageIdentifier::English,
    ): TranslationResult {
        return new TranslationResult(
            targetLanguage: $target,
            fullText: $fullText,
            segments: [
                new TranslationSegmentData(0, 0.0, 4.999, 'Hai semua', $sourceLanguage),
                new TranslationSegmentData(1, 4.999, 9.5, 'Selamat datang', $sourceLanguage),
            ],
            provider: 'self-hosted',
            model: 'translation-test',
        );
    }
}

if (! function_exists('writerPersist')) {
    function writerPersist(Transcription $transcription, TranslationResult $result, Translation $translation): Translation
    {
        return app(TranslationResultWriter::class)->persist(
            $transcription,
            $result,
            $translation->getKey(),
            (string) $translation->attempt_token,
        );
    }
}
