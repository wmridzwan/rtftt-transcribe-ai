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

it('selects the most invasive reason for an edit spanning multiple categories', function () {
    expect(TranslationInvalidationPolicy::reasonForKinds(EditKind::Textual, EditKind::Timing))
        ->toBe(TranslationStalenessReason::TimingChanged)
        ->and(TranslationInvalidationPolicy::reasonForKinds(EditKind::Timing, EditKind::Structural))
        ->toBe(TranslationStalenessReason::SegmentStructureChanged)
        ->and(TranslationInvalidationPolicy::reasonForKinds(EditKind::Textual, EditKind::Structural))
        ->toBe(TranslationStalenessReason::SegmentStructureChanged)
        ->and(TranslationInvalidationPolicy::reasonForKinds(EditKind::Textual, EditKind::Timing, EditKind::Structural))
        ->toBe(TranslationStalenessReason::SegmentStructureChanged)
        ->and(TranslationInvalidationPolicy::reasonForKinds(EditKind::Structural, EditKind::Textual))
        ->toBe(TranslationStalenessReason::SegmentStructureChanged)
        ->and(TranslationInvalidationPolicy::reasonForKinds(EditKind::Timing, EditKind::Textual))
        ->toBe(TranslationStalenessReason::TimingChanged)
        ->and(TranslationInvalidationPolicy::reasonForKinds(EditKind::Textual))
        ->toBe(TranslationStalenessReason::SourceTextChanged);
});

it('orders edit-kind precedence Structural > Timing > Textual', function () {
    expect(EditKind::Structural->precedence())->toBeGreaterThan(EditKind::Timing->precedence())
        ->and(EditKind::Timing->precedence())->toBeGreaterThan(EditKind::Textual->precedence());
});

it('requires at least one edit kind to select a staleness reason', function () {
    expect(fn () => TranslationInvalidationPolicy::reasonForKinds())
        ->toThrow(InvalidArgumentException::class);
});

it('keeps every category of a mixed edit invalidating regardless of precedence', function () {
    expect(TranslationInvalidationPolicy::mustMarkStale(EditKind::Textual))->toBeTrue()
        ->and(TranslationInvalidationPolicy::mustMarkStale(EditKind::Timing))->toBeTrue()
        ->and(TranslationInvalidationPolicy::mustMarkStale(EditKind::Structural))->toBeTrue();
});
