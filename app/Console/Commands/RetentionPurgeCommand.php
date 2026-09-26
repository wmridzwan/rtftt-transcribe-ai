<?php

namespace App\Console\Commands;

use App\Retention\RetentionPurge;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Execute the D7-06 retention purge (P7-011).
 *
 * Deletes physical bytes for retention-eligible lifecycles
 * (`transcriptions.completed_at + 30 days`) and expired abandoned
 * staging, tombstoning rows instead of hard-deleting history.
 * `--dry-run` reports eligibility and deletes nothing. Every decision
 * is audited; any failure exits non-zero without false success.
 */
#[Signature('retention:purge {--dry-run : Report eligibility without deleting anything}')]
#[Description('Purge retention-eligible bytes with tombstoned history (P7-011).')]
class RetentionPurgeCommand extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $result = RetentionPurge::run($dryRun);

        $this->line('Retention purge run '.$result['run_id'].($dryRun ? ' (dry-run)' : ''));

        foreach ($result['outcomes'] as $outcome => $count) {
            $this->line("  {$outcome}: {$count}");
        }

        if ($result['failures'] !== []) {
            foreach ($result['failures'] as $failure) {
                $this->error('  failure: '.$failure);
            }

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Dry-run complete; nothing was deleted.');
        } else {
            $this->info('Retention purge complete.');
        }

        return self::SUCCESS;
    }
}
