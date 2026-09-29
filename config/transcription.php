<?php

return [
    'worker_url' => env('RTFTT_TRANSCRIPTION_WORKER_URL', 'http://localhost:8000'),

    'worker_token' => env('RTFTT_TRANSCRIPTION_WORKER_TOKEN', ''),

    'contract_version' => '1.0',

    /*
    |--------------------------------------------------------------------------
    | Provider selection (PP-T2)
    |--------------------------------------------------------------------------
    |
    | Decided: DECISION-PP-T2-CONFIG-NAMING-001. Deterministic selection key
    | read by TranscriptionProviderResolver on every interface resolution.
    | 'self_hosted' (default) preserves pre-PP-T2 behavior. 'external_<name>'
    | resolves via the transcription.providers.<name> container binding
    | (fixtures/test doubles in PP-T2; real adapters only via later tasks).
    |
    */

    'provider_selection' => env('RTFTT_TRANSCRIPTION_PROVIDER_SELECTION', 'self_hosted'),

    /*
    |--------------------------------------------------------------------------
    | Canonical model
    |--------------------------------------------------------------------------
    |
    | Server/config controlled. Users may not select model paths or
    | filesystem locations. Canonical Phase 3 default: large-v3 (turbo is a
    | non-default/experimental profile).
    |
    */

    'model' => env('RTFTT_WHISPER_MODEL', 'large-v3'),

    /*
    |--------------------------------------------------------------------------
    | Queue orchestration
    |--------------------------------------------------------------------------
    |
    | Small identifiers only are placed on the queue. The canonical Phase 3
    | backend is Redis (without Horizon). The connection defaults to the
    | application queue connection so tests can use sync/fake safely; set
    | QUEUE_CONNECTION=redis (or RTFTT_TRANSCRIPTION_QUEUE_CONNECTION=redis)
    | to exercise the real Redis path.
    |
    */

    'queue' => env('RTFTT_TRANSCRIPTION_QUEUE', 'transcription'),

    'queue_connection' => env('RTFTT_TRANSCRIPTION_QUEUE_CONNECTION'),

    'timeout_seconds' => 300,

    /*
    |--------------------------------------------------------------------------
    | Job timeout and queue retry_after reconciliation
    |--------------------------------------------------------------------------
    |
    | provider timeout  : maximum provider inference time (timeout_seconds).
    | job timeout       : per-job worker timeout; provider timeout + safety.
    | required retry_after : minimum queue connection retry_after so a running
    |                       job is never re-delivered while still executing.
    | Invariant: timeout_seconds < job_timeout_seconds < retry_after_seconds.
    | (P7-003; mirrors config/translation.php.)
    |
    */

    'job_timeout_seconds' => (int) env('RTFTT_TRANSCRIPTION_JOB_TIMEOUT_SECONDS', 330),

    'retry_after_seconds' => (int) env('RTFTT_TRANSCRIPTION_RETRY_AFTER_SECONDS', 420),

    /*
    |--------------------------------------------------------------------------
    | Stale attempt recovery
    |--------------------------------------------------------------------------
    |
    | A `running` attempt older than this threshold is considered abandoned and
    | recoverable (P3-007 / ADR-018). When unset, it is derived from the
    | canonical provider execution timeout plus an explicit 60-second safety
    | margin so a legitimately long inference is never mistaken for stale.
    |
    */

    'attempt_stale_seconds' => env('RTFTT_TRANSCRIPTION_ATTEMPT_STALE_SECONDS'),

    'prepared_audio_retention' => env('RTFTT_PREPARED_AUDIO_RETENTION', 'ephemeral'),

    'shared_media_root' => env('RTFTT_SHARED_MEDIA_ROOT', storage_path('app/media')),
];
