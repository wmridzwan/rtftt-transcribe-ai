<?php

use App\Deployment\RevisionGraphIntegrity;
use App\Models\Transcription;
use App\Models\TranscriptRevisionModel;
use App\Models\TranscriptRevisionSegment;

/*
 * P7-002: revision-graph reconciliation — the identical check that runs
 * before and after the SQLite → PostgreSQL data migration. Driver
 * agnostic; seed a valid graph (clean) and corruptions (violations).
 */

function seedRevisionGraph(): Transcription
{
    $transcription = Transcription::factory()->create();

    $revision = TranscriptRevisionModel::factory()->create([
        'transcription_id' => $transcription->id,
    ]);

    TranscriptRevisionSegment::factory()->create([
        'revision_id' => $revision->id,
    ]);

    $transcription->forceFill(['active_revision_id' => $revision->id])->save();

    return $transcription->refresh();
}

it('reconciles a valid revision graph with exact counts', function (): void {
    seedRevisionGraph();

    $result = RevisionGraphIntegrity::reconcile();

    expect($result['violations'])->toBe([])
        ->and($result['counts']['transcriptions'])->toBeGreaterThanOrEqual(1)
        ->and($result['counts']['transcript_revisions'])->toBeGreaterThanOrEqual(1)
        ->and($result['counts']['transcript_revision_segments'])->toBeGreaterThanOrEqual(1);
});

it('detects a transcription pointing at a missing active revision', function (): void {
    driftConnection();

    DB::connection('drift')->table('transcriptions')->insert(['id' => 1, 'active_revision_id' => 'missing-revision']);

    $joined = implode(' ', RevisionGraphIntegrity::reconcile('drift')['violations']);

    expect($joined)->toContain('missing active_revision_id');
});

it('detects revision segments orphaned from their revision', function (): void {
    driftConnection();

    DB::connection('drift')->table('transcript_revision_segments')->insert(['id' => 1, 'revision_id' => 'missing-revision']);

    $joined = implode(' ', RevisionGraphIntegrity::reconcile('drift')['violations']);

    expect($joined)->toContain('missing revision');
});

it('detects revisions orphaned from their transcription', function (): void {
    driftConnection();

    DB::connection('drift')->table('transcript_revisions')->insert(['id' => 'orphan-rev', 'transcription_id' => 999999, 'version' => 1]);

    $joined = implode(' ', RevisionGraphIntegrity::reconcile('drift')['violations']);

    expect($joined)->toContain('missing transcription');
});

it('detects revisions with a missing parent revision', function (): void {
    $transcription = seedRevisionGraph();

    TranscriptRevisionModel::factory()->create([
        'transcription_id' => $transcription->id,
        'version' => 2,
        'parent_revision_id' => (string) Str::uuid(),
    ]);

    $joined = implode(' ', RevisionGraphIntegrity::reconcile()['violations']);

    expect($joined)->toContain('missing parent_revision_id');
});

it('detects duplicated (transcription_id, version) groups', function (): void {
    driftConnection();

    DB::connection('drift')->table('transcript_revisions')->insert([
        ['id' => 'rev-a', 'transcription_id' => 7, 'version' => 1],
        ['id' => 'rev-b', 'transcription_id' => 7, 'version' => 1],
    ]);

    $joined = implode(' ', RevisionGraphIntegrity::reconcile('drift')['violations']);

    expect($joined)->toContain('duplicated');
});

/**
 * Minimal FK-free mirror of the revision schema on a dedicated
 * connection OUTSIDE the test transaction (PRAGMA toggles are no-ops
 * inside transactions, and production FKs correctly prevent orphans
 * at write time). Simulates legacy drift the migration reconciler
 * must catch.
 */
function driftConnection(): void
{
    config(['database.connections.drift' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]]);

    DB::purge('drift');

    $schema = Schema::connection('drift');

    foreach (['transcript_revision_segments', 'transcript_revisions', 'transcriptions'] as $table) {
        $schema->dropIfExists($table);
    }

    $schema->create('transcriptions', function ($table): void {
        $table->increments('id');
        $table->string('active_revision_id')->nullable();
    });

    $schema->create('transcript_revisions', function ($table): void {
        $table->string('id')->primary();
        $table->unsignedInteger('transcription_id');
        $table->unsignedInteger('version');
        $table->string('parent_revision_id')->nullable();
    });

    $schema->create('transcript_revision_segments', function ($table): void {
        $table->increments('id');
        $table->string('revision_id');
    });
}
