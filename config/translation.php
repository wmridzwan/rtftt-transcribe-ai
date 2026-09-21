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

    'timeout_seconds' => (int) env('RTFTT_TRANSLATION_TIMEOUT_SECONDS', 300),

    'contract_version' => '1.0',
];
