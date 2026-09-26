<?php

use App\Editing\RevisionService;
use App\Editing\TranscriptRevision;
use App\Models\Transcription;
use App\Models\TranscriptRevisionModel;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\EditingFixtures;
use Tests\Support\EditingPersistenceFixtures;

/**
 * P6-008 revision-history surface + explicit historical activation over the
 * frozen P6-001..P6-007 revision foundation.
 */
function activateUrl(Transcription $transcription): string
{
    return route('transcriptions.revisions.activate', $transcription);
}

function revisionCount(Transcription $transcription): int
{
    return TranscriptRevisionModel::query()
        ->where('transcription_id', $transcription->getKey())
        ->count();
}

/**
 * Build a transcription with two linear revisions (v1 initial, v2 edit) and
 * v2 active. Returns [$transcription, $owner, $v1, $v2].
 *
 * @return array{Transcription, User, TranscriptRevision, TranscriptRevision}
 */
function twoRevisionTranscription(): array
{
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;
    $service = app(RevisionService::class);

    $v1 = $service->materializeInitial($owner, $transcription);
    $v2 = $service->edit($owner, $transcription, $v1->revisionId, EditingFixtures::sequence());

    return [$transcription, $owner, $v1, $v2];
}

it('renders the machine-source state when no revision exists', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $response = $this->actingAs($owner)->get(route('transcriptions.show', $transcription));

    $response->assertOk()
        ->assertSee('data-revision-history', false)
        ->assertSee('data-history-machine-source', false)
        ->assertSee('machine transcript is authoritative', false);
});

it('renders every durable revision in version order with the active marker and persisted metadata', function () {
    [$transcription, $owner, $v1, $v2] = twoRevisionTranscription();

    $response = $this->actingAs($owner)->get(route('transcriptions.show', $transcription));

    $response->assertOk()
        ->assertSee('data-history-list', false)
        ->assertSee('data-history-version="1"', false)
        ->assertSee('data-history-version="2"', false)
        ->assertSee('data-history-active', false)
        ->assertSee($owner->name, false)
        ->assertSee('from machine source', false)
        ->assertSee('from v1', false)
        ->assertSee('2 segments', false);

    $body = $response->getContent();
    $v1Position = strpos($body, 'data-history-version="1"');
    $v2Position = strpos($body, 'data-history-version="2"');

    expect($v1Position)->not->toBeFalse()
        ->and($v2Position)->not->toBeFalse()
        ->and($v1Position)->toBeLessThan($v2Position);
});

it('activates a historical revision without creating a new revision', function () {
    [$transcription, $owner, $v1, $v2] = twoRevisionTranscription();

    $response = $this->actingAs($owner)->post(activateUrl($transcription), [
        'target' => $v1->revisionId,
        'expected_base' => $v2->revisionId,
    ]);

    $response->assertRedirect(route('transcriptions.show', $transcription))
        ->assertSessionHas('history_notice', 'Historical revision activated.');

    expect($transcription->fresh()->active_revision_id)->toBe($v1->revisionId)
        ->and(revisionCount($transcription))->toBe(2);

    // The workspace now identifies v1 as active.
    $workspace = $this->actingAs($owner)->get(route('transcriptions.show', $transcription));
    $workspace->assertOk()->assertSee('Revision v1', false);
});

it('activates an arbitrary sibling-branch revision beyond undo reach', function () {
    [$transcription, $owner, $v1, $v2] = twoRevisionTranscription();
    $service = app(RevisionService::class);

    // Undo to v1, then branch: v3 is a sibling of v2, so v2 is not an
    // ancestor of the active revision and undo cannot reach it.
    $service->undo($owner, $transcription, $v1->revisionId);
    $v3 = $service->edit($owner, $transcription, $v1->revisionId, EditingFixtures::sequence());

    $response = $this->actingAs($owner)->post(activateUrl($transcription), [
        'target' => $v2->revisionId,
        'expected_base' => $v3->revisionId,
    ]);

    $response->assertRedirect(route('transcriptions.show', $transcription))
        ->assertSessionHas('history_notice', 'Historical revision activated.');

    expect($transcription->fresh()->active_revision_id)->toBe($v2->revisionId)
        ->and(revisionCount($transcription))->toBe(3);
});

it('treats activation of the already-active revision as a no-op success', function () {
    [$transcription, $owner, $v1, $v2] = twoRevisionTranscription();

    $response = $this->actingAs($owner)->post(activateUrl($transcription), [
        'target' => $v2->revisionId,
        'expected_base' => $v2->revisionId,
    ]);

    $response->assertRedirect(route('transcriptions.show', $transcription))
        ->assertSessionHas('history_notice', 'That revision is already the active revision. Nothing changed.');

    expect($transcription->fresh()->active_revision_id)->toBe($v2->revisionId)
        ->and(revisionCount($transcription))->toBe(2);
});

