<?php

use App\Actions\MediaIngestionService;
use App\Models\MediaFile;
use App\Models\User;
use App\Security\ClamavScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * P7-006: scan-gate ingestion integration. Clean verdicts persist per
 * media; infected uploads are quarantined + rejected with no row;
 * disabled scanners skip in non-production and fail closed in
 * production-shaped runs.
 */

function scanFakeSocket(string $replyLine): mixed
{
    [$client, $server] = stream_socket_pair(STREAM_PF_INET, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    fwrite($server, $replyLine."\n");
    $GLOBALS['scan_test_sockets'][] = $server;

    return $client;
}

function closeScanTestSockets(): void
{
    foreach ($GLOBALS['scan_test_sockets'] ?? [] as $socket) {
        if (is_resource($socket)) {
            fclose($socket);
        }
    }

    $GLOBALS['scan_test_sockets'] = [];
}

function bindScanFake(string $replyLine): void
{
    app()->instance(ClamavScanner::class, new ClamavScanner(fn (): mixed => scanFakeSocket($replyLine)));
    config()->set('security.clamav.enabled', true);
}

function scanTestFile(): UploadedFile
{
    return UploadedFile::fake()
        ->createWithContent('talk.mp3', str_repeat('a', 2048))->mimeType('audio/mpeg');
}

it('persists clean scan provenance on the media row', function (): void {
    Storage::fake('local');
    // Real INSTREAM clean reply (VERSION probe gets the same reply and
    // yields a null engine — verdict stays clean):
    app()->instance(ClamavScanner::class, new ClamavScanner(fn (): mixed => scanFakeSocket('stream: OK')));
    config()->set('security.clamav.enabled', true);

    $user = User::factory()->create();

    try {
        $media = app(MediaIngestionService::class)->ingest($user, scanTestFile(), (string) Str::uuid());

        expect($media->scan_verdict)->toBe('clean')
            ->and($media->scanned_at)->not->toBeNull();
    } finally {
        closeScanTestSockets();
    }
});

it('quarantines infected uploads and persists no row', function (): void {
    Storage::fake('local');
    bindScanFake('stream: Eicar-Test-Signature FOUND');
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();

    try {
        app(MediaIngestionService::class)->ingest($user, scanTestFile(), $attemptId);

        $this->fail('Infected upload must be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->validator->errors()->first('media_file'))->toContain('malware');
    } finally {
        closeScanTestSockets();
    }

    expect(MediaFile::query()->where('upload_attempt_id', $attemptId)->exists())->toBeFalse()
        ->and(Storage::disk('local')->files('quarantine'))->not->toBeEmpty();
});

it('rejects uploads when the enabled scanner is unreachable', function (): void {
    Storage::fake('local');
    app()->instance(ClamavScanner::class, new ClamavScanner(fn (): mixed => false));
    config()->set('security.clamav.enabled', true);
    $user = User::factory()->create();

    try {
        app(MediaIngestionService::class)->ingest($user, scanTestFile(), (string) Str::uuid());

        $this->fail('Unavailable scanner must fail closed.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(503);
    }

    expect(MediaFile::query()->count())->toBe(0);
});

it('skips scanning with a skipped verdict when disabled outside production', function (): void {
    Storage::fake('local');
    config()->set('security.clamav.enabled', false);
    $user = User::factory()->create();

    $media = app(MediaIngestionService::class)->ingest($user, scanTestFile(), (string) Str::uuid());

    expect($media->scan_verdict)->toBe('skipped')->and($media->scanned_at)->toBeNull();
});
