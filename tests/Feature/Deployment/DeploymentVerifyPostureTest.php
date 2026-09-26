<?php

/*
 * P7-001: `deployment:verify` posture + target-evidence sub-checks.
 * Advisory outside production: warnings print, the gate still passes,
 * and every pre-existing check keeps working.
 */

it('reports posture and target evidence as advisory outside production', function (): void {
    // Same healthy baseline as DeploymentVerifyTest (phpunit ships
    // QUEUE_CONNECTION=sync, which the queue guards rightly flag).
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.retry_after', 420);

    $this->artisan('deployment:verify')
        ->expectsOutputToContain('Posture:')
        ->expectsOutputToContain('Target evidence [ac2-reboot-cycle]:')
        ->expectsOutputToContain('Target evidence [ac8-sigterm-drain]:')
        ->expectsOutputToContain('Deployment verification passed.')
        ->assertExitCode(0);
});

it('keeps every pre-existing check intact', function (): void {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.retry_after', 420);

    $this->artisan('deployment:verify')
        ->expectsOutputToContain('Migrations: current')
        ->expectsOutputToContain('Transcription queue: ok')
        ->expectsOutputToContain('Translation queue: ok')
        ->expectsOutputToContain('translation:recover-stale-attempts')
        ->expectsOutputToContain('transcription:recover-stale-attempts');
});
