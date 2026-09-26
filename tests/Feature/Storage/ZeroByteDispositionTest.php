<?php

use App\Actions\MediaIngestionService;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * P7-004 / TD-011: zero-byte disposition. Empty uploads ARE accepted by
 * ingestion (size 0 passes the byte ceiling and detects a MIME type), so
 * the edge is reachable and handled explicitly: the endpoint serves an
 * honest empty 200 and rejects every range with 416, never claiming
 * Content-Length 1 for a 0-byte object.
 */

beforeEach(function () {
    Storage::fake('local');
});

it('records an empty upload as a durable 0-byte row (edge is reachable)', function (): void {
    $user = User::factory()->create();
    $service = app(MediaIngestionService::class);

    $file = UploadedFile::fake()->createWithContent('empty.mp3', '');

    $media = $service->ingest($user, $file, (string) Str::uuid());

    expect($media->file_size_bytes)->toBe(0)
        ->and(MediaFile::count())->toBe(1);
});

it('serves a 0-byte object as an empty 200 and 416s its ranges', function (): void {
    $user = User::factory()->create();

    $media = MediaFile::factory()->create(['user_id' => $user->id]);
    Storage::disk(config('media.storage_disk'))->put($media->storage_path, '');

    $url = route('media.stream', ['mediaFile' => $media->uuid]);

    $full = $this->actingAs($user)->get($url);
    $full->assertOk();
    $full->assertHeader('Content-Length', '0');
    expect($full->getContent())->toBe('');

    $ranged = $this->actingAs($user)->withHeaders(['Range' => 'bytes=0-'])->get($url);
    $ranged->assertStatus(416);
    $ranged->assertHeader('Content-Range', 'bytes */0');
});
