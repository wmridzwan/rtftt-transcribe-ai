<?php

namespace App\Console\Commands;

use App\Deployment\TargetHostEvidence;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Record real-host carry-forward evidence (P7-001, AC8).
 *
 * Validates a target-host evidence JSON file against the
 * `TargetHostEvidence` schema (AC2 reboot-cycle + AC8 SIGTERM-drain,
 * each with result/detail/recorded_at) and retains it. Exit 0 records;
 * exit 1 rejects an unrecordable payload. This command validates and
 * stores operator-supplied evidence; it performs no host verification
 * itself and manufactures no PASS.
 */
#[Signature('deployment:record-target-evidence {file : Path to the evidence JSON file}')]
#[Description('Validate and retain real-host AC2/AC8 carry-forward evidence (P7-001).')]
class RecordTargetEvidence extends Command
{
    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_readable($file)) {
            $this->error("Evidence file is not readable: {$file}");

            return self::FAILURE;
        }

        try {
            $payload = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            TargetHostEvidence::record(is_array($payload) ? $payload : []);
        } catch (Throwable $exception) {
            $this->error('Evidence rejected: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Target evidence recorded: '.TargetHostEvidence::path());

        foreach (TargetHostEvidence::EXPECTED_ITEMS as $item) {
            $this->line("Evidence [{$item}]: ".TargetHostEvidence::itemStatus($item));
        }

        return self::SUCCESS;
    }
}
