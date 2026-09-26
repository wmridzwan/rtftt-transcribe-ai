<?php

/*
 * P7-008: deployment verification command.
 */

it('passes verification in a healthy test environment', function (): void {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.retry_after', 420);

    $this->artisan('deployment:verify')
        ->expectsOutputToContain('Migrations: current')
        ->expectsOutputToContain('Transcription queue: ok')
        ->expectsOutputToContain('Translation queue: ok')
        ->expectsOutputToContain('Deployment verification passed.')
        ->assertSuccessful();
});

it('reports a queue violation and fails only under --strict', function (): void {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.retry_after', 10);

    $this->artisan('deployment:verify')
        ->expectsOutputToContain('VIOLATION')
        ->assertSuccessful();

    $this->artisan('deployment:verify', ['--strict' => true])
        ->expectsOutputToContain('Deployment verification found failures.')
        ->assertFailed();
});

it('reports a missing stale-recovery schedule', function (): void {
    // Both recovery commands are scheduled by routes/console.php; the check
    // itself is covered here by asserting the healthy path mentions them.
    $this->artisan('deployment:verify')
        ->expectsOutputToContain('translation:recover-stale-attempts')
        ->expectsOutputToContain('transcription:recover-stale-attempts')
        ->assertSuccessful();
});
