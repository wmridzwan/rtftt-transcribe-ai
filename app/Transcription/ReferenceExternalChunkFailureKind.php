<?php

namespace App\Transcription;

/**
 * Failure kinds for the fixture-shaped reference external transport.
 *
 * Vendor-neutral shapes only. The domain mapping into the frozen
 * TranscriptionFailure taxonomy lives in
 * ReferenceExternalTranscriptionProvider; the transport-provided
 * retryability hint is advisory metadata and never controls mapping
 * (ADR-018 precedent).
 */
enum ReferenceExternalChunkFailureKind: string
{
    case Timeout = 'timeout';
    case RateLimited = 'rate_limited';
    case ServerError = 'server_error';
    case Unreachable = 'unreachable';
    case Partial = 'partial';
    case Invalid = 'invalid';
    case Unknown = 'unknown';
}
