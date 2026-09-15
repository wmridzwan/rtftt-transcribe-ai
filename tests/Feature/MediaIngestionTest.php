<?php

use App\Actions\MediaIngestionService;
use App\Enums\MediaStatus;
use App\Models\Folder;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Storage::fake('local');
});

test('the media upload page exposes the real multipart upload form', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('media.upload'))
        ->assertOk()
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee(route('media.upload.store'), false)
        ->assertSee('XMLHttpRequest', false)
        ->assertSee('Finalizing…', false)
        ->assertSee('Maximum size: 500 MiB');
});

test('authenticated user can ingest one supported file into private media storage', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $attemptId = (string) Str::uuid();
    $this->actingAs($user);

    $response = $this->post(route('media.upload.store'), [
        'media_file' => UploadedFile::fake()->createWithContent('Weekly Meeting.mp3', str_repeat('a', 1024))->mimeType('audio/mpeg'),
        'folder_id' => $folder->id,
        'upload_attempt_id' => $attemptId,
    ]);

    $mediaFile = MediaFile::query()->firstOrFail();

    $response->assertRedirect(route('media.show', $mediaFile));
    expect($mediaFile->user_id)->toBe($user->id)
        ->and($mediaFile->folder_id)->toBe($folder->id)
        ->and($mediaFile->original_filename)->toBe('Weekly Meeting.mp3')
        ->and($mediaFile->display_name)->toBe('Weekly Meeting')
        ->and($mediaFile->extension)->toBe('mp3')
        ->and($mediaFile->mime_type)->toBe('audio/mpeg')
        ->and($mediaFile->media_type->value)->toBe('audio')
        ->and($mediaFile->file_size_bytes)->toBe(1024)
        ->and($mediaFile->duration_seconds)->toBeNull()
        ->and($mediaFile->status)->toBe(MediaStatus::Uploaded)
        ->and($mediaFile->upload_attempt_id)->toBe($attemptId)
        ->and($mediaFile->checksum_sha256)->toMatch('/\A[0-9a-f]{64}\z/');

    expect($mediaFile->storage_path)
        ->toMatch('/\Amedia\/[0-9a-f-]{36}\/[A-Za-z0-9]{40}\.mp3\z/')
        ->not->toContain('media/'.$user->id.'/')
        ->not->toContain('Weekly Meeting');

    Storage::disk('local')->assertExists($mediaFile->storage_path);
    expect(Storage::disk('local')->allFiles('media/.staging'))->toBe([]);
    expect(Transcription::query()->count())->toBe(0)
        ->and(ProcessingJob::query()->count())->toBe(0);
});

test('exact product boundary is accepted and one byte over is rejected by application validation', function () {
    MediaIngestionService::validateByteSize(524_288_000);

    expect(fn () => MediaIngestionService::validateByteSize(524_288_001))
        ->toThrow(ValidationException::class);
});

test('unsupported media and extension mime mismatches are rejected before persistence', function (string $filename, string $mime): void {
    $this->actingAs(User::factory()->create());

    $response = $this->from(route('media.upload'))
        ->post(route('media.upload.store'), [
            'media_file' => UploadedFile::fake()->create($filename, 10, $mime),
            'upload_attempt_id' => (string) Str::uuid(),
        ]);

    $response->assertRedirect(route('media.upload'))
        ->assertSessionHasErrors('media_file');
    expect(MediaFile::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('media/.staging'))->toBe([]);
})->with([
    'unsupported extension' => ['notes.txt', 'text/plain'],
    'extension mime mismatch' => ['recording.mp3', 'video/mp4'],
]);

test('multiple files are rejected as one upload attempt', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->from(route('media.upload'))
        ->post(route('media.upload.store'), [
            'media_file' => [
                UploadedFile::fake()->create('one.mp3', 1, 'audio/mpeg'),
                UploadedFile::fake()->create('two.mp3', 1, 'audio/mpeg'),
            ],
            'upload_attempt_id' => (string) Str::uuid(),
        ]);

    $response->assertRedirect(route('media.upload'))
        ->assertSessionHasErrors('media_file');
    expect(MediaFile::query()->count())->toBe(0);
});

