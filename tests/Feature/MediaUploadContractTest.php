<?php

use App\Models\MediaFile;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

test('supported media contract defines the approved extension and mime matrix', function () {
    $supportedMedia = config('media.supported_media');

    expect($supportedMedia)->toBe([
        'audio' => [
            'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg-3'],
            'wav' => ['audio/wav', 'audio/wave', 'audio/x-wav'],
            'm4a' => ['audio/mp4', 'audio/x-m4a'],
            'aac' => ['audio/aac', 'audio/x-aac'],
            'flac' => ['audio/flac', 'audio/x-flac'],
            'ogg' => ['audio/ogg', 'application/ogg'],
        ],
        'video' => [
            'mp4' => ['video/mp4'],
            'mov' => ['video/quicktime'],
            'webm' => ['video/webm'],
        ],
    ]);
});

test('media ingestion configuration centralizes the approved 500 MiB size and lifecycle values', function () {
    $maxUploadBytes = config('media.max_upload_bytes');

    expect($maxUploadBytes)->toBe(524_288_000)
        ->and($maxUploadBytes)->toBe(500 * 1024 * 1024)
        ->and(config('media.max_files_per_upload'))->toBe(1)
        ->and(config('media.max_duration_seconds'))->toBeNull()
        ->and(config('media.allow_duplicate_uploads'))->toBeTrue()
        ->and(config('media.post_upload_route'))->toBe('media.show')
        ->and(config('media.temporary_retention_hours'))->toBe(24)
        ->and(config('media.storage_disk'))->toBe('local')
        ->and(config('media.staging_directory'))->toBe('media/.staging')
        ->and(config('media.storage_directory'))->toBe('media')
        ->and(config('media.checksum'))->toBe([
            'algorithm' => 'sha256',
            'encoding' => 'lowercase_hex',
            'length' => 64,
        ]);
});

test('media storage uses the existing private local disk', function () {
    expect(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'))
        ->and(config('filesystems.disks.public.root'))->not->toBe(config('filesystems.disks.local.root'));
});

test('media storage boundary rejects a public disk before use', function () {
    config(['media.storage_disk' => 'public']);

    expect(fn () => MediaFile::storage())
        ->toThrow(LogicException::class, 'The configured media storage disk must be private.');
});

test('media files have a nullable indexed sha256 checksum column', function () {
    expect(Schema::hasColumn('media_files', 'checksum_sha256'))->toBeTrue();

    expect(Schema::getColumnType('media_files', 'checksum_sha256'))->toBeIn(['string', 'varchar']);

    $checksumColumn = collect(Schema::getColumns('media_files'))
        ->firstWhere('name', 'checksum_sha256');
    $checksumIndex = collect(Schema::getIndexes('media_files'))
        ->first(fn (array $index): bool => $index['columns'] === ['checksum_sha256']);

    expect($checksumColumn['nullable'])->toBeTrue()
        ->and($checksumIndex['unique'])->toBeFalse();
});

test('media file checksum is server-assigned and duplicate checksums remain allowed', function () {
    $checksum = '2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824';
    $firstMediaFile = MediaFile::factory()->make();
    $secondMediaFile = MediaFile::factory()->create();

    $firstMediaFile->fill(['checksum_sha256' => str_repeat('A', 64)]);

    expect($firstMediaFile->checksum_sha256)->toBeNull();

    $firstMediaFile->assignGeneratedChecksumSha256($checksum)->save();
    $secondMediaFile->assignGeneratedChecksumSha256($checksum)->save();

    expect($firstMediaFile->refresh()->checksum_sha256)->toBe($checksum)
        ->and($secondMediaFile->refresh()->checksum_sha256)->toBe($checksum)
        ->and(MediaFile::where('checksum_sha256', $checksum)->count())->toBe(2);
});

test('media file checksum rejects values outside the server-generated sha256 format', function (string $checksum) {
    $mediaFile = MediaFile::factory()->make();

    expect(fn () => $mediaFile->assignGeneratedChecksumSha256($checksum))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'uppercase hexadecimal' => str_repeat('A', 64),
    'too short' => str_repeat('a', 63),
    'too long' => str_repeat('a', 65),
    'non hexadecimal' => str_repeat('g', 64),
]);

test('media file checksum remains nullable for existing prototype records', function () {
    $mediaFile = MediaFile::factory()->make([
        'checksum_sha256' => null,
    ]);

    expect($mediaFile->checksum_sha256)->toBeNull();
});
