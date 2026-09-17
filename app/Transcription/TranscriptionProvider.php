<?php

namespace App\Transcription;

/**
 * Provider-neutral transcription interface.
 *
 * Application/domain code depends only on this contract.
 * No faster-whisper, FFmpeg, Python, or HTTP types leak to consumers.
 */
interface TranscriptionProvider
{
    /**
     * Transcribe media using the given invocation context.
     *
     * The invocation carries real transcription/attempt IDs, media
     * reference, and options. Returns a normalized transcript.
     * Throws TranscriptionException on provider-level failure.
     */
    public function transcribe(
        TranscriptionInvocation $invocation,
    ): NormalizedTranscript;
}
