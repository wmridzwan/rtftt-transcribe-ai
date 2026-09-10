<?php

namespace App\Enums;

enum TranscriptionStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Preparing = 'preparing';
    case Transcribing = 'transcribing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
