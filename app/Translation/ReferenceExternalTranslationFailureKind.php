<?php

namespace App\Translation;

/**
 * Failure kinds for the fixture-shaped reference external transport.
 *
 * Vendor-neutral shapes only. The domain mapping into the frozen
 * TranslationFailure taxonomy lives in
 * ReferenceExternalTranslationProvider; the transport-provided
 * retryability hint is advisory metadata and never controls mapping
 * (ADR-022 precedent: worker flags are transport-advisory only).
 */
enum ReferenceExternalTranslationFailureKind: string
{
    case Timeout = 'timeout';
    case RateLimited = 'rate_limited';
    case ServerError = 'server_error';
    case Unreachable = 'unreachable';
    case Partial = 'partial';
    case Invalid = 'invalid';
    case Unknown = 'unknown';
}
