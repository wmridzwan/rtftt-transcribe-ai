<?php

namespace App\Translation;

/**
 * Self-hosted translation provider (PP-T1).
 *
 * Canonical T1 adapter behind {@see TranslationProvider}: the existing
 * authenticated internal NLLB worker path, unchanged. Extending the proven
 * HTTP implementation guarantees observable-behavior parity by
 * construction — no payload, strict validation/alignment, taxonomy,
 * lifecycle, staleness, or revision semantic differs from pre-T1.
 *
 * Translation remains self-hosted-only in Wave 1. Transport details stay
 * inside this adapter boundary. No external, routing, or fallback behavior
 * belongs here (PP-T2+ scope, not authorized).
 */
class SelfHostedTranslationProvider extends HttpTranslationProvider implements TranslationProvider {}
