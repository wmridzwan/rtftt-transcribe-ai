<?php

namespace App\Translation;

/**
 * Named failure for translation provider-selection errors (PP-T2).
 *
 * Thrown when the configured selection value is anything other than the
 * Wave-1 `self_hosted` value. Fail-closed: callers must not fall back to
 * another provider.
 */
class ProviderResolutionException extends \RuntimeException {}
