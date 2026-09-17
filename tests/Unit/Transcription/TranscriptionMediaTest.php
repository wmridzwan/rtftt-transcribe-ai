<?php

use App\Transcription\TranscriptionMedia;

it('creates a valid media reference', function () {
    $media = new TranscriptionMedia(
        storageKey: 'media/abc123.mp3',
        mimeType: 'audio/mpeg',
        fileSizeBytes: 1024000,
    );

    expect($media->storageKey)->toBe('media/abc123.mp3');
    expect($media->mimeType)->toBe('audio/mpeg');
    expect($media->fileSizeBytes)->toBe(1024000);
    expect($media->durationSeconds)->toBeNull();
});

it('accepts duration seconds', function () {
    $media = new TranscriptionMedia(
        storageKey: 'media/abc123.mp3',
        mimeType: 'audio/mpeg',
        fileSizeBytes: 1024000,
        durationSeconds: 120.5,
    );

    expect($media->durationSeconds)->toBe(120.5);
});

it('rejects empty storage key', function () {
    new TranscriptionMedia(
        storageKey: '',
        mimeType: 'audio/mpeg',
        fileSizeBytes: 1024000,
    );
})->throws(InvalidArgumentException::class, 'Storage key must not be empty.');

it('rejects absolute unix path', function () {
    new TranscriptionMedia(
        storageKey: '/etc/passwd',
        mimeType: 'audio/mpeg',
        fileSizeBytes: 1024000,
    );
})->throws(InvalidArgumentException::class, 'Storage key must be a relative path.');

it('rejects absolute windows path', function () {
    new TranscriptionMedia(
        storageKey: '\\server\share\file.mp3',
        mimeType: 'audio/mpeg',
        fileSizeBytes: 1024000,
    );
})->throws(InvalidArgumentException::class, 'Storage key must be a relative path.');

it('rejects windows drive-letter path with backslash', function () {
    new TranscriptionMedia(
        storageKey: 'C:\\Windows\\file.mp3',
        mimeType: 'audio/mpeg',
        fileSizeBytes: 1024000,
    );
})->throws(InvalidArgumentException::class, 'Storage key must be a relative path.');

it('rejects windows drive-letter path with forward slash', function () {
    new TranscriptionMedia(
        storageKey: 'C:/Windows/file.mp3',
        mimeType: 'audio/mpeg',
        fileSizeBytes: 1024000,
    );
})->throws(InvalidArgumentException::class, 'Storage key must be a relative path.');

it('rejects path traversal', function () {
    new TranscriptionMedia(
        storageKey: 'media/../../../etc/passwd',
        mimeType: 'audio/mpeg',
        fileSizeBytes: 1024000,
    );
})->throws(InvalidArgumentException::class, 'Storage key must not contain path traversal.');

it('rejects negative file size', function () {
    new TranscriptionMedia(
        storageKey: 'media/abc123.mp3',
        mimeType: 'audio/mpeg',
        fileSizeBytes: -1,
    );
})->throws(InvalidArgumentException::class, 'File size must be non-negative.');
