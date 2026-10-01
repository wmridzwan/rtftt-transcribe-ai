<?php

use App\Actions\TranscriptionOrchestrator;
use App\Enums\MediaStatus;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Http\Controllers\DemoTranscriptionController;
use App\Jobs\ProcessTranscription;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\User;
use App\Transcription\LanguageIdentifier;
use App\Transcription\NormalizedTranscript;
use App\Transcription\TranscriptionProvider;
use App\Transcription\TranscriptSegmentData;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\RecordingTranscriptionProvider;

beforeEach(function (): void {
    Storage::fake('local');
});

function p7Corr01Media(?User $owner = null, array $overrides = [], bool $withBytes = true): MediaFile
{
    $media = MediaFile::factory()->create(array_merge([
        'user_id' => ($owner ?? User::factory()->create())->id,
        'storage_path' => 'media/'.Str::uuid().'.mp3',
        'extension' => 'mp3',
        'mime_type' => 'audio/mpeg',
        'status' => MediaStatus::Uploaded,
    ], $overrides));

    if ($withBytes) {
        Storage::disk((string) config('media.storage_disk'))->put($media->storage_path, 'audio-bytes');
    }

    return $media;
}

test('upload alone never creates a transcription, attempt, or queue dispatch', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    $this->post(route('media.upload.store'), [
        'media_file' => UploadedFile::fake()->createWithContent('Meeting.mp3', str_repeat('a', 1024))->mimeType('audio/mpeg'),
        'upload_attempt_id' => (string) Str::uuid(),
    ]);

    expect(MediaFile::query()->count())->toBe(1)
        ->and(MediaFile::query()->first()->status)->toBe(MediaStatus::Uploaded)
        ->and(Transcription::query()->count())->toBe(0)
        ->and(ProcessingJob::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('media detail exposes Start Transcription for an eligible owned media file only', function () {
    $owner = User::factory()->create();
    $media = p7Corr01Media($owner);

    $this->actingAs($owner)->get(route('media.show', $media))
        ->assertOk()
        ->assertSee('Start Transcription')
        ->assertSee(route('media.transcriptions.store', $media), false);

    $failed = p7Corr01Media($owner, ['status' => MediaStatus::Failed]);
    $this->get(route('media.show', $failed))->assertOk()->assertDontSee('Start Transcription');

    $noBytes = p7Corr01Media($owner, [], withBytes: false);
    $this->get(route('media.show', $noBytes))->assertOk()->assertDontSee('Start Transcription');
});

test('viewing media detail never starts processing', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $media = p7Corr01Media($owner);

    $this->actingAs($owner)->get(route('media.show', $media))->assertOk();

    expect(Transcription::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('starting transcription reuses the real media and queues canonical work through the orchestrator', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $media = p7Corr01Media($owner);
    $mediaCount = MediaFile::query()->count();

    $spy = Mockery::spy(app(TranscriptionOrchestrator::class));
    $this->app->instance(TranscriptionOrchestrator::class, $spy);

    $response = $this->actingAs($owner)->post(route('media.transcriptions.store', $media));

    $transcription = Transcription::query()->firstOrFail();
    $attempt = ProcessingJob::query()->firstOrFail();

    $response->assertRedirect(route('transcriptions.show', $transcription));
    $spy->shouldHaveReceived('request')->once();

    expect(MediaFile::query()->count())->toBe($mediaCount)
        ->and($transcription->media_file_id)->toBe($media->id)
        ->and($transcription->user_id)->toBe($owner->id)
        ->and($transcription->title)->toBe($media->display_name)
        ->and($transcription->language)->toBeNull()
        ->and($transcription->model)->toBe(config('transcription.model'))
        ->and($transcription->status)->toBe(TranscriptionStatus::Queued)
        ->and($attempt->transcription_id)->toBe($transcription->id)
        ->and($attempt->status)->toBe(ProcessingStatus::Queued);

    Queue::assertPushed(ProcessTranscription::class, 1);
    Queue::assertPushedOn('transcription', ProcessTranscription::class);
    Queue::assertPushed(ProcessTranscription::class, fn (ProcessTranscription $job): bool => $job->transcriptionId === $transcription->id
        && $job->processingAttemptId === $attempt->id);
});

test('a non-owner cannot start transcription and nothing is created', function () {
    Queue::fake();
    $media = p7Corr01Media();

    $this->actingAs(User::factory()->create())
        ->post(route('media.transcriptions.store', $media))
        ->assertForbidden();

    expect(Transcription::query()->count())->toBe(0)
        ->and(ProcessingJob::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('an admin may start transcription for another user media per the existing policy', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $media = p7Corr01Media($owner);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('media.transcriptions.store', $media))
        ->assertRedirect();

    $transcription = Transcription::query()->firstOrFail();

    expect($transcription->user_id)->toBe($owner->id)
        ->and($transcription->media_file_id)->toBe($media->id);
    Queue::assertPushedOn('transcription', ProcessTranscription::class);
});

test('guests are redirected to login', function () {
    $media = p7Corr01Media();

    $this->post(route('media.transcriptions.store', $media))->assertRedirect(route('login'));
});

test('non-processable media is refused safely without creating work', function (array $overrides, bool $withBytes) {
    Queue::fake();
    $owner = User::factory()->create();
    $media = p7Corr01Media($owner, $overrides, $withBytes);

    $this->actingAs($owner)
        ->post(route('media.transcriptions.store', $media))
        ->assertRedirect(route('media.show', $media))
        ->assertSessionHas('error', 'This media file cannot be transcribed in its current state.');

    expect(Transcription::query()->count())->toBe(0)
        ->and(ProcessingJob::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'failed' => [['status' => MediaStatus::Failed], true],
    'processing' => [['status' => MediaStatus::Processing], true],
    'deleted' => [['status' => MediaStatus::Deleted], true],
    'purged' => [['purged_at' => now()], true],
    'infected verdict' => [['scan_verdict' => 'infected'], true],
    'bytes missing' => [[], false],
]);

test('a double submission converges on one active transcription and one attempt', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $media = p7Corr01Media($owner);

    $first = $this->actingAs($owner)->post(route('media.transcriptions.store', $media));
    $second = $this->actingAs($owner)->post(route('media.transcriptions.store', $media));

    expect(Transcription::query()->count())->toBe(1)
        ->and(ProcessingJob::query()->count())->toBe(1);

    $transcription = Transcription::query()->firstOrFail();
    $first->assertRedirect(route('transcriptions.show', $transcription));
    $second->assertRedirect(route('transcriptions.show', $transcription));
});

test('an already active transcription for the media is reused, not duplicated', function (TranscriptionStatus $status) {
    Queue::fake();
    $owner = User::factory()->create();
    $media = p7Corr01Media($owner);
    $existing = Transcription::factory()->create([
        'user_id' => $owner->id,
        'media_file_id' => $media->id,
        'status' => $status,
    ]);

    $this->actingAs($owner)
        ->post(route('media.transcriptions.store', $media))
        ->assertRedirect(route('transcriptions.show', $existing));

    expect(Transcription::query()->count())->toBe(1);
})->with([
    'draft' => TranscriptionStatus::Draft,
    'queued' => TranscriptionStatus::Queued,
    'preparing' => TranscriptionStatus::Preparing,
    'transcribing' => TranscriptionStatus::Transcribing,
]);

test('deliberate re-transcription after a terminal attempt creates a new transcription', function (TranscriptionStatus $status) {
    Queue::fake();
    $owner = User::factory()->create();
    $media = p7Corr01Media($owner);
    $previous = Transcription::factory()->create([
        'user_id' => $owner->id,
        'media_file_id' => $media->id,
        'status' => $status,
    ]);

    $this->actingAs($owner)->post(route('media.transcriptions.store', $media))->assertRedirect();

    $latest = Transcription::query()->orderByDesc('id')->firstOrFail();

    expect(Transcription::query()->count())->toBe(2)
        ->and($latest->id)->not->toBe($previous->id)
        ->and($latest->status)->toBe(TranscriptionStatus::Queued);
})->with([
    'completed' => TranscriptionStatus::Completed,
    'failed' => TranscriptionStatus::Failed,
    'cancelled' => TranscriptionStatus::Cancelled,
]);

test('media detail hides Start Transcription while an active transcription exists', function () {
    $owner = User::factory()->create();
    $media = p7Corr01Media($owner);
    Transcription::factory()->queued()->create(['user_id' => $owner->id, 'media_file_id' => $media->id]);

    $this->actingAs($owner)->get(route('media.show', $media))->assertOk()->assertDontSee('Start Transcription');
});

test('real media through the initiation bridge and queue job completes with persisted segments', function () {
    $owner = User::factory()->create();
    $media = p7Corr01Media($owner);

    $provider = new RecordingTranscriptionProvider(new NormalizedTranscript(
        text: 'Hello world.',
        detectedLanguage: LanguageIdentifier::English,
        durationSeconds: 4.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 2.0, 'Hello', LanguageIdentifier::English),
            new TranscriptSegmentData(1, 2.0, 4.0, 'world.', LanguageIdentifier::English),
        ],
    ));
    $this->app->instance(TranscriptionProvider::class, $provider);

    // QUEUE_CONNECTION=sync in the test environment: the dispatched job runs
    // through the real ProcessTranscription handler with the fake provider.
    $this->actingAs($owner)->post(route('media.transcriptions.store', $media))->assertRedirect();

    $transcription = Transcription::query()->firstOrFail()->refresh();

    expect($provider->callCount())->toBe(1)
        ->and($provider->invocations[0]->media->storageKey)->toBe($media->storage_path)
        ->and($transcription->media_file_id)->toBe($media->id)
        ->and($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->full_text)->toBe('Hello world.')
        ->and($transcription->segments()->count())->toBe(2)
        ->and(ProcessingJob::query()->firstOrFail()->status)->toBe(ProcessingStatus::Completed)
        ->and(MediaFile::query()->count())->toBe(1);
});

test('the demo transcription controller is unavailable in production', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->actingAs(User::factory()->create());
    $this->app['env'] = 'production';

    $this->post(route('transcriptions.store'), ['title' => 'Fake'])->assertNotFound();

    expect(Transcription::query()->count())->toBe(0)
        ->and(MediaFile::query()->count())->toBe(0);
});

test('production does not register the demo transcription routes', function () {
    $this->app['env'] = 'production';
    $router = app('router');
    $router->setRoutes(new RouteCollection);

    require base_path('routes/web.php');

    $routes = $router->getRoutes();
    $routes->refreshNameLookups();

    expect($routes->getByName('transcriptions.store'))->toBeNull()
        ->and($routes->getByName('transcriptions.create'))->not->toBeNull()
        ->and($routes->getByName('transcriptions.create')->uri())->toBe('transcriptions/create')
        ->and($routes->getByName('media.transcriptions.store'))->not->toBeNull();
});

test('the demo controller still guards production when invoked directly', function () {
    $this->app['env'] = 'production';
    $request = Request::create('/transcriptions', 'POST', ['title' => 'x']);

    expect(fn () => app(DemoTranscriptionController::class)->store($request))
        ->toThrow(HttpException::class);
});
