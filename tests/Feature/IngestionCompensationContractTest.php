<?php

use App\Actions\MediaIngestionService;
use App\Models\MediaFile;
use App\Models\StagingClaim;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

beforeEach(function (): void {
    Storage::fake('local');
});

it('returns the existing media record when completion is retried for one upload attempt', function (): void {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $this->actingAs($user);
    $payload = ['media_file' => UploadedFile::fake()->createWithContent('retry.mp3', 'retry')->mimeType('audio/mpeg'), 'upload_attempt_id' => $attemptId];
    $this->post(route('media.upload.store'), $payload)->assertRedirect();
    $mediaFile = MediaFile::query()->firstOrFail();
    $this->postJson(route('media.upload.store'), ['upload_attempt_id' => $attemptId])->assertOk()->assertJsonPath('redirect', route('media.show', $mediaFile));
    expect(MediaFile::query()->count())->toBe(1);
});

it('does not promote another object or insert another record after a response retry', function (): void {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $this->actingAs($user);
    $this->post(route('media.upload.store'), ['media_file' => UploadedFile::fake()->createWithContent('retry.mp3', 'same')->mimeType('audio/mpeg'), 'upload_attempt_id' => $attemptId])->assertRedirect();
    $before = MediaFile::query()->firstOrFail();
    $this->postJson(route('media.upload.store'), ['upload_attempt_id' => $attemptId])->assertOk();
    expect(MediaFile::query()->count())->toBe(1)->and(MediaFile::query()->first()->storage_path)->toBe($before->storage_path)->and(Storage::disk('local')->allFiles('media/.staging'))->toBe([]);
});

it('allows identical content in separate intentional upload attempts', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    foreach ([Str::uuid(), Str::uuid()] as $attemptId) {
        $this->post(route('media.upload.store'), ['media_file' => UploadedFile::fake()->createWithContent('same.mp3', 'same bytes')->mimeType('audio/mpeg'), 'upload_attempt_id' => (string) $attemptId])->assertRedirect();
    }
    expect(MediaFile::query()->count())->toBe(2);
});

it('does not allow one owner to complete another owners upload attempt', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $this->actingAs($owner)->post(route('media.upload.store'), ['media_file' => UploadedFile::fake()->createWithContent('owned.mp3', 'owned')->mimeType('audio/mpeg'), 'upload_attempt_id' => $attemptId])->assertRedirect();
    $this->actingAs($other)->post(route('media.upload.store'), ['media_file' => UploadedFile::fake()->createWithContent('intrusion.mp3', 'intrusion')->mimeType('audio/mpeg'), 'upload_attempt_id' => $attemptId])->assertForbidden();
    expect(MediaFile::query()->count())->toBe(1);
});

function delegatedDisk($realDisk, bool $failPromotion = false): FilesystemAdapter
{
    $adapter = Mockery::mock(FilesystemAdapter::class);
    $adapter->shouldReceive('putFileAs')->andReturnUsing(fn (string $directory, $file, ?string $name = null, array $options = []) => $realDisk->putFileAs($directory, $file, $name, $options));
    $adapter->shouldReceive('size')->andReturnUsing(fn (string $path) => $realDisk->size($path));
    $adapter->shouldReceive('readStream')->andReturnUsing(fn (string $path) => $realDisk->readStream($path));
    $adapter->shouldReceive('put')->andReturnUsing(function (string $path, $contents) use ($realDisk, $failPromotion) {
        if ($failPromotion && ! str_contains($path, '.staging')) {
            throw new RuntimeException('promotion failed');
        }

        return $realDisk->put($path, $contents);
    });
    $adapter->shouldReceive('exists')->andReturnUsing(fn (string $path) => $realDisk->exists($path));
    $adapter->shouldReceive('delete')->andReturnUsing(fn (string $path) => $realDisk->delete($path));

    return $adapter;
}

it('compensates a promoted object when media persistence fails', function (): void {
    $user = User::factory()->create();
    $realDisk = Storage::disk('local');
    Storage::shouldReceive('disk')->andReturn(delegatedDisk($realDisk));
    $database = Mockery::mock(DatabaseManager::class);
    $database->shouldReceive('transaction')->once()->andThrow(new RuntimeException('persistence failed'));
    $this->app->instance(DatabaseManager::class, $database);
    expect(fn () => app(MediaIngestionService::class)->ingest($user, UploadedFile::fake()->createWithContent('failure.mp3', 'bytes')->mimeType('audio/mpeg'), (string) Str::uuid()))->toThrow(RuntimeException::class);
    expect(MediaFile::query()->count())->toBe(0)->and($realDisk->allFiles('media/.staging'))->toBe([])->and(array_filter($realDisk->allFiles('media'), fn (string $path): bool => ! str_contains($path, '.staging')))->toBe([]);
});

