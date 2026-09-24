<?php

use App\Editing\EditKind;
use App\Editing\TranslationStalenessReason;

it('exposes the frozen precedence ordering', function () {
    expect(TranslationStalenessReason::SegmentStructureChanged->precedence())->toBe(3)
        ->and(TranslationStalenessReason::TimingChanged->precedence())->toBe(2)
        ->and(TranslationStalenessReason::SourceTextChanged->precedence())->toBe(1);
});

it('matches the edit-kind precedence exactly', function () {
    expect(TranslationStalenessReason::SourceTextChanged->precedence())->toBe(EditKind::Textual->precedence())
        ->and(TranslationStalenessReason::TimingChanged->precedence())->toBe(EditKind::Timing->precedence())
        ->and(TranslationStalenessReason::SegmentStructureChanged->precedence())->toBe(EditKind::Structural->precedence());
});
