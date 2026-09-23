<?php

use App\Editing\RevisionConflictException;
use App\Editing\RevisionFactory;
use App\Editing\RevisionRepository;
use App\Editing\RevisionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EditingFixtures;
use Tests\Support\EditingPersistenceFixtures;

beforeEach(function (): void {
    $this->service = app(RevisionService::class);
    $this->repository = app(RevisionRepository::class);
    $this->factory = new RevisionFactory;
});

it('handles a read-max-then-insert version-allocation race as a domain conflict', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    $v1 = $this->service->materializeInitial($user, $transcription);

    // Two writers read the same active base and allocate the same next version
    // before either commits — the read-max-then-insert race.
    $base = $this->repository->activeFor($transcription->getKey());
    $allocatedA = $this->repository->nextVersionFor($transcription->getKey());
    $allocatedB = $this->repository->nextVersionFor($transcription->getKey());

    expect($allocatedA)->toBe(2)->and($allocatedB)->toBe(2);

    $revisionA = $this->factory->derive($base, $user->getKey(), EditingFixtures::sequence(), $this->repository);
    $revisionB = $this->factory->derive($base, $user->getKey(), EditingFixtures::sequence(), $this->repository);

    expect($revisionA->version)->toBe(2)->and($revisionB->version)->toBe(2);

    // Writer A commits; the active pointer moves.
    $this->repository->append($revisionA, $v1->revisionId);

    // Writer B loses the active-pointer CAS.
    expect(fn () => $this->repository->append($revisionB, $v1->revisionId))
        ->toThrow(RevisionConflictException::class);

    // Undo back to the branch point and replay writer B's stale allocation:
    // now the monotonic-version backstop (not the CAS) must reject it.
    $this->repository->activate($transcription->getKey(), $v1->revisionId, $revisionA->revisionId);

    expect(fn () => $this->repository->append($revisionB, $v1->revisionId))
        ->toThrow(RevisionConflictException::class);

    $versions = array_map(fn ($r): int => $r->version, $this->repository->historyFor($transcription->getKey()));

    expect($versions)->toBe([1, 2])
        ->and(count($versions))->toBe(count(array_unique($versions)));
});

it('never lets two stale writers silently commit the same version', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    $v1 = $this->service->materializeInitial($user, $transcription);

    $a = $this->factory->derive($v1, $user->getKey(), EditingFixtures::sequence(), $this->repository);
    $b = $this->factory->derive($v1, $user->getKey(), EditingFixtures::sequence(), $this->repository);

    $this->repository->append($a, $v1->revisionId);

    expect(fn () => $this->repository->append($b, $v1->revisionId))
        ->toThrow(RevisionConflictException::class);

    expect($this->repository->historyFor($transcription->getKey()))->toHaveCount(2);
});

it('fences active-pointer changes with compare-and-set under two writers', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    $v1 = $this->service->materializeInitial($user, $transcription);
    $v2 = $this->service->edit($user, $transcription, $v1->revisionId, EditingFixtures::sequence());

    // Both writers hold the same expected active token (v2).
    $this->repository->activate($transcription->getKey(), $v1->revisionId, $v2->revisionId);

    expect(fn () => $this->repository->activate($transcription->getKey(), $v2->revisionId, $v2->revisionId))
        ->toThrow(RevisionConflictException::class);

    expect($this->repository->activeFor($transcription->getKey())?->revisionId)->toBe($v1->revisionId);
});

it('relies on the database unique constraint as the durable version backstop', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $now = now();

    DB::table('transcript_revisions')->insert([
        'id' => (string) Str::uuid(),
        'transcription_id' => $transcription->getKey(),
        'version' => 1,
        'parent_revision_id' => null,
        'created_by' => $transcription->user_id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    expect(fn () => DB::table('transcript_revisions')->insert([
        'id' => (string) Str::uuid(),
        'transcription_id' => $transcription->getKey(),
        'version' => 1,
        'parent_revision_id' => null,
        'created_by' => $transcription->user_id,
        'created_at' => $now,
        'updated_at' => $now,
    ]))->toThrow(UniqueConstraintViolationException::class);
});
