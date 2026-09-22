<?php

namespace App\Console\Commands;

use App\Actions\StaleTranscriptionAttemptRecovery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('transcription:recover-stale-attempts')]
#[Description('Mark demonstrably stale running transcription attempts as recoverable failures (manual retry stays explicit).')]
class RecoverStaleTranscriptionAttempts extends Command
{
    public function handle(StaleTranscriptionAttemptRecovery $recovery): int
    {
        $count = $recovery->recover();

        $this->info("Recovered {$count} stale transcription attempt(s).");

        return self::SUCCESS;
    }
}
