<?php

namespace App\Console\Commands;

use App\Actions\StaleTranslationAttemptRecovery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('translation:recover-stale-attempts')]
#[Description('Mark demonstrably stale translating translations as recoverable failures (manual retry stays explicit).')]
class RecoverStaleTranslationAttempts extends Command
{
    public function handle(StaleTranslationAttemptRecovery $recovery): int
    {
        $count = $recovery->recover();

        $this->info("Recovered {$count} stale translation attempt(s).");

        return self::SUCCESS;
    }
}
