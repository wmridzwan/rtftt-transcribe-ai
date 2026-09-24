<?php

use App\Comparison\ComparisonRow;

/**
 * P6-007 provenance-truthfulness predicate (MEDIUM corrective): the per-row
 * edited-revision note must be gated on a persisted translation actually
 * existing for that row, in addition to the revision/mismatch text comparison.
 */
function row(
    ?int $machineIndex,
    ?string $machineText,
    ?string $translatedText,
    ?string $revisionText,
    bool $revisionAligned,
): ComparisonRow {
    return new ComparisonRow(
        machineIndex: $machineIndex,
        machineText: $machineText,
        machineLanguage: 'en',
        translatedText: $translatedText,
        revisionPosition: 0,
        revisionText: $revisionText,
        revisionAligned: $revisionAligned,
    );
}

it('reports a revision as edited when aligned and the text differs from the machine source', function () {
    $row = row(0, 'machine', 'translated', 'edited', true);

    expect($row->revisionEdited())->toBeTrue();
});

it('never warrants the edited-revision note when no translation is persisted', function () {
    $row = row(0, 'machine', null, 'edited', true);

    expect($row->hasTranslation())->toBeFalse()
        ->and($row->revisionEdited())->toBeTrue()
        ->and($row->hasEditedRevisionWithPersistedTranslation())->toBeFalse();
});

it('warrants the edited-revision note only when a persisted translation exists', function () {
    $row = row(0, 'machine', 'translated', 'edited', true);

    expect($row->hasTranslation())->toBeTrue()
        ->and($row->hasEditedRevisionWithPersistedTranslation())->toBeTrue();
});

it('never warrants the note when the revision is not edited relative to the machine source', function () {
    $row = row(0, 'machine', 'translated', 'machine', true);

    expect($row->revisionEdited())->toBeFalse()
        ->and($row->hasEditedRevisionWithPersistedTranslation())->toBeFalse();
});

it('never warrants the note for an unaligned revision segment', function () {
    $row = row(null, null, null, 'restructured', false);

    expect($row->revisionEdited())->toBeFalse()
        ->and($row->hasEditedRevisionWithPersistedTranslation())->toBeFalse();
});
