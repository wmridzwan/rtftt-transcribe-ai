<?php

use App\Editing\EditableField;
use App\Editing\EditKind;
use App\Editing\TranslationInvalidationPolicy;
use App\Editing\TranslationStalenessReason;

it('marks every edit kind stale with an explicit reason', function () {
    expect(TranslationInvalidationPolicy::mustMarkStale(EditKind::Textual))->toBeTrue()
        ->and(TranslationInvalidationPolicy::mustMarkStale(EditKind::Timing))->toBeTrue()
        ->and(TranslationInvalidationPolicy::mustMarkStale(EditKind::Structural))->toBeTrue()
        ->and(TranslationInvalidationPolicy::reasonFor(EditKind::Textual))->toBe(TranslationStalenessReason::SourceTextChanged)
        ->and(TranslationInvalidationPolicy::reasonFor(EditKind::Timing))->toBe(TranslationStalenessReason::TimingChanged)
        ->and(TranslationInvalidationPolicy::reasonFor(EditKind::Structural))->toBe(TranslationStalenessReason::SegmentStructureChanged);
});

it('never allows a translation to remain current or to be silently remapped', function () {
    foreach (EditKind::cases() as $kind) {
        expect(TranslationInvalidationPolicy::mayRemainCurrent($kind))->toBeFalse()
            ->and(TranslationInvalidationPolicy::neverSilentlyRemaps($kind))->toBeTrue();
    }
});

it('maps editable fields explicitly', function () {
    expect(EditableField::Text->value)->toBe('text')
        ->and(EditableField::StartTime->value)->toBe('start_seconds')
        ->and(EditableField::EndTime->value)->toBe('end_seconds')
        ->and(EditableField::Text->label())->toBe('Text');
});
