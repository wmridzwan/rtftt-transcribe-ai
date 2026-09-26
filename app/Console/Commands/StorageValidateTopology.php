<?php

namespace App\Console\Commands;

use App\Storage\StorageTopology;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Validate the local private-storage topology (P7-004).
 *
 * Scripted production-mount checks with retained output: local driver,
 * root existence/writability, free-space floor. Exit 0 when clean,
 * exit 1 with loud violations otherwise. Never claims capacity proof
 * (G-01/G-10 belong to P7-009/P7-012).
 */
#[Signature('storage:validate-topology')]
#[Description('Validate local private-storage topology: driver, root, writability, free space (P7-004).')]
class StorageValidateTopology extends Command
{
    public function handle(): int
    {
        $result = StorageTopology::evaluate();

        foreach ($result['detail'] as $key => $value) {
            $this->line("Topology [{$key}]: {$value}");
        }

        if ($result['violations'] === []) {
            $this->info('Storage topology: ok (local private disk, writable root, capacity floor met).');

            return self::SUCCESS;
        }

        foreach ($result['violations'] as $violation) {
            $this->error('Storage topology: '.$violation);
        }

        return self::FAILURE;
    }
}