test('client checksum values cannot override the server-generated checksum', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('media.upload.store'), [
        'media_file' => UploadedFile::fake()->createWithContent('recording.mp3', 'same bytes')->mimeType('audio/mpeg'),
        'checksum_sha256' => str_repeat('a', 64),
        'upload_attempt_id' => (string) Str::uuid(),
    ])->assertRedirect();

    $mediaFile = MediaFile::query()->firstOrFail();

    expect($mediaFile->checksum_sha256)
        ->toBe(hash('sha256', 'same bytes'))
        ->not->toBe(str_repeat('a', 64));
});

test('identical content is allowed across separate intentional attempts', function () {
    $this->actingAs(User::factory()->create());
    $content = 'same content across separate attempts';

    foreach ([Str::uuid(), Str::uuid()] as $attemptId) {
        $this->post(route('media.upload.store'), [
            'media_file' => UploadedFile::fake()->createWithContent('same.mp3', $content)->mimeType('audio/mpeg'),
            'upload_attempt_id' => (string) $attemptId,
        ])->assertRedirect();
    }

    expect(MediaFile::query()->count())->toBe(2)
        ->and(MediaFile::query()->pluck('checksum_sha256')->unique()->count())->toBe(1);
});

test('retrying one upload attempt returns the committed media file without a second record', function () {
    $this->actingAs(User::factory()->create());
    $attemptId = (string) Str::uuid();

    $this->post(route('media.upload.store'), [
        'media_file' => UploadedFile::fake()->createWithContent('retry.mp3', 'retry content')->mimeType('audio/mpeg'),
        'upload_attempt_id' => $attemptId,
    ])->assertRedirect();

    $mediaFile = MediaFile::query()->firstOrFail();

    $this->withHeaders(['Accept' => 'application/json'])
        ->post(route('media.upload.store'), ['upload_attempt_id' => $attemptId])
        ->assertOk()
        ->assertJsonPath('redirect', route('media.show', $mediaFile));

    expect(MediaFile::query()->count())->toBe(1)
        ->and(MediaFile::query()->first()->storage_path)->toBe($mediaFile->storage_path);
});

test('one user cannot reuse another users upload attempt', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $attemptId = (string) Str::uuid();

    $this->actingAs($owner)->post(route('media.upload.store'), [
        'media_file' => UploadedFile::fake()->createWithContent('owned.mp3', 'owned content')->mimeType('audio/mpeg'),
        'upload_attempt_id' => $attemptId,
    ])->assertRedirect();

    $this->actingAs($otherUser)
        ->post(route('media.upload.store'), [
            'media_file' => UploadedFile::fake()->create('intrusion.mp3', 1, 'audio/mpeg'),
            'upload_attempt_id' => $attemptId,
        ])
        ->assertForbidden();

    expect(MediaFile::query()->count())->toBe(1);
});

test('folder association is limited to folders owned by the authenticated user', function () {
    $user = User::factory()->create();
    $foreignFolder = Folder::factory()->create();
    $this->actingAs($user);

    $this->from(route('media.upload'))
        ->post(route('media.upload.store'), [
            'media_file' => UploadedFile::fake()->create('recording.mp3', 1, 'audio/mpeg'),
            'folder_id' => $foreignFolder->id,
            'upload_attempt_id' => (string) Str::uuid(),
        ])
        ->assertRedirect(route('media.upload'))
        ->assertSessionHasErrors('folder_id');

    expect(MediaFile::query()->count())->toBe(0);
});