it('does not create a media record when a promotion failure leaves an orphan candidate', function (): void {
    $user = User::factory()->create();
    $realDisk = Storage::disk('local');
    $adapter = delegatedDisk($realDisk, true);
    Storage::shouldReceive('disk')->andReturn($adapter);
    expect(fn () => app(MediaIngestionService::class)->ingest($user, UploadedFile::fake()->createWithContent('failure.mp3', 'bytes')->mimeType('audio/mpeg'), (string) Str::uuid()))->toThrow(RuntimeException::class);
    expect(MediaFile::query()->count())->toBe(0)->and($realDisk->allFiles('media/.staging'))->toBe([]);
});

it('resolves an ambiguous database result by attempt identity before replaying ingestion', function (): void {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $realDisk = Storage::disk('local');
    Storage::shouldReceive('disk')->andReturn(delegatedDisk($realDisk));
    $database = Mockery::mock(DatabaseManager::class);
    $winner = null;
    $database->shouldReceive('transaction')->once()->andReturnUsing(function (Closure $closure) use ($user, $attemptId, &$winner) {
        $winner = MediaFile::factory()->create(['user_id' => $user->id, 'upload_attempt_id' => $attemptId]);

        return $closure();
    });
    $this->app->instance(DatabaseManager::class, $database);
    $result = app(MediaIngestionService::class)->ingest($user, UploadedFile::fake()->createWithContent('race.mp3', 'bytes')->mimeType('audio/mpeg'), $attemptId);
    expect($result->id)->toBe($winner->id)->and(MediaFile::query()->count())->toBe(1)->and($realDisk->allFiles('media/.staging'))->toBe([])->and(array_filter($realDisk->allFiles('media'), fn (string $path): bool => ! str_contains($path, '.staging')))->toBe([]);
});

it('defers staging cleanup while a retry owns the active attempt claim', function (): void {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $path = "media/.staging/{$user->id}/{$attemptId}/retry.mp3";
    Storage::disk('local')->put($path, 'active bytes');
    StagingClaim::factory()->create(['user_id' => $user->id, 'upload_attempt_id' => $attemptId, 'staging_path' => $path, 'expires_at' => now()->addHour()]);
    Artisan::call('media:cleanup-staging');
    Storage::disk('local')->assertExists($path);
});

it('requires restaging with the same attempt identity after cleanup wins a race', function (): void {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $path = "media/.staging/{$user->id}/{$attemptId}/old.mp3";
    Storage::disk('local')->put($path, 'old bytes');
    StagingClaim::factory()->create(['user_id' => $user->id, 'upload_attempt_id' => $attemptId, 'staging_path' => $path, 'expires_at' => now()->subHour()]);
    touch(Storage::disk('local')->path($path), now()->subHours(48)->timestamp);
    touch(dirname(Storage::disk('local')->path($path)), now()->subHours(48)->timestamp);
    Artisan::call('media:cleanup-staging');
    Storage::disk('local')->assertMissing($path);
    $media = app(MediaIngestionService::class)->ingest($user, UploadedFile::fake()->createWithContent('retry.mp3', 'new bytes')->mimeType('audio/mpeg'), $attemptId);
    expect($media->upload_attempt_id)->toBe($attemptId)->and(MediaFile::query()->count())->toBe(1);
});

it('does not adopt a durable orphan into a media record', function (): void {
    $path = 'media/orphan/orphan.mp3';
    Storage::disk('local')->put($path, 'orphan bytes');
    Artisan::call('media:cleanup-staging');
    Storage::disk('local')->assertExists($path);
    expect(MediaFile::query()->count())->toBe(0);
});

it('defers young or actively claimed durable orphans for later reconciliation', function (): void {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $path = "media/{$attemptId}/orphan.mp3";
    Storage::disk('local')->put($path, 'orphan bytes');
    StagingClaim::factory()->create(['user_id' => $user->id, 'upload_attempt_id' => $attemptId, 'staging_path' => "media/.staging/{$user->id}/{$attemptId}/orphan.mp3", 'expires_at' => now()->addHour()]);
    Artisan::call('media:cleanup-staging');
    Storage::disk('local')->assertExists($path);
    expect(MediaFile::query()->count())->toBe(0);
});
