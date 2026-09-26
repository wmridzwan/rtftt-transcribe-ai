<?php

namespace App\Console\Commands;

use App\Security\ClamavScanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * ClamAV service health (P7-006).
 *
 * Probes daemon reachability, engine version, and signature freshness.
 * Stale signatures hold (never skip) scanning per the fail-closed
 * policy. Exit 0 = healthy; exit 1 = unreachable or stale. Informational
 * in any environment; scheduled daily so missed signature updates alert
 * through the runbook path. Changes nothing.
 */
#[Signature('clamav:health')]
#[Description('Probe ClamAV daemon reachability and signature freshness (P7-006).')]
class ClamavHealth extends Command
{
    public function handle(ClamavScanner $scanner): int
    {
        if (! $scanner->isEnabled()) {
            $this->line('ClamAV: disabled (CLAMAV_ENABLED=false; non-production skip, production fail-closed at scan time).');

            return self::SUCCESS;
        }

        $health = $scanner->health();

        $this->line('ClamAV: '.($health['reachable'] ? 'reachable' : 'unreachable'));
        $this->line('Engine: '.($health['engine'] ?? 'unknown'));
        $this->line('Signatures: '.($health['signature_date'] ?? 'unknown')
            .($health['signature_age_hours'] !== null ? sprintf(' (%.1fh old)', $health['signature_age_hours']) : ''));
        $this->line('Freshness: '.($health['stale'] ? 'STALE (scanning holds)' : 'ok'));

        if (! $health['reachable'] || $health['stale']) {
            $this->warn('Detail: '.$health['detail']);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
