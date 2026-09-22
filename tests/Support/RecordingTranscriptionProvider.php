<?php

namespace Tests\Support;

use App\Transcription\NormalizedTranscript;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionInvocation;
use App\Transcription\TranscriptionProvider;

/**
 * Deterministic test double for the provider-neutral transcription contract.
 */
final class RecordingTranscriptionProvider implements TranscriptionProvider
{
    /** @var list<TranscriptionInvocation> */
    public array $invocations = [];

    public function __construct(
        private readonly ?NormalizedTranscript $result = null,
        private readonly ?TranscriptionException $exception = null,
    ) {}

    public function transcribe(TranscriptionInvocation $invocation): NormalizedTranscript
    {
        $this->invocations[] = $invocation;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->result ?? NormalizedTranscript::noSpeech(0.0);
    }

    public function callCount(): int
    {
        return count($this->invocations);
    }
}
