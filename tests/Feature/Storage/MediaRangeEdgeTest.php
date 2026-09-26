<?php

use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
 * P7-004 / TD-011: range-edge branches the P4-002 suite never pinned.
 * Pre-existing matrix (full/0-/N-M/N-/-N/416/malformed/auth/404/MIME/
 * HEAD/no-leak) stays owned by tests/Feature/MediaStreamingTest.php and
 * is not duplicated here: only the uncovered edges live below.
 */

beforeEach(function () {
    Storage::fake('local');
});

function makeEdgeMedia(User $user, string $bytes): MediaFile
{
    $media = MediaFile::factory()->create(['user_id' => $user->id]);

    Storage::disk(config('media.storage_disk'))->put($media->storage_path, $bytes);

    return $media;
}

function edgeUrl(MediaFile $media): string
{
    return route('media.stream', ['mediaFile' => $media->uuid]);
}

it('clamps a range end past the file size', function (): void {
    $user = User::factory()->create();
    $media = makeEdgeMedia($user, 'ABCDEFGHIJ');

    $response = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=5-99'])
        ->get(edgeUrl($media));

    $response->assertStatus(206);
    $response->assertHeader('Content-Range', 'bytes 5-9/10');
    $response->assertHeader('Content-Length', '5');
    expect($response->streamedContent())->toBe('FGHIJ');
});

it('serves a suffix larger than the file as the whole file', function (): void {
    $user = User::factory()->create();
    $media = makeEdgeMedia($user, 'ABCDEFGHIJ');

    $response = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=-99'])
        ->get(edgeUrl($media));

    $response->assertStatus(206);
    $response->assertHeader('Content-Range', 'bytes 0-9/10');
    expect($response->streamedContent())->toBe('ABCDEFGHIJ');
});

it('serves a single-byte range', function (): void {
    $user = User::factory()->create();
    $media = makeEdgeMedia($user, 'ABCDEFGHIJ');

    $response = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=9-9'])
        ->get(edgeUrl($media));

    $response->assertStatus(206);
    $response->assertHeader('Content-Range', 'bytes 9-9/10');
    $response->assertHeader('Content-Length', '1');
    expect($response->streamedContent())->toBe('J');
});

it('returns 416 for a zero-length suffix', function (): void {
    $user = User::factory()->create();
    $media = makeEdgeMedia($user, 'ABCDEFGHIJ');

    $response = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=-0'])
        ->get(edgeUrl($media));

    $response->assertStatus(416);
    $response->assertHeader('Content-Range', 'bytes */10');
});

it('returns the full response when the range end precedes the start', function (): void {
    $user = User::factory()->create();
    $media = makeEdgeMedia($user, 'ABCDEFGHIJ');

    $response = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=5-2'])
        ->get(edgeUrl($media));

    $response->assertOk();
    expect($response->streamedContent())->toBe('ABCDEFGHIJ');
});

it('delivers exactly the ranged bytes for a large file (bounded delivery)', function (): void {
    $user = User::factory()->create();
    $bytes = str_repeat('0123456789ABCDEF', 65536); // 1 MiB
    $media = makeEdgeMedia($user, $bytes);

    $response = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=1048576-1049599'])
        ->get(edgeUrl($media));

    // 1 MiB file is 1048576 bytes; bytes=1048576-… starts past EOF.
    $response->assertStatus(416);

    $mid = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=100-1099'])
        ->get(edgeUrl($media));

    $mid->assertStatus(206);
    $mid->assertHeader('Content-Length', '1000');
    expect($mid->streamedContent())->toBe(substr($bytes, 100, 1000));
});
