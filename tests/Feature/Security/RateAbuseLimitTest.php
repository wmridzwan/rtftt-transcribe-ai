<?php

use App\Models\User;
use App\Security\UploadAbuseGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * P7-006: rate limiting + upload-abuse limits (TD-002 pairing side).
 * Exact boundaries enforced; rejection leaves no partial state.
 */

it('throttles upload initiation at the documented threshold', function (): void {
    Storage::fake('local');
    config()->set('security.throttles.upload_initiate', [2, 1]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('media.upload.store'), []);
    $this->actingAs($user)->post(route('media.upload.store'), []);

    // Third attempt inside the window: 429 before any controller work.
    $this->actingAs($user)->post(route('media.upload.store'), [])->assertStatus(429);
});

it('throttles logins at the documented threshold', function (): void {
    config()->set('security.throttles.login', [3, 1]);

    $payload = ['_token' => csrf_token(), 'email' => 'nobody@example.com', 'password' => 'wrong'];

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->post('/login', $payload);
    }

    $this->post('/login', $payload)->assertStatus(429);
});

it('enforces the per-hour upload count at the exact boundary', function (): void {
    config()->set('security.abuse.max_uploads_per_hour', 2);
    $user = User::factory()->create();

    UploadAbuseGuard::check($user, 100);
    UploadAbuseGuard::check($user, 100);

    expect(fn () => UploadAbuseGuard::check($user, 100))
        ->toThrow(HttpException::class, 'at most 2 uploads per hour');
});

it('enforces the daily byte cap at the exact boundary', function (): void {
    config()->set('security.abuse.max_bytes_per_day', 1000);
    $user = User::factory()->create();

    UploadAbuseGuard::check($user, 600);
    UploadAbuseGuard::check($user, 400);

    expect(fn () => UploadAbuseGuard::check($user, 1))
        ->toThrow(HttpException::class, 'daily upload volume exhausted');
});

it('leaves no partial state when the abuse limit rejects', function (): void {
    Storage::fake('local');
    config()->set('security.abuse.max_uploads_per_hour', 1);
    $user = User::factory()->create();

    $file = fn (): UploadedFile => UploadedFile::fake()
        ->createWithContent('talk.mp3', str_repeat('a', 1024))->mimeType('audio/mpeg');

    $this->actingAs($user)->post(route('media.upload.store'), [
        'upload_attempt_id' => (string) Str::uuid(),
        'media_file' => $file(),
    ])->assertRedirect();

    $this->actingAs($user)->post(route('media.upload.store'), [
        'upload_attempt_id' => (string) Str::uuid(),
        'media_file' => $file(),
    ])->assertStatus(429);

    expect(User::find($user->id)->mediaFiles()->count())->toBe(1);
});
