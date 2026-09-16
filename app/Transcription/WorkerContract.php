<?php

namespace App\Transcription;

/**
 * Worker contract version constants.
 *
 * Used in request/response payloads to ensure compatibility.
 */
final class WorkerContract
{
    public const VERSION = '1.0';

    private function __construct() {}
}
