<?php

use App\Models\MediaFile;
use App\Models\RetentionPurgeAudit;
use App\Models\Transcription;
use App\Models\User;
use App\Retention\RetentionPurge;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * P7-011: audit completeness, disclosure, purged-state behavior, and
 * generation safety. Every decision outcome is accounted; purged
 * sources report 410 (never bare 404); history and text exports keep
 * working; backup generations are untouched.
 */

beforeEach(function () {
    Storage::fake('local');
});

it('accounts every decision outcome in the audit ledger', function (): void {
    $user = User::factory()->create();

    $old = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'completed_at' => now()->subDays(45)->toDateTimeString(),
    ]);
    Storage::disk(config('media.storage_disk'))->put($old->mediaFile->storage_path, 'BYTES');

    $recent = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'completed_at' => now()->subDays(5)->toDateTimeString(),
    ]);
    Storage::disk(config('media.storage_disk'))->put($recent->mediaFile->storage_path, 'BYTES');

    $result = RetentionPurge::run();

    $ledger = RetentionPurgeAudit::query()->where('run_id', $result['run_id'])
        ->pluck('outcome', 'purgeable_id')->all();

    expect($ledger[$old->media_file_id] ?? null)->toBe('purged')
        ->and(array_sum($result['outcomes']))->toBe(RetentionPurgeAudit::query()->where('run_id', $result['run_id'])->count());
});

it('reports purged sources as 410 with retention disclosure', function (): void {
    $user = User::factory()->create();
    $media = MediaFile::factory()->create(['user_id' => $user->id]);

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $media->id,
        'completed_at' => now()->subDays(45)->toDateTimeString(),
    ]);
    Storage::disk(config('media.storage_disk'))->put($media->storage_path, 'BYTES');

    RetentionPurge::run();

    // User-facing behavior: 410 Gone (never a bare 404).
    $download = $this->actingAs($user)->get(route('media.download', ['mediaFile' => $media->id]));
    $download->assertStatus(410);

    $stream = $this->actingAs($user)->get(route('media.stream', ['mediaFile' => $media->uuid]));
    $stream->assertStatus(410);

    // Disclosure accuracy: the abort message names the retention policy.
    $this->withoutExceptionHandling();

    try {
        $this->actingAs($user)->get(route('media.download', ['mediaFile' => $media->id]));
        $this->fail('Purged download must abort.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(410)
            ->and($exception->getMessage())->toContain('retention');
    }
});

it('keeps transcript history and text exports working after purge', function (): void {
    $user = User::factory()->create();

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'completed_at' => now()->subDays(45)->toDateTimeString(),
    ]);
    Storage::disk(config('media.storage_disk'))->put($transcription->mediaFile->storage_path, 'BYTES');

    RetentionPurge::run();

    // History row intact (not orphaned, not deleted).
    expect(Transcription::query()->find($transcription->id))->not->toBeNull();

    // Text export derives from persisted segments — still served.
    $export = $this->actingAs($user)->get(route('transcriptions.export.txt', ['transcription' => $transcription->id]));
    $export->assertOk();
});

it('publishes the user-facing retention disclosure', function (): void {
    $path = base_path('docs/RETENTION-POLICY.md');

    expect(is_file($path))->toBeTrue();

    $copy = (string) file_get_contents($path);

    expect($copy)->toContain('30 days')
        ->and($copy)->toContain('never automatically deleted')
        ->and($copy)->toContain('410');
});

it('leaves backup generations and their manifest untouched', function (): void {
    $originalTarget = config('backup.target');
    $target = sys_get_temp_dir().DIRECTORY_SEPARATOR.'p7011-gen-'.uniqid();
    mkdir($target, 0750, true);

    $db = tempnam(sys_get_temp_dir(), 'p7011-db-').'.sqlite';
    $pdo = new PDO('sqlite:'.$db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE migrations (migration VARCHAR(255), batch INT)');
    $pdo->exec("INSERT INTO migrations VALUES ('2026_01_01_000001_probe.php', 1)");
    unset($pdo);

    $originalDb = config('database.connections.sqlite.database');
    config()->set('database.connections.sqlite.database', $db);
    config()->set('backup.target', $target);

    try {
        expect(Artisan::call('backup:run', ['--driver' => 'sqlite']))->toBe(0);

        $manifests = glob($target.'/*/manifest.json') ?: [];
        expect($manifests)->not->toBeEmpty();
        $manifestBefore = (string) file_get_contents($manifests[0]);

        $user = User::factory()->create();
        $transcription = Transcription::factory()->completed()->create([
            'user_id' => $user->id,
            'completed_at' => now()->subDays(45)->toDateTimeString(),
        ]);
        Storage::disk(config('media.storage_disk'))->put($transcription->mediaFile->storage_path, 'BYTES');

        RetentionPurge::run();

        $manifestsAfter = glob($target.'/*/manifest.json') ?: [];
        expect($manifestsAfter)->not->toBeEmpty();

        $manifestAfter = (string) file_get_contents($manifestsAfter[0]);

        expect($manifestAfter)->toBe($manifestBefore);
    } finally {
        config()->set('database.connections.sqlite.database', $originalDb);
        config()->set('backup.target', $originalTarget);
    }

    @unlink($db);
});

it('reports retention health through deployment:verify and diagnostics', function (): void {
    $this->artisan('deployment:verify')
        ->expectsOutputToContain('Retention:')
        ->assertSuccessful();

    $this->artisan('observability:diagnostics')
        ->expectsOutputToContain('Retention schedule:')
        ->expectsOutputToContain('Retention ledger:');
});
