<?php

use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function makeStreamableMedia(User $user, string $bytes, string $mime = 'audio/mpeg'): MediaFile
{
    $media = MediaFile::factory()->create([
        'user_id' => $user->id,
        'mime_type' => $mime,
    ]);

    Storage::disk(config('media.storage_disk'))->put($media->storage_path, $bytes);

    return $media;
}

function streamUrl(MediaFile $media): string
{
    return route('media.stream', ['mediaFile' => $media->uuid]);
}

it('streams the full media for its owner', function () {
    $user = User::factory()->create();
    $media = makeStreamableMedia($user, 'ABCDEFGHIJ');

    $response = $this->actingAs($user)->get(streamUrl($media));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'audio/mpeg');
    $response->assertHeader('Accept-Ranges', 'bytes');
    $response->assertHeader('Content-Length', '10');
    expect($response->streamedContent())->toBe('ABCDEFGHIJ');
});

it('serves a 206 response for bytes=0-', function () {
    $user = User::factory()->create();
    $media = makeStreamableMedia($user, 'ABCDEFGHIJ');

    $response = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=0-'])
        ->get(streamUrl($media));

    $response->assertStatus(206);
    $response->assertHeader('Content-Range', 'bytes 0-9/10');
    $response->assertHeader('Content-Length', '10');
    expect($response->streamedContent())->toBe('ABCDEFGHIJ');
});

it('serves a 206 response for bytes=N-M', function () {
    $user = User::factory()->create();
    $media = makeStreamableMedia($user, 'ABCDEFGHIJ');

    $response = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=2-5'])
        ->get(streamUrl($media));

    $response->assertStatus(206);
    $response->assertHeader('Content-Range', 'bytes 2-5/10');
    $response->assertHeader('Content-Length', '4');
    expect($response->streamedContent())->toBe('CDEF');
});

it('serves a 206 response for bytes=N-', function () {
    $user = User::factory()->create();
    $media = makeStreamableMedia($user, 'ABCDEFGHIJ');

    $response = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=5-'])
        ->get(streamUrl($media));

    $response->assertStatus(206);
    $response->assertHeader('Content-Range', 'bytes 5-9/10');
    $response->assertHeader('Content-Length', '5');
    expect($response->streamedContent())->toBe('FGHIJ');
});

it('serves a 206 suffix response for bytes=-N', function () {
    $user = User::factory()->create();
    $media = makeStreamableMedia($user, 'ABCDEFGHIJ');

    $response = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=-3'])
        ->get(streamUrl($media));

    $response->assertStatus(206);
    $response->assertHeader('Content-Range', 'bytes 7-9/10');
    $response->assertHeader('Content-Length', '3');
    expect($response->streamedContent())->toBe('HIJ');
});

it('returns 416 for an unsatisfiable range', function () {
    $user = User::factory()->create();
    $media = makeStreamableMedia($user, 'ABCDEFGHIJ');

    $response = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=10-'])
        ->get(streamUrl($media));

    $response->assertStatus(416);
    $response->assertHeader('Content-Range', 'bytes */10');
});

it('ignores malformed and multiple ranges and returns the full response', function () {
    $user = User::factory()->create();
    $media = makeStreamableMedia($user, 'ABCDEFGHIJ');

    foreach (['bytes=abc', 'bytes=0-1,3-4', 'bytes=', 'items=0-1'] as $range) {
        $response = $this->actingAs($user)
            ->withHeaders(['Range' => $range])
            ->get(streamUrl($media));

        $response->assertOk();
        expect($response->streamedContent())->toBe('ABCDEFGHIJ');
    }
});

it('denies unauthenticated streaming', function () {
    $user = User::factory()->create();
    $media = makeStreamableMedia($user, 'ABCDEFGHIJ');

    $this->get(streamUrl($media))->assertRedirect('/login');
});

it('denies cross-user streaming', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $media = makeStreamableMedia($owner, 'ABCDEFGHIJ');

    $this->actingAs($other)->get(streamUrl($media))->assertForbidden();
});

it('returns 404 when the physical file is missing', function () {
    $user = User::factory()->create();
    $media = MediaFile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(streamUrl($media))->assertNotFound();
});

it('serves the persisted MIME type for audio and video', function () {
    $user = User::factory()->create();
    $audio = makeStreamableMedia($user, 'AUDIO', 'audio/mpeg');
    $video = makeStreamableMedia($user, 'VIDEO', 'video/mp4');

    $this->actingAs($user)->get(streamUrl($audio))->assertHeader('Content-Type', 'audio/mpeg');
    $this->actingAs($user)->get(streamUrl($video))->assertHeader('Content-Type', 'video/mp4');
});

it('answers HEAD requests with range headers', function () {
    $user = User::factory()->create();
    $media = makeStreamableMedia($user, 'ABCDEFGHIJ');

    $response = $this->actingAs($user)->head(streamUrl($media));

    $response->assertOk();
    $response->assertHeader('Accept-Ranges', 'bytes');
    $response->assertHeader('Content-Type', 'audio/mpeg');
    $response->assertHeader('Content-Length', '10');
});

it('does not expose the private storage path', function () {
    $user = User::factory()->create();
    $media = makeStreamableMedia($user, 'ABCDEFGHIJ');

    $response = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=0-4'])
        ->get(streamUrl($media));

    $headers = collect($response->headers->all())
        ->map(fn (array $values): string => implode(' ', $values))
        ->implode(' ');

    expect($response->streamedContent())->not->toContain($media->storage_path)
        ->and($headers)->not->toContain($media->storage_path)
        ->and($response->streamedContent())->not->toContain('media/');
});
