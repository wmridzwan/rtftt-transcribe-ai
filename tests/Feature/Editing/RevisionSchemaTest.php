<?php

use App\Models\Transcription;
use App\Models\TranscriptRevisionModel;
use App\Models\TranscriptRevisionSegment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EditingPersistenceFixtures;

it('creates the additive revision schema with named constraints and a nullable active pointer', function () {
    expect(Schema::hasTable('transcript_revisions'))->toBeTrue()
        ->and(Schema::hasTable('transcript_revision_segments'))->toBeTrue()
        ->and(Schema::hasColumn('transcriptions', 'active_revision_id'))->toBeTrue();

    foreach (['id', 'transcription_id', 'version', 'parent_revision_id', 'created_by', 'created_at', 'updated_at'] as $column) {
        expect(Schema::hasColumn('transcript_revisions', $column))->toBeTrue();
    }

    foreach (['revision_id', 'segment_key', 'position', 'start_seconds', 'end_seconds', 'text', 'language'] as $column) {
        expect(Schema::hasColumn('transcript_revision_segments', $column))->toBeTrue();
    }

    $transcription = Transcription::factory()->completed()->create();

    expect($transcription->fresh()->active_revision_id)->toBeNull();
});

it('enforces unique (transcription_id, version)', function () {
    $transcription = Transcription::factory()->completed()->create();

    TranscriptRevisionModel::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'version' => 1,
    ]);

    expect(fn () => TranscriptRevisionModel::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'version' => 1,
    ]))->toThrow(QueryException::class);
});

it('allows the same version number in different transcriptions', function () {
    $a = Transcription::factory()->completed()->create();
    $b = Transcription::factory()->completed()->create();

    TranscriptRevisionModel::factory()->create(['transcription_id' => $a->getKey(), 'version' => 1]);
    TranscriptRevisionModel::factory()->create(['transcription_id' => $b->getKey(), 'version' => 1]);

    expect(TranscriptRevisionModel::query()->where('version', 1)->count())->toBe(2);
});

it('enforces unique (revision_id, segment_key) and (revision_id, position)', function () {
    $revision = TranscriptRevisionModel::factory()->create();

    TranscriptRevisionSegment::factory()->create([
        'revision_id' => $revision->getKey(),
        'segment_key' => 'seg-0',
        'position' => 0,
    ]);

    expect(fn () => TranscriptRevisionSegment::factory()->create([
        'revision_id' => $revision->getKey(),
        'segment_key' => 'seg-0',
        'position' => 1,
    ]))->toThrow(QueryException::class);

    expect(fn () => TranscriptRevisionSegment::factory()->create([
        'revision_id' => $revision->getKey(),
        'segment_key' => 'seg-1',
        'position' => 0,
    ]))->toThrow(QueryException::class);
});

it('cascades revision rows and segments when the transcription is deleted', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();

    $revision = TranscriptRevisionModel::factory()->create(['transcription_id' => $transcription->getKey(), 'version' => 1]);
    TranscriptRevisionSegment::factory()->create(['revision_id' => $revision->getKey()]);

    $transcription->delete();

    expect(TranscriptRevisionModel::query()->count())->toBe(0)
        ->and(TranscriptRevisionSegment::query()->count())->toBe(0);
});

it('leaves the machine transcription and segment tables semantically untouched', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();

    $before = $transcription->segments()->get()->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    expect($before)->toHaveCount(2)
        ->and(Schema::hasColumn('transcription_segments', 'segment_index'))->toBeTrue()
        ->and(Schema::hasColumn('transcription_segments', 'start_seconds'))->toBeTrue()
        ->and(Schema::hasColumn('transcription_segments', 'text'))->toBeTrue();

    // No revision-layer column leaked onto the immutable machine table.
    expect(Schema::hasColumn('transcription_segments', 'revision_id'))->toBeFalse()
        ->and(Schema::hasColumn('transcriptions', 'version'))->toBeFalse();
});
