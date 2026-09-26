<?php

use App\Security\ClamavScanner;

/*
 * P7-006: ClamAV scanner six-behavior matrix (double-driven) + endpoint
 * discipline. Socket pairs script the daemon side; no daemon needed.
 * EICAR-standard test string only — never real malware.
 */

function clamavScriptedSocket(string $replyLine): mixed
{
    [$client, $server] = stream_socket_pair(STREAM_PF_INET, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    fwrite($server, $replyLine."\n");
    $GLOBALS['clamav_test_sockets'][] = $server;

    return $client;
}

function clamavSilentSocket(): mixed
{
    [$client, $server] = stream_socket_pair(STREAM_PF_INET, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $GLOBALS['clamav_test_sockets'][] = $server;

    return $client;
}

function closeClamavTestSockets(): void
{
    foreach ($GLOBALS['clamav_test_sockets'] ?? [] as $socket) {
        if (is_resource($socket)) {
            fclose($socket);
        }
    }

    $GLOBALS['clamav_test_sockets'] = [];
}

function clamavStreamOf(string $bytes): mixed
{
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $bytes);
    rewind($stream);

    return $stream;
}

it('reports clean on an OK reply', function (): void {
    $scanner = new ClamavScanner(fn (): mixed => clamavScriptedSocket('stream: OK'));

    try {
        $result = $scanner->scanStream(clamavStreamOf('clean bytes'), 11);

        expect($result['verdict'])->toBe('clean');
    } finally {
        closeClamavTestSockets();
    }
});

it('reports infected on a FOUND reply', function (): void {
    $scanner = new ClamavScanner(fn (): mixed => clamavScriptedSocket('stream: Eicar-Test-Signature FOUND'));

    try {
        $result = $scanner->scanStream(clamavStreamOf(ClamavScanner::EICAR), strlen(ClamavScanner::EICAR));

        expect($result['verdict'])->toBe('infected')
            ->and($result['detail'])->toContain('Eicar');
    } finally {
        closeClamavTestSockets();
    }
});

it('reports unavailable when the daemon is unreachable', function (): void {
    $scanner = new ClamavScanner(fn (): mixed => false);
    $result = $scanner->scanStream(clamavStreamOf('x'), 1);

    expect($result['verdict'])->toBe('unavailable');
});

it('reports timeout on a silent daemon inside the configured budget', function (): void {
    config()->set('security.clamav.timeout_seconds', 1);
    $scanner = new ClamavScanner(fn (): mixed => clamavSilentSocket());

    try {
        $started = microtime(true);
        $result = $scanner->scanStream(clamavStreamOf('x'), 1);

        expect($result['verdict'])->toBe('timeout')
            ->and(microtime(true) - $started)->toBeLessThan(10);
    } finally {
        closeClamavTestSockets();
    }
});

it('reports error on a garbage reply and never marks clean', function (): void {
    $scanner = new ClamavScanner(fn (): mixed => clamavScriptedSocket('GARBAGE !!!'));

    try {
        expect($scanner->scanStream(clamavStreamOf('x'), 1)['verdict'])->toBe('error');
    } finally {
        closeClamavTestSockets();
    }
});

it('refuses non-loopback tcp endpoints without an explicit allow', function (): void {
    config()->set('security.clamav.socket', null);
    config()->set('security.clamav.host', 'scanner.evil.example');
    config()->set('security.clamav.allow_non_loopback', false);

    $scanner = new ClamavScanner(fn (): mixed => throw new RuntimeException('must not connect'));

    expect($scanner->endpoint())->toHaveKey('refused')
        ->and($scanner->scanStream(clamavStreamOf('x'), 1)['verdict'])->toBe('error');
});

it('defaults to a local-only endpoint (no third-party egress)', function (): void {
    config()->set('security.clamav.socket', null);
    config()->set('security.clamav.host', '127.0.0.1');

    $endpoint = (new ClamavScanner)->endpoint();

    expect($endpoint['transport'] ?? null)->toBe('tcp')
        ->and($endpoint['target'] ?? '')->toStartWith('127.0.0.1:');
});

it('parses engine version and signature date from VERSION', function (): void {
    $scanner = new ClamavScanner(fn (): mixed => clamavScriptedSocket('ClamAV 1.4.2/27345/Thu Sep 25 08:12:01 2026'));

    try {
        $version = $scanner->version();

        expect($version['engine'])->toBe('1.4.2')
            ->and($version['signature_date'])->toBe('Thu Sep 25 08:12:01 2026');
    } finally {
        closeClamavTestSockets();
    }
});
