<?php

namespace App\Console\Commands;

use App\Deployment\UploadLimitAudit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Upload-limit adequacy audit (P7-001, TD-002 application share).
 *
 * Reports the effective PHP receiving limits against the required 600 MiB
 * floor (500 MiB product boundary + overhead; see UploadLimitAudit).
 * `--json` emits a machine-parseable report for the P7-009 harness.
 * Exits non-zero on FAIL so pipelines and readiness gates fail loudly;
 * this command changes nothing.
 */
#[Signature('env:audit-limits {--json : Emit a machine-parseable JSON report}')]
#[Description('Audit effective PHP upload limits against the 500 MiB product boundary plus overhead (P7-001).')]
class EnvAuditLimits extends Command
{
    public function handle(): int
    {
        $report = UploadLimitAudit::evaluate();

        if ($this->option('json')) {
            $encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $this->line($encoded === false ? '{"pass":false}' : $encoded);

            return $report['pass'] ? self::SUCCESS : self::FAILURE;
        }

        $this->line('upload_max_filesize: '.UploadLimitAudit::formatBytes($report['upload_max_filesize']));
        $this->line('post_max_size: '.UploadLimitAudit::formatBytes($report['post_max_size']));
        $this->line('required minimum: '.UploadLimitAudit::formatBytes($report['required']));
        $this->line('verdict: '.($report['pass'] ? 'PASS' : 'FAIL'));

        foreach ($report['findings'] as $finding) {
            $this->warn($finding);
        }

        return $report['pass'] ? self::SUCCESS : self::FAILURE;
    }
}
