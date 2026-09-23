<?php

use Monolog\Formatter\JsonFormatter;

it('configures a structured JSON log channel without changing the default', function () {
    expect(config('logging.channels.structured.driver'))->toBe('daily')
        ->and(config('logging.channels.structured.formatter'))->toBe(JsonFormatter::class)
        ->and(config('logging.default'))->not->toBe('structured');
})->group('p7-005');

it('runs observability diagnostics successfully', function () {
    $this->artisan('observability:diagnostics')
        ->expectsOutputToContain('Structured channel configured: yes')
        ->expectsOutputToContain('Translation queue:')
        ->expectsOutputToContain('Timeout consistency: ok')
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
