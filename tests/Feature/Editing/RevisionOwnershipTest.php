<?php

use App\Editing\RevisionService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\EditingFixtures;
use Tests\Support\EditingPersistenceFixtures;

beforeEach(function (): void {
    $this->service = app(RevisionService::class);
});

it('lets the owner materialize, edit, undo, and redo revisions', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $v1 = $this->service->materializeInitial($owner, $transcription);
    $v2 = $this->service->edit($owner, $transcription, $v1->revisionId, EditingFixtures::sequence());

    $this->service->undo($owner, $transcription, $v1->revisionId);
    $redone = $this->service->redo($owner, $transcription);

    expect($redone->revisionId)->toBe($v2->revisionId);
});

it('lets an admin mutate revisions they do not own', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $admin = User::factory()->admin()->create();

    $v1 = $this->service->materializeInitial($admin, $transcription);

    expect($v1->createdBy)->toBe($admin->getKey())
        ->and($this->service->active($admin, $transcription)?->revisionId)->toBe($v1->revisionId);
});

it('forbids a non-owner from mutating revisions', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;
    $intruder = User::factory()->create();

    $v1 = $this->service->materializeInitial($owner, $transcription);

    expect(fn () => $this->service->materializeInitial($intruder, $transcription))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $this->service->edit($intruder, $transcription, $v1->revisionId, EditingFixtures::sequence()))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $this->service->undo($intruder, $transcription, $v1->revisionId))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $this->service->redo($intruder, $transcription))
        ->toThrow(AuthorizationException::class);
});

it('forbids a non-owner from reading revision state', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;
    $intruder = User::factory()->create();

    $this->service->materializeInitial($owner, $transcription);

    expect(fn () => $this->service->active($intruder, $transcription))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $this->service->history($intruder, $transcription))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $this->service->redoTarget($intruder, $transcription))
        ->toThrow(AuthorizationException::class);
});

it('isolates revision history between transcriptions', function () {
    $a = EditingPersistenceFixtures::completedTranscription();
    $b = EditingPersistenceFixtures::completedTranscription();

    $this->service->materializeInitial($a->user, $a);
    $this->service->materializeInitial($b->user, $b);

    expect($this->service->history($a->user, $a))->toHaveCount(1)
        ->and($this->service->history($b->user, $b))->toHaveCount(1)
        ->and($this->service->active($a->user, $a)?->transcriptionId)->toBe($a->getKey())
        ->and($this->service->active($b->user, $b)?->transcriptionId)->toBe($b->getKey());
});
