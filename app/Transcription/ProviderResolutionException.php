<?php

namespace App\Transcription;

/**
 * Named failure for transcription provider-selection errors (PP-T2).
 *
 * Thrown when the configured selection value is unknown/unsupported or a
 * named external binding does not exist or cannot be used. Fail-closed:
 * callers must not fall back to another provider.
 */
class ProviderResolutionException extends \RuntimeException {}
