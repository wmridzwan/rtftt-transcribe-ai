<?php

namespace App\Transcription;

/**
 * Self-hosted transcription provider (PP-T1).
 *
 * Canonical T1 adapter behind {@see TranscriptionProvider}: the existing
 * authenticated internal faster-whisper worker path, unchanged. Extending
 * the proven HTTP implementation guarantees observable-behavior parity by
 * construction — no request, validation, normalization, taxonomy, or
 * lifecycle semantic differs from the pre-T1 path.
 *
 * Transport details (HTTP, envelope, bearer token) stay inside this
 * adapter boundary. No routing, fallback, chunking, or external-provider
 * behavior belongs here (PP-T2+ scope, not authorized).
 */
class SelfHostedTranscriptionProvider extends HttpTranscriptionProvider implements TranscriptionProvider {}
