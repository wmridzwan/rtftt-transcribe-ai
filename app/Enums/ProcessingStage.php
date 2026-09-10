<?php

namespace App\Enums;

enum ProcessingStage: string
{
    case Upload = 'upload';
    case Probe = 'probe';
    case ExtractAudio = 'extract_audio';
    case Transcribe = 'transcribe';
    case Finalize = 'finalize';
}
