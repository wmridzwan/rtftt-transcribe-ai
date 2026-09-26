<?php

use App\Security\ClamavScanner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;

/*
 * P7-006: csp-report endpoint, clamav:health command, production error
 * rendering, diagnostics security section.
 */

it('accepts well-formed csp reports with 204 and audits metadata only', function (): void {
    $handler = new TestHandler;
    Log::channel('structured')->getLogger()->pushHandler($handler);

    $this->postJson('/csp-report', [
        'csp-report' => [
            'document-uri' => 'http://localhost:8000/media/1',
            'violated-directive' => 'script-src-elem',
            'blocked-uri' => 'https://evil.example/x.js',
        ],
    ])->assertNoContent(204);

    $violations = array_values(array_filter(
        $handler->getRecords(),
        fn ($record): bool => ($record['context']['security_event'] ?? null) === 'csp_violation'
    ));

    expect($violations)->not->toBeEmpty()
        ->and($violations[0]['context']['blocked_uri'] ?? null)->toContain('evil.example');
});

it('rejects oversized and malformed csp reports', function (): void {
    $this->postJson('/csp-report', ['nope' => true])->assertStatus(204);

    $this->call('POST', '/csp-report', [], [], [], [], str_repeat('x', 9000))->assertStatus(413);
});

it('reports disabled scanners without failing', function (): void {
    config()->set('security.clamav.enabled', false);

    $this->artisan('clamav:health')
        ->expectsOutputToContain('disabled')
        ->assertExitCode(0);
});

it('fails health when the enabled scanner is unreachable', function (): void {
    config()->set('security.clamav.enabled', true);
    app()->instance(ClamavScanner::class, new ClamavScanner(fn (): mixed => false));

    $this->artisan('clamav:health')
        ->expectsOutputToContain('unreachable')
        ->assertExitCode(1);
});

it('passes health against a fresh scripted daemon', function (): void {
    [$client, $server] = stream_socket_pair(STREAM_PF_INET, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    fwrite($server, 'ClamAV 1.4.2/27345/'.date('D M d H:i:s Y')."\n");

    try {
        app()->instance(ClamavScanner::class, new ClamavScanner(fn (): mixed => $client));
        config()->set('security.clamav.enabled', true);

        $this->artisan('clamav:health')
            ->expectsOutputToContain('reachable')
            ->assertExitCode(0);
    } finally {
        foreach ([$client, $server] as $socket) {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }
});

it('renders production errors without leaks', function (): void {
    config()->set('app.debug', false);

    Route::get('/_p7006-boom', function (): void {
        throw new RuntimeException('secret-marker-xyz');
    });

    $response = $this->get('/_p7006-boom');

    $response->assertServerError()
        ->assertDontSee('secret-marker-xyz')
        ->assertDontSee('Trace')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('reports the security section in diagnostics', function (): void {
    $this->artisan('observability:diagnostics')
        ->expectsOutputToContain('CSP mode:')
        ->expectsOutputToContain('Limiters: login=yes')
        ->expectsOutputToContain('ClamAV:');
});
