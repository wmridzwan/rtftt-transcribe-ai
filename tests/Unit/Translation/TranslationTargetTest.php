<?php

use App\Translation\TranslationTarget;

it('supports exactly the frozen target languages', function () {
    expect(TranslationTarget::values())->toBe(['ms', 'en', 'zh', 'ta']);
});

it('resolves supported BCP 47 tags', function () {
    expect(TranslationTarget::fromBcp47('ms'))->toBe(TranslationTarget::Malay);
    expect(TranslationTarget::fromBcp47('en'))->toBe(TranslationTarget::English);
    expect(TranslationTarget::fromBcp47('zh'))->toBe(TranslationTarget::Chinese);
    expect(TranslationTarget::fromBcp47('ta'))->toBe(TranslationTarget::Tamil);
});

it('resolves prefixed and case-insensitive tags', function () {
    expect(TranslationTarget::fromBcp47('en-US'))->toBe(TranslationTarget::English);
    expect(TranslationTarget::fromBcp47('MS-MY'))->toBe(TranslationTarget::Malay);
    expect(TranslationTarget::fromBcp47('Zh-CN'))->toBe(TranslationTarget::Chinese);
});

it('rejects undefined and unsupported targets', function () {
    expect(TranslationTarget::fromBcp47('und'))->toBeNull();
    expect(TranslationTarget::fromBcp47('fr'))->toBeNull();
    expect(TranslationTarget::fromBcp47(''))->toBeNull();
    expect(TranslationTarget::fromBcp47('   '))->toBeNull();
});