test('promotion failure compensation cleans staging and creates no media record', function () {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();

    // Capture the real (faked) disk before overriding the facade.
    $realDisk = Storage::disk('local');

    // Build a mock adapter that delegates most operations to the real fake
    // disk but throws when durable promotion (put on non-staging path) is attempted.
    $mockAdapter = Mockery::mock(FilesystemAdapter::class);
    $mockAdapter
        ->shouldReceive('put')
        ->once()
        ->andReturnUsing(function (string $path, $contents) use ($realDisk) {
            if (str_starts_with($path, 'media/') && ! str_contains($path, '.staging')) {
                throw new RuntimeException('Durable storage promotion failed.');
            }

            return $realDisk->put($path, $contents);
        });
    $mockAdapter
        ->shouldReceive('exists')
        ->andReturnUsing(fn (string $path) => $realDisk->exists($path));
    $mockAdapter
        ->shouldReceive('delete')
        ->andReturnUsing(fn (string $path) => $realDisk->delete($path));
    $mockAdapter
        ->shouldReceive('size')
        ->andReturnUsing(fn (string $path) => $realDisk->size($path));
    $mockAdapter
        ->shouldReceive('readStream')
        ->andReturnUsing(fn (string $path) => $realDisk->readStream($path));
    $mockAdapter
        ->shouldReceive('putFileAs')
        ->andReturnUsing(fn (string $directory, $file, ?string $name = null, array $options = []) => $realDisk->putFileAs($directory, $file, $name, $options));

    // Override Storage::disk() so MediaFile::storage() returns our mock.
    Storage::shouldReceive('disk')->andReturn($mockAdapter);

    $this->actingAs($user);

    $response = $this->post(route('media.upload.store'), [
        'media_file' => UploadedFile::fake()->createWithContent('meeting.mp3', str_repeat('x', 1024))->mimeType('audio/mpeg'),
        'upload_attempt_id' => $attemptId,
    ]);

    $response->assertRedirect()
        ->assertSessionHasErrors('media_file');

    // No committed MediaFile row
    expect(MediaFile::query()->count())->toBe(0);

    // Staging file was cleaned up by the compensation path.
    // Use the real disk reference directly since Storage::disk() now returns the mock.
    expect($realDisk->allFiles('media/.staging'))->toBe([]);
});

test('persistence failure compensation removes promoted durable object and staging', function () {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();

    $realDisk = Storage::disk('local');

    // Mock the storage adapter so durable promotion succeeds but we can track cleanup.
    $mockAdapter = Mockery::mock(FilesystemAdapter::class);
    $mockAdapter->shouldReceive('putFileAs')->andReturnUsing(
        fn (string $directory, $file, ?string $name = null, array $options = []) => $realDisk->putFileAs($directory, $file, $name, $options)
    );
    $mockAdapter->shouldReceive('size')->andReturnUsing(fn (string $path) => $realDisk->size($path));
    $mockAdapter->shouldReceive('readStream')->andReturnUsing(fn (string $path) => $realDisk->readStream($path));
    $mockAdapter->shouldReceive('put')->andReturnUsing(fn (string $path, $contents) => $realDisk->put($path, $contents));
    $mockAdapter->shouldReceive('exists')->andReturnUsing(fn (string $path) => $realDisk->exists($path));
    $mockAdapter->shouldReceive('delete')->andReturnUsing(fn (string $path) => $realDisk->delete($path));
    Storage::shouldReceive('disk')->andReturn($mockAdapter);

    // Force the database transaction to throw, simulating persistence failure
    // after durable promotion has already succeeded.
    $database = Mockery::mock(DatabaseManager::class);
    $database->shouldReceive('transaction')->once()->andThrow(new RuntimeException('Database persistence failed.'));
    $this->app->instance(DatabaseManager::class, $database);

    $service = app(MediaIngestionService::class);
    $file = UploadedFile::fake()->createWithContent('recording.mp3', str_repeat('y', 1024))->mimeType('audio/mpeg');

    try {
        $service->ingest($user, $file, $attemptId);
        $this->fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Database persistence failed.');
    }

    // No committed MediaFile row
    expect(MediaFile::query()->count())->toBe(0);

    // Staging was cleaned up by the compensation path
    expect($realDisk->allFiles('media/.staging'))->toBe([]);

    // Durable file was cleaned up by deleteOrLog
    // (no durable files should remain except possibly staging leftovers)
    $durableFiles = array_filter($realDisk->allFiles('media'), fn ($path) => ! str_contains($path, '.staging'));
    expect($durableFiles)->toBe([]);
});

