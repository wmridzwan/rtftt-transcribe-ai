<?php

use App\Jobs\ProcessTranscription;

test('the job rejects non-positive identifiers', function () {
    expect(fn () => new ProcessTranscription(0, 1))->toThrow(InvalidArgumentException::class);
    expect(fn () => new ProcessTranscription(1, 0))->toThrow(InvalidArgumentException::class);
    expect(fn () => new ProcessTranscription(-1, -1))->toThrow(InvalidArgumentException::class);
});

test('the serialized job payload carries only small server-controlled identifiers', function () {
    $serialized = serialize(new ProcessTranscription(123, 456));

    expect($serialized)->toContain('transcriptionId')
        ->and($serialized)->toContain('processingAttemptId')
        ->and($serialized)->toContain('123')
        ->and($serialized)->toContain('456')
        ->and(strlen($serialized))->toBeLessThan(2000);
});