it('rejects a stale activation base as a conflict without moving the pointer', function () {
    [$transcription, $owner, $v1, $v2] = twoRevisionTranscription();
    $service = app(RevisionService::class);

    // A concurrent writer moves the pointer first.
    $service->undo($owner, $transcription, $v1->revisionId);

    $response = $this->actingAs($owner)->post(activateUrl($transcription), [
        'target' => $v2->revisionId,
        'expected_base' => $v2->revisionId,
    ]);

    $response->assertRedirect(route('transcriptions.show', $transcription))
        ->assertSessionHas('history_conflict');

    expect($transcription->fresh()->active_revision_id)->toBe($v1->revisionId)
        ->and(revisionCount($transcription))->toBe(2);
});

it('rejects an unknown revision target without moving the pointer', function () {
    [$transcription, $owner, $v1, $v2] = twoRevisionTranscription();

    $response = $this->actingAs($owner)->post(activateUrl($transcription), [
        'target' => '00000000-0000-0000-0000-000000000000',
        'expected_base' => $v2->revisionId,
    ]);

    $response->assertRedirect(route('transcriptions.show', $transcription))
        ->assertSessionHas('history_error');

    expect($transcription->fresh()->active_revision_id)->toBe($v2->revisionId)
        ->and(revisionCount($transcription))->toBe(2);
});

it('rejects a cross-transcription revision target', function () {
    [$transcription, $owner, $v1, $v2] = twoRevisionTranscription();

    $other = EditingPersistenceFixtures::completedTranscription();
    $otherOwner = $other->user;
    $otherService = app(RevisionService::class);
    $foreign = $otherService->materializeInitial($otherOwner, $other);

    $response = $this->actingAs($owner)->post(activateUrl($transcription), [
        'target' => $foreign->revisionId,
        'expected_base' => $v2->revisionId,
    ]);

    $response->assertRedirect(route('transcriptions.show', $transcription))
        ->assertSessionHas('history_error');

    expect($transcription->fresh()->active_revision_id)->toBe($v2->revisionId)
        ->and($other->fresh()->active_revision_id)->toBe($foreign->revisionId);
});

it('forbids activation and history reads for non-owners at both boundaries', function () {
    [$transcription, $owner, $v1, $v2] = twoRevisionTranscription();
    $intruder = User::factory()->create();

    // HTTP boundary.
    $this->actingAs($intruder)->post(activateUrl($transcription), [
        'target' => $v1->revisionId,
        'expected_base' => $v2->revisionId,
    ])->assertForbidden();

    $this->actingAs($intruder)->get(route('transcriptions.show', $transcription))->assertForbidden();

    // Service boundary.
    $service = app(RevisionService::class);

    expect(fn () => $service->activateHistorical($intruder, $transcription, $v1->revisionId))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $service->history($intruder, $transcription))
        ->toThrow(AuthorizationException::class);

    expect($transcription->fresh()->active_revision_id)->toBe($v2->revisionId);
});

it('lets an admin view history and activate without owning the transcription', function () {
    [$transcription, $owner, $v1, $v2] = twoRevisionTranscription();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('transcriptions.show', $transcription))->assertOk()
        ->assertSee('data-history-list', false);

    $this->actingAs($admin)->post(activateUrl($transcription), [
        'target' => $v1->revisionId,
        'expected_base' => $v2->revisionId,
    ])->assertSessionHas('history_notice', 'Historical revision activated.');

    expect($transcription->fresh()->active_revision_id)->toBe($v1->revisionId);
});

it('never mutates history or the active pointer when reading', function () {
    [$transcription, $owner, $v1, $v2] = twoRevisionTranscription();
    $service = app(RevisionService::class);

    $history = $service->history($owner, $transcription);

    expect($history)->toHaveCount(2)
        ->and(array_map(fn ($revision): int => $revision->version, $history))->toBe([1, 2]);

    $this->actingAs($owner)->get(route('transcriptions.show', $transcription))->assertOk();

    expect($transcription->fresh()->active_revision_id)->toBe($v2->revisionId)
        ->and(revisionCount($transcription))->toBe(2)
        ->and($transcription->fresh()->updated_at->timestamp)->toBe($transcription->updated_at->timestamp);
});

it('marks the causing revision when persisted staleness names it', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    Translation::factory()->completed()->create(['transcription_id' => $transcription->getKey()]);

    // A structural edit from the machine source appends v2 and persists
    // staleness caused by v2.
    $this->actingAs($owner)->post(route('transcriptions.revisions.split', $transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 2.0,
        'text_offset' => 4,
    ])->assertSessionHas('structural_notice');

    $response = $this->actingAs($owner)->get(route('transcriptions.show', $transcription));

    $response->assertOk()->assertSee('data-history-stale-cause', false);
});
