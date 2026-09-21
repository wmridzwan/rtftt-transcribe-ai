<?php

namespace Tests\Support;

use App\Translation\TranslationException;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationProvider;
use App\Translation\TranslationResult;
use Closure;
use Throwable;

/**
 * Records translation provider invocations for deterministic tests.
 */
class RecordingTranslationProvider implements TranslationProvider
{
    public int $calls = 0;

    public ?TranslationInvocation $lastInvocation = null;

    public function __construct(
        private readonly ?TranslationResult $result = null,
        private readonly ?TranslationException $exception = null,
        private readonly ?Closure $whileInFlight = null,
        private readonly ?Throwable $inFlightFailure = null,
    ) {}

    public function translate(TranslationInvocation $invocation): TranslationResult
    {
        $this->calls++;
        $this->lastInvocation = $invocation;

        // Runs while the job is "in flight" (after its claim, before it reports),
        // so tests can interleave stale recovery / retry with a running attempt.
        if ($this->whileInFlight !== null) {
            ($this->whileInFlight)($invocation);
        }

        if ($this->inFlightFailure !== null) {
            throw $this->inFlightFailure;
        }

        if ($this->exception !== null) {
            throw $this->exception;
        }

        if ($this->result === null) {
            throw new \RuntimeException('No translation result configured for the recording provider.');
        }

        return $this->result;
    }
}
