<?php

namespace App\Translation;

/**
 * Provider-neutral translation contract (ADR-022, D5-04).
 *
 * Implementations may be self-hosted (default) or later a hosted adapter; the
 * application boundary must not depend on either. Implementations must return
 * a segment-aligned result and must not mutate the source transcript.
 */
interface TranslationProvider
{
    /**
     * @throws TranslationException
     */
    public function translate(TranslationInvocation $invocation): TranslationResult;
}
