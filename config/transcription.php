<?php

return [
    'worker_url' => env('RTFTT_TRANSCRIPTION_WORKER_URL', 'http://localhost:8000'),

    'worker_token' => env('RTFTT_TRANSCRIPTION_WORKER_TOKEN', ''),

    'contract_version' => '1.0',

    'timeout_seconds' => 300,

    'prepared_audio_retention' => env('RTFTT_PREPARED_AUDIO_RETENTION', 'ephemeral'),

    'shared_media_root' => env('RTFTT_SHARED_MEDIA_ROOT', storage_path('app/media')),
];
