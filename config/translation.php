<?php

return [
    'worker_url' => env(
        'RTFTT_TRANSLATION_WORKER_URL',
        env('RTFTT_TRANSCRIPTION_WORKER_URL', 'http://localhost:8000'),
    ),

    'worker_token' => env(
        'RTFTT_TRANSLATION_WORKER_TOKEN',
        env('RTFTT_TRANSCRIPTION_WORKER_TOKEN', ''),
    ),

    /*
    |--------------------------------------------------------------------------
    | Provider selection
    |--------------------------------------------------------------------------
    |
    | D5-04: provider-neutral application boundary with a self-hosted default.
    | The self-hosted name is recorded as provider identity on persisted
    | translations. A hosted adapter would require a separate ADR.
    |
    */

    'provider' => env('RTFTT_TRANSLATION_PROVIDER', 'self-hosted'),

    'model' => env('RTFTT_TRANSLATION_MODEL', 'self-hosted-default'),

    /*
    |--------------------------------------------------------------------------
    | Queue orchestration
    |--------------------------------------------------------------------------
    |
    | Small identifiers only are placed on the queue. Mirrors the Phase 3
    | convention: a dedicated queue name with an optional explicit connection.
    |
    */

    'queue' => env('RTFTT_TRANSLATION_QUEUE', 'translation'),

    'queue_connection' => env('RTFTT_TRANSLATION_QUEUE_CONNECTION', env('RTFTT_TRANSCRIPTION_QUEUE_CONNECTION')),

    'timeout_seconds' => (int) env('RTFTT_TRANSLATION_TIMEOUT_SECONDS', 300),

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
    |
    */

    'job_timeout_seconds' => (int) env('RTFTT_TRANSLATION_JOB_TIMEOUT_SECONDS', 330),

    'retry_after_seconds' => (int) env('RTFTT_TRANSLATION_RETRY_AFTER_SECONDS', 420),

    /*
    |--------------------------------------------------------------------------
    | Stale attempt recovery
    |--------------------------------------------------------------------------
    |
    | A `translating` translation older than this threshold is considered
    | abandoned and recoverable (P5-005). When unset it is derived from the
    | canonical provider execution timeout plus an explicit 60-second safety
    | margin so a legitimately long translation is never mistaken for stale.
    |
    */

    'attempt_stale_seconds' => env('RTFTT_TRANSLATION_ATTEMPT_STALE_SECONDS'),

    'contract_version' => '1.0',
];