test('ambiguous duplicate key retry returns existing media without creating a second record', function () {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();

    $realDisk = Storage::disk('local');

    // Mock the storage adapter to delegate file operations to the real fake disk.
    $mockAdapter = Mockery::mock(FilesystemAdapter::class);
    $mockAdapter->shouldReceive('putFileAs')->andReturnUsing(
        fn (string $directory, $file, ?string $name = null, array $options = []) => $realDisk->putFileAs($directory, $file, $name, $options)
    );
    $mockAdapter->shouldReceive('size')->andReturnUsing(fn (string $path) => $realDisk->size($path));
    $mockAdapter->shouldReceive('readStream')->andReturnUsing(fn (string $path) => $realDisk->readStream($path));
    $mockAdapter->shouldReceive('put')->andReturnUsing(fn (string $path, $contents) => $realDisk->put($path, $contents));
    $mockAdapter->shouldReceive('exists')->andReturnUsing(fn (string $path) => $realDisk->exists($path));
    $mockAdapter->shouldReceive('delete')->andReturnUsing(fn (string $path) => $realDisk->delete($path));
    Storage::shouldReceive('disk')->andReturn($mockAdapter);

    // Simulate a race condition: findByAttempt returns null, but by the time
    // the transaction runs, a concurrent request has committed the same
    // (user_id, upload_attempt_id). The transaction throws a unique-violation.
    $conflictingRow = null;
    $database = Mockery::mock(DatabaseManager::class);
    $database->shouldReceive('transaction')->once()->andReturnUsing(function ($closure) use ($user, $attemptId, &$conflictingRow) {
        // Insert a conflicting row before the closure runs, simulating the race.
        $conflictingRow = MediaFile::factory()->create([
            'user_id' => $user->id,
            'upload_attempt_id' => $attemptId,
        ]);

        // The closure's save() will throw a unique-violation.
        return $closure();
    });
    $this->app->instance(DatabaseManager::class, $database);

    $service = app(MediaIngestionService::class);
    $file = UploadedFile::fake()->createWithContent('retry.mp3', str_repeat('z', 1024))->mimeType('audio/mpeg');

    $result = $service->ingest($user, $file, $attemptId);

    // The service recovered by returning the existing record.
    expect($result->id)->toBe($conflictingRow->id)
        ->and($result->upload_attempt_id)->toBe($attemptId);

    // Exactly one record — the one inserted by the simulated race.
    expect(MediaFile::query()->count())->toBe(1);

    // The duplicate durable file was cleaned up by the inner catch.
    expect($realDisk->allFiles('media/.staging'))->toBe([]);

    // No transcription or processing job side effects.
    expect(Transcription::query()->count())->toBe(0)
        ->and(ProcessingJob::query()->count())->toBe(0);
});

test('missing file submission is rejected with validation error', function () {
    $this->actingAs(User::factory()->create());

    $this->from(route('media.upload'))
        ->post(route('media.upload.store'), [
            'upload_attempt_id' => (string) Str::uuid(),
        ])
        ->assertRedirect(route('media.upload'))
        ->assertSessionHasErrors('media_file');

    expect(MediaFile::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles('media/.staging'))->toBe([]);
});

test('guest cannot access upload screen or store route', function () {
    $this->get(route('media.upload'))->assertRedirect(route('login'));
    $this->post(route('media.upload.store'), [])->assertRedirect(route('login'));
    expect(MediaFile::query()->count())->toBe(0);
});
