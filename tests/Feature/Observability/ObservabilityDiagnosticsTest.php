<?php

use Illuminate\Support\Facades\Http;
use Monolog\Formatter\JsonFormatter;

it('configures a structured JSON log channel without changing the default', function () {
    expect(config('logging.channels.structured.driver'))->toBe('daily')
        ->and(config('logging.channels.structured.formatter'))->toBe(JsonFormatter::class)
        ->and(config('logging.channels.structured.days'))->toBeInt()
        ->and(config('logging.default'))->not->toBe('structured');
})->group('p7-005');

it('runs observability diagnostics successfully', function () {
    // P7-003 closed the sync exemption: the prohibited `sync` test default
    // must be overridden with an allowed driver for the ok-path assertion.
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.retry_after', 420);

    $this->artisan('observability:diagnostics')
        ->expectsOutputToContain('Structured channel configured: yes')
        ->expectsOutputToContain('Translation queue:')
        ->expectsOutputToContain('Timeout consistency: ok')
        ->assertSuccessful();
})->group('p7-005');

it('derives the stale-recovery schedule from the live scheduler', function () {
    $this->artisan('observability:diagnostics')
        ->expectsOutputToContain('translation:recover-stale-attempts (every minute, without overlapping)')
        ->assertSuccessful();
})->group('p7-005');

it('reports a timeout violation as a failure only under --strict', function () {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.retry_after', 10);

    $this->artisan('observability:diagnostics')
        ->expectsOutputToContain('Timeout consistency: VIOLATION')
        ->assertSuccessful();

    $this->artisan('observability:diagnostics', ['--strict' => true])
        ->expectsOutputToContain('Timeout consistency: VIOLATION')
        ->assertFailed();
})->group('p7-005');

it('probes configured workers and reports them reachable', function () {
    config()->set('transcription.worker_url', 'http://worker.test');
    config()->set('translation.worker_url', 'http://worker.test');
    Http::fake(['http://worker.test/health' => Http::response('ok', 200)]);

    $this->artisan('observability:diagnostics', ['--probe-worker' => true])
        ->expectsOutputToContain('Worker (transcription): reachable')
        ->expectsOutputToContain('Worker (translation): reachable')
        ->assertSuccessful();
})->group('p7-005');

it('reports unconfigured workers without failing', function () {
    config()->set('transcription.worker_url', '');
    config()->set('translation.worker_url', '');

    $this->artisan('observability:diagnostics', ['--probe-worker' => true])
        ->expectsOutputToContain('Worker (transcription): not configured')
        ->expectsOutputToContain('Worker (translation): not configured')
        ->assertSuccessful();
})->group('p7-005');

it('fails under --strict when a probed worker is unreachable', function () {
    config()->set('transcription.worker_url', 'http://worker.test');
    config()->set('translation.worker_url', 'http://worker.test');
    Http::fake(['http://worker.test/health' => Http::response('down', 503)]);

    $this->artisan('observability:diagnostics', ['--probe-worker' => true])
        ->expectsOutputToContain('unreachable')
        ->assertSuccessful();

    $this->artisan('observability:diagnostics', ['--probe-worker' => true, '--strict' => true])
        ->expectsOutputToContain('unreachable')
        ->assertFailed();
})->group('p7-005');
