<?php

return [
    'storage_disk' => env('RTFTT_MEDIA_DISK', 'local'),

    'staging_directory' => 'media/.staging',

    'storage_directory' => 'media',

    'checksum' => [
        'algorithm' => 'sha256',
        'encoding' => 'lowercase_hex',
        'length' => 64,
    ],

    // The approved per-file boundary is exactly 500 MiB (524,288,000 bytes).
    'max_upload_bytes' => 524_288_000,

    'max_files_per_upload' => 1,

    'max_duration_seconds' => null,

    'allow_duplicate_uploads' => true,

    'post_upload_route' => 'media.show',

    'temporary_retention_hours' => 24,

    'ffprobe_path' => env('RTFTT_FFPROBE_PATH', 'ffprobe'),

    'supported_media' => [
        'audio' => [
            'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg-3'],
            'wav' => ['audio/wav', 'audio/wave', 'audio/x-wav'],
            'm4a' => ['audio/mp4', 'audio/x-m4a'],
            'aac' => ['audio/aac', 'audio/x-aac'],
            'flac' => ['audio/flac', 'audio/x-flac'],
            'ogg' => ['audio/ogg', 'application/ogg'],
        ],
        'video' => [
            'mp4' => ['video/mp4'],
            'mov' => ['video/quicktime'],
            'webm' => ['video/webm'],
        ],
    ],
];
